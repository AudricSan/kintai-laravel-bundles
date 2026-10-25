<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\TimeOff\Controllers\Api;


use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Bundles\Installed\TimeOff\Controllers\Api\TimeoffRequestController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Bundles\Installed\TimeOff\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Régression (audit RBAC du 11/09/2026) : show/update/destroy ne re-vérifiaient
 * jamais que la demande de congé chargée appartenait à un store géré par
 * l'appelant — voir le docblock de TimeoffRequestController.
 */
final class TimeoffRequestControllerTest extends TestCase
{
    private TimeoffRequestRepositoryInterface&MockObject $timeoffRequests;
    private RoleAssignmentRepositoryInterface&MockObject $assignments;
    private RoleRepositoryInterface&MockObject $roles;
    private TimeoffRequestController $controller;
    private StoreUserRepositoryInterface&Stub $storeUsers;

    protected function setUp(): void
    {
        $this->timeoffRequests = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $this->assignments     = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles           = $this->createMock(RoleRepositoryInterface::class);
        $this->controller      = new TimeoffRequestController(
            $this->timeoffRequests,
            new AuditLogger(),
            new PermissionService($this->assignments, $this->roles),
            $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class)
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
        $this->roles->method('getPermissions')->with(20)->willReturn(['timeoff.view', 'timeoff.update', 'timeoff.delete']);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => $userId]);
        return $req;
    }

    public function testIndexByUserIdExcludesRequestsFromOtherStoresForScopedManager(): void
    {
        $_GET = ['user_id' => '99'];

        $this->timeoffRequests->method('findByUser')->with(99)->willReturn([
            ['id' => 1, 'store_id' => 5],
            ['id' => 2, 'store_id' => 9],
        ]);

        $response = $this->controller->index($this->requestAsStoreManager(2, 5));
        $body = json_decode($response->body(), true);

        $this->assertCount(1, $body['data']);
        $this->assertSame(5, $body['data'][0]['store_id']);
    }

    public function testShowThrowsNotFoundForMissingRequest(): void
    {
        $this->timeoffRequests->method('findById')->with(99)->willReturn(null);

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '99']);

        $this->expectException(NotFoundException::class);
        $this->controller->show($req);
    }

    public function testShowRejectsRequestFromAnotherStore(): void
    {
        $this->timeoffRequests->method('findById')->with(5)->willReturn(['id' => 5, 'store_id' => 9]);

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller->show($req);
    }

    public function testUpdateRejectsRequestFromAnotherStore(): void
    {
        $this->timeoffRequests->method('findById')->with(5)->willReturn(['id' => 5, 'store_id' => 9]);
        $this->timeoffRequests->expects($this->never())->method('save');

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller->update($req);
    }

    public function testDestroyAllowsRequestInManagedStore(): void
    {
        $this->timeoffRequests->method('findById')->with(5)->willReturn(['id' => 5, 'store_id' => 5]);
        $this->timeoffRequests->expects($this->once())->method('delete')->with(5);

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller->destroy($req);

        $this->assertSame(204, $response->status());
    }

    public function testDestroyRejectsRequestFromAnotherStore(): void
    {
        $this->timeoffRequests->method('findById')->with(5)->willReturn(['id' => 5, 'store_id' => 9]);
        $this->timeoffRequests->expects($this->never())->method('delete');

        $req = $this->requestAsStoreManager(2, 5);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller->destroy($req);
    }
}
