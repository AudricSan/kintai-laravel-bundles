<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftClaim\Controllers\Api;


use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Bundles\Installed\ShiftClaim\Controllers\Api\ShiftClaimController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Bundles\Installed\ShiftClaim\Repositories\ShiftClaimRepositoryInterface;
use kintai\Core\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Régression (audit RBAC du 11/09/2026) : show/update/destroy ne re-vérifiaient
 * jamais que la candidature chargée appartenait à un store géré par l'appelant,
 * et index() sans filtre renvoyait TOUTES les candidatures de TOUS les stores à
 * n'importe quel porteur de token (findAll() sans restriction) — voir le
 * docblock de ShiftClaimController.
 */
final class ShiftClaimControllerTest extends TestCase
{
    private ShiftClaimRepositoryInterface&Stub $claims;
    private RoleAssignmentRepositoryInterface&Stub $assignments;
    private RoleRepositoryInterface&Stub $roles;
    private ?ShiftClaimController $controller = null;
    private StoreUserRepositoryInterface&Stub $storeUsers;

    protected function setUp(): void
    {
        $this->claims      = $this->createStub(ShiftClaimRepositoryInterface::class);
        $this->assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $this->roles       = $this->createStub(RoleRepositoryInterface::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): ShiftClaimController
    {
        if ($this->controller === null) {
            $this->controller  = new ShiftClaimController(
                $this->claims,
                new PermissionService($this->assignments, $this->roles),
                $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class)
            );
        }

        return $this->controller;
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    private function requestAsStoreManager(int $userId, int $storeId): Request
    {
        $this->assignments->method('findByUser')->willReturnCallback(fn($a0) => $a0 === $userId ? [
            ['id' => 1, 'user_id' => $userId, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => $storeId],
        ] : []);
        $this->roles->method('findById')->willReturnCallback(fn($a0) => $a0 === 20 ? ['id' => 20, 'is_system' => 0] : null);
        $this->roles->method('getPermissions')->willReturnCallback(fn($a0) => $a0 === 20 ? ['open_shifts.view', 'open_shifts.approve'] : []);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => $userId]);
        return $req;
    }

    public function testIndexWithoutFilterExcludesOtherStoresForScopedManager(): void
    {
        $this->claims->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 5],
            ['id' => 2, 'store_id' => 9],
        ]);

        $response = $this->controller()->index($this->requestAsStoreManager(2, 5));
        $body = json_decode($response->body(), true);

        $this->assertCount(1, $body['data']);
        $this->assertSame(5, $body['data'][0]['store_id']);
    }

    public function testShowThrowsNotFoundForMissingClaim(): void
    {
        $this->claims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $this->claims->expects($this->atLeastOnce())->method('findById')->with(99)->willReturn(null);

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '99']);

        $this->expectException(NotFoundException::class);
        $this->controller()->show($req);
    }

    public function testShowRejectsClaimFromAnotherStore(): void
    {
        $this->claims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $this->claims->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn(['id' => 5, 'store_id' => 9]);

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller()->show($req);
    }

    public function testDestroyAllowsClaimInManagedStore(): void
    {
        $this->claims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $this->claims->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn(['id' => 5, 'store_id' => 5]);
        $this->claims->expects($this->once())->method('delete')->with(5);

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller()->destroy($req);

        $this->assertSame(204, $response->status());
    }

    public function testDestroyRejectsClaimFromAnotherStore(): void
    {
        $this->claims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $this->claims->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn(['id' => 5, 'store_id' => 9]);
        $this->claims->expects($this->never())->method('delete');

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller()->destroy($req);
    }
}
