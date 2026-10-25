<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\DailyReport\Controllers\Api;

use kintai\Bundles\Installed\DailyReport\Controllers\Api\DailyReportController;
use kintai\Bundles\Installed\DailyReport\Repositories\DailyReportRepositoryInterface;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportPermissionService;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Request;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * GET /api/v1/daily-reports (audit n°8, B2) : sans store_id, ou avec author_id, la liste renvoyait les rapports de
 * tous les magasins à n'importe quel compte autorisé dans un seul. Elle ne garde plus que ce que show() accepterait.
 */
final class DailyReportIndexScopeTest extends TestCase
{
    private DailyReportRepositoryInterface&Stub $reports;
    private StoreUserRepositoryInterface&Stub $storeUsers;
    private RoleAssignmentRepositoryInterface&Stub $assignments;
    private RoleRepositoryInterface&Stub $roles;
    private ?DailyReportController $controller = null;

    private const REPORTS = [
        ['id' => 1, 'store_id' => 1, 'author_id' => 10, 'status' => 'submitted'],
        ['id' => 2, 'store_id' => 1, 'author_id' => 20, 'status' => 'draft'],
        ['id' => 3, 'store_id' => 2, 'author_id' => 30, 'status' => 'submitted'],
    ];

    protected function setUp(): void
    {
        $this->reports     = $this->createStub(DailyReportRepositoryInterface::class);
        
        $this->storeUsers  = $this->createStub(StoreUserRepositoryInterface::class);
        $this->assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $this->roles       = $this->createStub(RoleRepositoryInterface::class);

        $this->reports->method('findAllActive')->willReturn(self::REPORTS);
        $this->reports->method('findByAuthor')->willReturnCallback(
            fn(int $a) => array_values(array_filter(self::REPORTS, fn($r) => $r['author_id'] === $a))
        );
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): DailyReportController
    {
        if ($this->controller === null) {
            $stores            = $this->createStub(StoreRepositoryInterface::class);
            $stores->method('findById')->willReturnCallback(fn(int $id) => in_array($id, [1, 2], true) ? ['id' => $id] : null);
            $this->controller = new DailyReportController(
                $this->reports,
                $stores,
                $this->storeUsers,
                new DailyReportPermissionService(new PermissionService($this->assignments, $this->roles)),
            );
        }

        return $this->controller;
    }

    /** Rôle de magasin portant la permission de lecture sur le seul magasin $storeId. */
    private function managerOf(int $userId, int $storeId): void
    {
        $this->assignments->method('findByUser')->willReturnCallback(fn($a0) => $a0 === $userId ? [
            ['id' => 1, 'user_id' => $userId, 'role_id' => 5, 'scope_type' => 'store', 'scope_id' => $storeId],
        ] : []);
        $this->roles->method('findById')->willReturnCallback(fn($a0) => $a0 === 5 ? ['id' => 5, 'is_system' => 0] : null);
        $this->roles->method('getPermissions')->willReturnCallback(fn($a0) => $a0 === 5 ? ['daily_reports.view'] : []);
        $this->storeUsers->method('findMembership')->willReturnCallback(
            fn(int $s, int $u) => $s === $storeId ? ['store_id' => $s, 'user_id' => $u] : null
        );
    }

    /** @return list<int> */
    private function listedIds(array $query, int $userId): array
    {
        $_GET = $query;
        $req = new Request();
        $_GET = [];
        $req->setAttribute('auth_user', ['id' => $userId]);
        $body = json_decode($this->controller()->index($req)->body(), true);
        return array_map(fn($r) => (int) $r['id'], $body['data']);
    }

    public function testManagerOfOneStoreNoLongerSeesOtherStoresWithoutFilter(): void
    {
        $this->managerOf(40, 2);
        $this->assertSame([3], $this->listedIds([], 40));
    }

    public function testAuthorFilterCannotReachAnotherStore(): void
    {
        $this->managerOf(40, 2);
        $this->assertSame([], $this->listedIds(['author_id' => '10'], 40));
    }

    public function testPlainMemberOnlySeesOwnReports(): void
    {
        $this->assignments->method('findByUser')->willReturn([]);
        $this->storeUsers->method('findMembership')->willReturnCallback(
            fn(int $s, int $u) => $s === 1 ? ['store_id' => 1, 'user_id' => $u] : null
        );
        $this->assertSame([2], $this->listedIds([], 20));
    }
}
