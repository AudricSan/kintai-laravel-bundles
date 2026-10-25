<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftClaim\Controllers\Api;

use kintai\Bundles\Installed\ShiftClaim\Controllers\Api\ShiftClaimController;
use kintai\Bundles\Installed\ShiftClaim\Repositories\ShiftClaimRepositoryInterface;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\Log;
use PHPUnit\Framework\TestCase;

/**
 * Régression (audit du 03/10/2026), reprise du Core à l'extraction : la création par l'API fusionnait le JSON brut du
 * client dans save(), qui fait un upsert dès qu'un « id » est présent. Un POST portant un « id » écrasait la candidature
 * de quelqu'un d'autre, et le statut ou la résolution passaient tels quels.
 *
 * L'appelant (id 2) est un employé du magasin 5 ; le magasin 6 ne le concerne pas.
 */
final class ShiftClaimMassAssignmentTest extends TestCase
{
    private const ME       = 2;
    private const MY_STORE = 5;
    private const OTHER    = 6;

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
        $roles->method('getPermissions')->willReturn(['open_shifts.approve']);
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

    public function testClaimCreationIsForcedPendingAndIgnoresId(): void
    {
        $repo = $this->createMock(ShiftClaimRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->with($this->callback(
            fn(array $d): bool => !array_key_exists('id', $d)
                && $d['status'] === 'pending'
                && !array_key_exists('resolved_by', $d)
                && $d['user_id'] === self::ME
                && $d['shift_id'] === 8
        ))->willReturn(['id' => 2]);
        $shifts = $this->createStub(\kintai\Core\Repositories\ShiftRepositoryInterface::class);
        $shifts->method('findById')->willReturn(['id' => 8, 'store_id' => self::MY_STORE, 'is_open' => 1, 'user_id' => null]);
        $controller = new ShiftClaimController($repo, $this->permissions, $this->storeUsers, $shifts);

        $controller->store($this->request([
            'id' => 1, 'user_id' => self::ME, 'store_id' => self::MY_STORE, 'shift_id' => 8,
            'status' => 'approved', 'resolved_by' => 99,
        ]));
    }

    public function testClaimCreationForAnotherStoreIsForbidden(): void
    {
        $repo = $this->createMock(ShiftClaimRepositoryInterface::class);
        $repo->expects($this->never())->method('save');
        $controller = new ShiftClaimController($repo, $this->permissions, $this->storeUsers);

        $this->expectException(ForbiddenException::class);
        $controller->store($this->request(['user_id' => self::ME, 'store_id' => self::OTHER, 'shift_id' => 8]));
    }
}
