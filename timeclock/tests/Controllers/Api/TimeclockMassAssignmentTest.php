<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Timeclock\Controllers\Api;

use kintai\Bundles\Installed\Timeclock\Controllers\Api\TimeclockController;
use kintai\Bundles\Installed\Timeclock\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use PHPUnit\Framework\TestCase;

/**
 * Régression (audit du 03/10/2026), reprise du Core à l'extraction : les API de bundles fusionnaient le JSON brut du
 * client dans save() ; un pointage pouvait viser un magasin dont l'employé n'est pas membre, ou être déplacé vers un
 * autre magasin ou employé.
 *
 * L'appelant (id 2) est un employé du magasin 5 ; le magasin 6 ne le concerne pas.
 */
final class TimeclockMassAssignmentTest extends TestCase
{
    private const ME       = 2;
    private const MY_STORE = 5;
    private const OTHER    = 6;

    private StoreUserRepositoryInterface $storeUsers;

    protected function setUp(): void
    {
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->storeUsers->method('findMembership')->willReturnCallback(
            fn(int $storeId, int $userId): ?array => $storeId === self::MY_STORE && $userId === self::ME
                ? ['store_id' => $storeId, 'user_id' => $userId]
                : null
        );
        $this->storeUsers->method('findByUser')->willReturn([['store_id' => self::MY_STORE]]);
    }

    protected function tearDown(): void
    {
        Log::reset();
    }

    /** @param list<string> $keys clés accordées sur le magasin 5 */
    private function permissions(array $keys): PermissionService
    {
        $assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $assignments->method('findByUser')->willReturn([
            ['id' => 1, 'user_id' => self::ME, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => self::MY_STORE],
        ]);
        $roles = $this->createStub(RoleRepositoryInterface::class);
        $roles->method('findById')->willReturn(['id' => 20, 'is_system' => 0]);
        $roles->method('getPermissions')->willReturn($keys);
        $roles->method('getGlobalPermissionKeys')->willReturn([]);
        return new PermissionService($assignments, $roles);
    }

    private function request(array $json, array $params = []): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => self::ME]);
        $req->setRouteParams($params);
        (new \ReflectionProperty(Request::class, 'jsonBody'))->setValue($req, $json);
        return $req;
    }

    public function testClockInInAStoreTheEmployeeDoesNotBelongToIsForbidden(): void
    {
        $repo = $this->createMock(TimeclockRepositoryInterface::class);
        $repo->method('findActiveByUser')->willReturn(null);
        $repo->expects($this->never())->method('save');
        $controller = new TimeclockController($repo, $this->storeUsers, new AuditLogger(), $this->permissions([]));

        $this->expectException(ForbiddenException::class);
        $controller->clockIn($this->request(['user_id' => self::ME, 'store_id' => self::OTHER]));
    }

    public function testTimeclockUpdateCannotMoveTheEntryToAnotherStoreOrUser(): void
    {
        $repo = $this->createMock(TimeclockRepositoryInterface::class);
        $repo->method('findById')->with(3)->willReturn(['id' => 3, 'user_id' => 9, 'store_id' => self::MY_STORE]);
        $repo->expects($this->once())->method('save')->with($this->callback(
            fn(array $d): bool => $d['id'] === 3
                && !array_key_exists('store_id', $d)
                && !array_key_exists('user_id', $d)
        ))->willReturn(['id' => 3, 'user_id' => 9, 'store_id' => self::MY_STORE]);
        $controller = new TimeclockController($repo, $this->storeUsers, new AuditLogger(), $this->permissions(['timeclock.update']));

        $controller->update($this->request(['note' => 'x', 'store_id' => self::OTHER, 'user_id' => 1], ['id' => '3']));
    }
}
