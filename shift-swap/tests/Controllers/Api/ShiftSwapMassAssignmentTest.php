<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftSwap\Controllers\Api;

use kintai\Bundles\Installed\ShiftSwap\Controllers\Api\ShiftSwapRequestController;
use kintai\Bundles\Installed\ShiftSwap\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use PHPUnit\Framework\TestCase;

/**
 * Régression (audit du 03/10/2026), reprise du Core à l'extraction : la création par l'API fusionnait le JSON brut du
 * client dans save(), qui fait un upsert dès qu'un « id » est présent ; le statut ou la validation passaient tels quels.
 *
 * L'appelant (id 2) est un employé du magasin 5 ; le magasin 6 ne le concerne pas.
 */
final class ShiftSwapMassAssignmentTest extends TestCase
{
    private const ME       = 2;
    private const MY_STORE = 5;
    private const OTHER    = 6;

    private PermissionService $permissions;

    protected function setUp(): void
    {
        $assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $assignments->method('findByUser')->willReturn([
            ['id' => 1, 'user_id' => self::ME, 'role_id' => 20, 'scope_type' => 'store', 'scope_id' => self::MY_STORE],
        ]);
        $roles = $this->createStub(RoleRepositoryInterface::class);
        $roles->method('findById')->willReturn(['id' => 20, 'is_system' => 0]);
        $roles->method('getPermissions')->willReturn(['swaps.create', 'swaps.update']);
        $roles->method('getGlobalPermissionKeys')->willReturn([]);
        $this->permissions = new PermissionService($assignments, $roles);
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

    public function testSwapCreationIsForcedPendingAndIgnoresId(): void
    {
        $repo = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->with($this->callback(
            fn(array $d): bool => !array_key_exists('id', $d)
                && $d['status'] === 'pending'
                && !array_key_exists('approved_by_id', $d)
                && $d['store_id'] === self::MY_STORE
                && $d['requester_id'] === self::ME
        ))->willReturn(['id' => 12]);
        $controller = new ShiftSwapRequestController($repo, new AuditLogger(), $this->permissions);

        $controller->store($this->request([
            'id' => 3, 'store_id' => self::MY_STORE, 'status' => 'approved', 'approved_by_id' => 99,
            'requester_shift_id' => 4, 'target_shift_id' => 5,
        ]));
    }

    public function testSwapCreationForAnotherStoreIsForbidden(): void
    {
        $repo = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $repo->expects($this->never())->method('save');
        $controller = new ShiftSwapRequestController($repo, new AuditLogger(), $this->permissions);

        $this->expectException(ForbiddenException::class);
        $controller->store($this->request(['store_id' => self::OTHER, 'requester_shift_id' => 4]));
    }
}
