<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Timeclock\Controllers\Api;


use kintai\Bundles\Installed\Timeclock\Controllers\Api\TimeclockController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ConflictException;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Bundles\Installed\Timeclock\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Régression (audit RBAC du 11/09/2026) : show/update/destroy ne re-vérifiaient
 * jamais que l'entrée de pointage chargée appartenait à un store géré par
 * l'appelant — voir le docblock de TimeclockController.
 */
final class TimeclockControllerTest extends TestCase
{
    private TimeclockRepositoryInterface&Stub $timeclocks;
    private StoreUserRepositoryInterface&Stub $storeUsers;
    private RoleAssignmentRepositoryInterface&Stub $assignments;
    private RoleRepositoryInterface&Stub $roles;
    private ?TimeclockController $controller = null;

    protected function setUp(): void
    {
        $this->timeclocks  = $this->createStub(TimeclockRepositoryInterface::class);
        $this->storeUsers  = $this->createStub(StoreUserRepositoryInterface::class);
        $this->assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $this->roles       = $this->createStub(RoleRepositoryInterface::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): TimeclockController
    {
        if ($this->controller === null) {
            $this->controller  = new TimeclockController(
                $this->timeclocks,
                $this->storeUsers,
                new AuditLogger(),
                new PermissionService($this->assignments, $this->roles)
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

    private function requestAsOwner(): Request
    {
        $this->assignments->method('findByUser')->willReturnCallback(fn($a0) => $a0 === 1 ? [
            ['id' => 1, 'user_id' => 1, 'role_id' => 10, 'scope_type' => 'global', 'scope_id' => null],
        ] : []);
        $this->roles->method('findById')->willReturnCallback(fn($a0) => $a0 === 10 ? ['id' => 10, 'is_system' => 1] : null);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);
        return $req;
    }

    /** Manager avec timeclock.* uniquement sur le store $storeId. */
    private function requestAsStoreManager(int $userId, int $storeId): Request
    {
        $this->assignments->method('findByUser')->willReturnCallback(fn($a0) => $a0 === $userId ? [
            ['id' => 1, 'user_id' => $userId, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => $storeId],
        ] : []);
        $this->roles->method('findById')->willReturnCallback(fn($a0) => $a0 === 20 ? ['id' => 20, 'is_system' => 0] : null);
        $this->roles->method('getPermissions')->willReturnCallback(fn($a0) => $a0 === 20 ? ['timeclock.view', 'timeclock.update', 'timeclock.delete'] : []);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => $userId]);
        return $req;
    }

    public function testIndexReturnsEmptyWithoutFilters(): void
    {
        $response = $this->controller()->index($this->requestAsOwner());

        $body = json_decode($response->body(), true);
        $this->assertSame([], $body['data']);
    }

    public function testIndexFiltersByStoreAndDate(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $_GET = ['store_id' => '5', 'date' => '2026-08-01'];

        $this->timeclocks->expects($this->atLeastOnce())->method('findByStoreAndDate')->with(5, '2026-08-01')->willReturn([['id' => 1, 'store_id' => 5]]);

        $response = $this->controller()->index($this->requestAsOwner());
        $body = json_decode($response->body(), true);

        $this->assertCount(1, $body['data']);
    }

    public function testIndexByUserIdExcludesEntriesFromOtherStoresForScopedManager(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $_GET = ['user_id' => '99'];

        $this->timeclocks->expects($this->atLeastOnce())->method('findByUser')->with(99)->willReturn([
            ['id' => 1, 'store_id' => 5],
            ['id' => 2, 'store_id' => 9],
        ]);

        $response = $this->controller()->index($this->requestAsStoreManager(2, 5));
        $body = json_decode($response->body(), true);

        $this->assertCount(1, $body['data']);
        $this->assertSame(5, $body['data'][0]['store_id']);
    }

    public function testClockInRejectsWhenAlreadyActive(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $req = $this->requestWithJson(['user_id' => 1]);

        $this->timeclocks->expects($this->atLeastOnce())->method('findActiveByUser')->with(1)->willReturn(['id' => 5]);
        $this->timeclocks->expects($this->never())->method('save');

        $this->expectException(ConflictException::class);
        $this->controller()->clockIn($req);
    }

    public function testClockOutThrowsWhenNoActiveEntry(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $req = $this->requestWithJson(['user_id' => 1]);

        $this->timeclocks->expects($this->atLeastOnce())->method('findActiveByUser')->with(1)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller()->clockOut($req);
    }

    /**
     * Simule un corps JSON via réflexion : lire php://input n'est pas possible
     * dans un test unitaire (cf. tests/Unit/Controller/Api/V1/AuthControllerTest.php).
     */
    private function requestWithJson(array $json): Request
    {
        $req = new Request();
        $ref = new \ReflectionProperty(Request::class, 'jsonBody');
        $ref->setAccessible(true);
        $ref->setValue($req, $json);
        return $req;
    }

    public function testDestroyDeletesExistingEntry(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $this->timeclocks->expects($this->atLeastOnce())->method('findById')->with(7)->willReturn(['id' => 7, 'store_id' => 5]);
        $this->timeclocks->expects($this->once())->method('delete')->with(7);

        $req = $this->requestAsOwner();
        $req->setRouteParams(['id' => '7']);

        $response = $this->controller()->destroy($req);

        $this->assertSame(204, $response->status());
    }

    public function testDestroyRejectsEntryFromAnotherStore(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $this->timeclocks->expects($this->atLeastOnce())->method('findById')->with(7)->willReturn(['id' => 7, 'store_id' => 9]);
        $this->timeclocks->expects($this->never())->method('delete');

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '7']);

        $this->expectException(ForbiddenException::class);
        $this->controller()->destroy($req);
    }

    public function testUpdateRejectsEntryFromAnotherStore(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $this->timeclocks->expects($this->atLeastOnce())->method('findById')->with(7)->willReturn(['id' => 7, 'store_id' => 9]);
        $this->timeclocks->expects($this->never())->method('save');

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '7']);

        $this->expectException(ForbiddenException::class);
        $this->controller()->update($req);
    }
}
