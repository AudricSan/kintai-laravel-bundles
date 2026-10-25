<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftSwap\Controllers\Api;


use kintai\Bundles\Installed\ShiftSwap\Controllers\Api\ShiftSwapRequestController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Bundles\Installed\ShiftSwap\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Régression (audit RBAC du 11/09/2026) : show/update/destroy ne re-vérifiaient
 * jamais que la demande d'échange chargée appartenait à un store géré par
 * l'appelant — voir le docblock de ShiftSwapRequestController.
 */
final class ShiftSwapRequestControllerTest extends TestCase
{
    private ShiftSwapRequestRepositoryInterface&MockObject $swapRequests;
    private RoleAssignmentRepositoryInterface&MockObject $assignments;
    private RoleRepositoryInterface&MockObject $roles;
    private ShiftSwapRequestController $controller;

    protected function setUp(): void
    {
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $this->assignments  = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles        = $this->createMock(RoleRepositoryInterface::class);
        $this->controller   = new ShiftSwapRequestController(
            $this->swapRequests,
            new AuditLogger(),
            new PermissionService($this->assignments, $this->roles)
        );
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
    }

    private function requestAsStoreManager(int $userId, int $storeId): Request
    {
        $this->assignments->method('findByUser')->with($userId)->willReturn([
            ['id' => 1, 'user_id' => $userId, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => $storeId],
        ]);
        $this->roles->method('findById')->with(20)->willReturn(['id' => 20, 'is_system' => 0]);
        $this->roles->method('getPermissions')->with(20)->willReturn(['swaps.view', 'swaps.update', 'swaps.delete']);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => $userId]);
        return $req;
    }

    public function testIndexByRequesterIdExcludesEntriesFromOtherStoresForScopedManager(): void
    {
        $_GET = ['requester_id' => '99'];

        $this->swapRequests->method('findByRequester')->with(99)->willReturn([
            ['id' => 1, 'store_id' => 5],
            ['id' => 2, 'store_id' => 9],
        ]);

        $response = $this->controller->index($this->requestAsStoreManager(2, 5));
        $body = json_decode($response->body(), true);

        $this->assertCount(1, $body['data']);
        $this->assertSame(5, $body['data'][0]['store_id']);
    }

    public function testShowThrowsNotFoundForMissingSwap(): void
    {
        $this->swapRequests->method('findById')->with(99)->willReturn(null);

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '99']);

        $this->expectException(NotFoundException::class);
        $this->controller->show($req);
    }

    public function testShowRejectsSwapFromAnotherStore(): void
    {
        $this->swapRequests->method('findById')->with(5)->willReturn(['id' => 5, 'store_id' => 9]);

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller->show($req);
    }

    public function testUpdateRejectsSwapFromAnotherStore(): void
    {
        $this->swapRequests->method('findById')->with(5)->willReturn(['id' => 5, 'store_id' => 9]);
        $this->swapRequests->expects($this->never())->method('save');

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller->update($req);
    }

    public function testDestroyAllowsSwapInManagedStore(): void
    {
        $this->swapRequests->method('findById')->with(5)->willReturn(['id' => 5, 'store_id' => 5]);
        $this->swapRequests->expects($this->once())->method('delete')->with(5);

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller->destroy($req);

        $this->assertSame(204, $response->status());
    }

    public function testDestroyRejectsSwapFromAnotherStore(): void
    {
        $this->swapRequests->method('findById')->with(5)->willReturn(['id' => 5, 'store_id' => 9]);
        $this->swapRequests->expects($this->never())->method('delete');

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller->destroy($req);
    }
}
