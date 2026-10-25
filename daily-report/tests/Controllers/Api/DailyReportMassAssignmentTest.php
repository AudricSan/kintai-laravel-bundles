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
use kintai\Core\Services\Log;
use PHPUnit\Framework\TestCase;

/**
 * Régression (audit du 03/10/2026), reprise du Core à l'extraction : la création par l'API fusionnait le JSON brut du
 * client dans save(), qui fait un upsert dès qu'un « id » est présent. Un POST portant un « id » écrasait donc le
 * rapport de quelqu'un d'autre, et l'auteur, le statut ou la validation passaient tels quels.
 *
 * L'appelant (id 2) est un employé du magasin 5 qui peut créer des rapports journaliers.
 */
final class DailyReportMassAssignmentTest extends TestCase
{
    private const ME       = 2;
    private const MY_STORE = 5;

    private PermissionService $permissions;
    private StoreUserRepositoryInterface $storeUsers;

    protected function setUp(): void
    {
        $assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $assignments->method('findByUser')->willReturn([
            ['id' => 1, 'user_id' => self::ME, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => self::MY_STORE],
        ]);
        $roles = $this->createStub(RoleRepositoryInterface::class);
        $roles->method('findById')->willReturn(['id' => 20, 'is_system' => 0]);
        $roles->method('getPermissions')->willReturn(['daily_reports.create']);
        $roles->method('getGlobalPermissionKeys')->willReturn([]);
        $this->permissions = new PermissionService($assignments, $roles);

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

    private function request(array $json): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => self::ME]);
        (new \ReflectionProperty(Request::class, 'jsonBody'))->setValue($req, $json);
        return $req;
    }

    public function testCreationForcesAuthorAndDraftAndIgnoresId(): void
    {
        $repo   = $this->createMock(DailyReportRepositoryInterface::class);
        $stores = $this->createStub(StoreRepositoryInterface::class);
        $stores->method('findById')->willReturn(['id' => self::MY_STORE, 'name' => 'A']);
        $repo->expects($this->once())->method('save')->with($this->callback(
            fn(array $d): bool => !array_key_exists('id', $d)
                && $d['author_id'] === self::ME
                && $d['status'] === 'draft'
                && $d['store_id'] === self::MY_STORE
                && !array_key_exists('validated_by', $d)
                && !array_key_exists('is_finalized', $d)
                && $d['notes'] === 'RAS'
        ))->willReturn(['id' => 6]);
        $controller = new DailyReportController($repo, $stores, $this->storeUsers, new DailyReportPermissionService($this->permissions));

        $controller->store($this->request([
            'id' => 1, 'author_id' => 99, 'store_id' => self::MY_STORE, 'notes' => 'RAS',
            'status' => 'validated', 'validated_by' => 99, 'is_finalized' => 1,
        ]));
    }
}
