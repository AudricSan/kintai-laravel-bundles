<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Timeclock\Controllers\Api;

use kintai\Bundles\Installed\Timeclock\Controllers\Api\TimeclockController;
use kintai\Bundles\Installed\Timeclock\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

/**
 * Libre-service de l'API (audit n°8 de Kintai, I3/D3) : un employé sans permission lit ses propres éléments avec
 * ?user_id=<lui> (règle self de la route). Avant, restrictToScope() vidait toujours sa liste. Il ne voit jamais ceux des
 * autres ; un gérant garde sa vue.
 */
final class ApiSelfServiceListTest extends TestCase
{
    private const EMPLOYEE = 3;
    private const MANAGER  = 1;

    private function controller(): TimeclockController
    {
        $items = [
            ['id' => 1, 'store_id' => 1, 'user_id' => self::EMPLOYEE],
            ['id' => 2, 'store_id' => 1, 'user_id' => 4],
            ['id' => 3, 'store_id' => 2, 'user_id' => self::EMPLOYEE],
        ];
        $repo = $this->createStub(TimeclockRepositoryInterface::class);
        $repo->method('findByUser')->willReturnCallback(fn(int $u) => array_values(array_filter($items, fn($i) => $i['user_id'] === $u)));
        $repo->method('findByStore')->willReturnCallback(fn(int $s) => array_values(array_filter($items, fn($i) => $i['store_id'] === $s)));

        $assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $assignments->method('findByUser')->willReturnCallback(fn(int $u) => $u === self::MANAGER
            ? [['id' => 1, 'user_id' => self::MANAGER, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1]]
            : []);
        $roles = $this->createStub(RoleRepositoryInterface::class);
        $roles->method('findById')->willReturn(['id' => 2, 'is_system' => 0]);
        $roles->method('getPermissions')->willReturn(['timeclock.view']);
        $roles->method('getGlobalPermissionKeys')->willReturn([]);
        $permissions = new PermissionService($assignments, $roles);
        $storeUsers = $this->createStub(StoreUserRepositoryInterface::class);

        return new TimeclockController($repo, $storeUsers, new AuditLogger(), $permissions);
    }

    /** @return list<int> */
    private function ids(int $caller, array $query): array
    {
        $_GET = $query;
        $request = new Request();
        $_GET = [];
        $request->setAttribute('auth_user', ['id' => $caller]);
        return array_map(fn($i) => (int) $i['id'], json_decode($this->controller()->index($request)->body(), true)['data']);
    }

    public function testAnEmployeeReadsTheirOwnItems(): void
    {
        $this->assertSame([1, 3], $this->ids(self::EMPLOYEE, ['user_id' => (string) self::EMPLOYEE]));
    }

    public function testAnEmployeeNeverSeesColleaguesItems(): void
    {
        $this->assertSame([1], $this->ids(self::EMPLOYEE, ['store_id' => '1']));
    }

    public function testAManagerStillSeesTheirStore(): void
    {
        $this->assertSame([1, 2], $this->ids(self::MANAGER, ['store_id' => '1']));
    }
}
