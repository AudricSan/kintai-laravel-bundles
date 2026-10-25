<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftClaim\Controllers\Api;

use kintai\Bundles\Installed\ShiftClaim\Controllers\Api\ShiftClaimController;
use kintai\Bundles\Installed\ShiftClaim\Repositories\ShiftClaimRepositoryInterface;
use kintai\Bundles\Installed\ShiftClaim\Services\ShiftClaimApproval;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\ValidationException;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\NotificationService;
use PHPUnit\Framework\TestCase;

/**
 * API cohérente avec le web (audit n°8 de Kintai) : une candidature ne vise qu'un shift publié du magasin indiqué, et
 * l'approuver attribue le shift (avant : statut changé, shift resté ouvert à son ancien titulaire).
 */
final class ApiClaimConsistencyTest extends TestCase
{
    private const MANAGER = 1;
    private const EMPLOYEE = 3;

    private PermissionService $permissions;
    private StoreUserRepositoryInterface $storeUsers;

    protected function setUp(): void
    {
        $assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $assignments->method('findByUser')->willReturnCallback(fn(int $u) => $u === self::MANAGER
            ? [['id' => 1, 'user_id' => self::MANAGER, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1]]
            : []);
        $roles = $this->createStub(RoleRepositoryInterface::class);
        $roles->method('findById')->willReturn(['id' => 2, 'is_system' => 0]);
        $roles->method('getPermissions')->willReturn(['open_shifts.approve', 'open_shifts.view']);
        $roles->method('getGlobalPermissionKeys')->willReturn([]);
        $this->permissions = new PermissionService($assignments, $roles);
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->storeUsers->method('findMembership')->willReturn(['store_id' => 1, 'user_id' => self::EMPLOYEE]);
    }

    private function request(int $caller, array $json, array $params = []): Request
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => $caller]);
        $req->setRouteParams($params);
        (new \ReflectionProperty(Request::class, 'jsonBody'))->setValue($req, $json);
        return $req;
    }

    /** @return array<string, array{array<string, mixed>|null}> */
    public static function unavailableShifts(): array
    {
        return [
            'inexistant'        => [null],
            'non publié'        => [['id' => 8, 'store_id' => 1, 'is_open' => 0, 'user_id' => null]],
            'autre magasin'     => [['id' => 8, 'store_id' => 2, 'is_open' => 1, 'user_id' => null]],
            'son propre shift'  => [['id' => 8, 'store_id' => 1, 'is_open' => 1, 'user_id' => self::EMPLOYEE]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unavailableShifts')]
    public function testAClaimNeedsAPublishedShiftOfThatStore(?array $shift): void
    {
        $claims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $claims->expects($this->never())->method('save');
        $shifts = $this->createStub(ShiftRepositoryInterface::class);
        $shifts->method('findById')->willReturn($shift);

        $this->expectException(ValidationException::class);
        (new ShiftClaimController($claims, $this->permissions, $this->storeUsers, $shifts))
            ->store($this->request(self::EMPLOYEE, ['store_id' => 1, 'shift_id' => 8]));
    }

    public function testApprovingThroughTheApiAssignsTheShift(): void
    {
        $claim = ['id' => 10, 'shift_id' => 8, 'user_id' => self::EMPLOYEE, 'store_id' => 1, 'status' => 'pending'];
        $shift = ['id' => 8, 'store_id' => 1, 'is_open' => 1, 'user_id' => 4];
        $claims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $claims->method('findById')->willReturn($claim);
        $claims->method('findByShift')->willReturn([$claim]);
        $claims->expects($this->once())->method('approveIfPending')->willReturn(['status' => 'approved'] + $claim);
        $shifts = $this->createMock(ShiftRepositoryInterface::class);
        $shifts->method('findById')->willReturn($shift);
        $shifts->expects($this->once())->method('closeOpenShiftTo')->with(8, self::EMPLOYEE)
            ->willReturn(['user_id' => self::EMPLOYEE, 'is_open' => 0] + $shift);
        $notifs = $this->createStub(NotificationService::class);
        $notified = [];
        $notifs->method('notify')->willReturnCallback(function (int $u, string $type) use (&$notified) {
            $notified[] = [$u, $type];
        });
        $approval = new ShiftClaimApproval($claims, $shifts, $this->createStub(StoreRepositoryInterface::class), $notifs);

        $response = (new ShiftClaimController($claims, $this->permissions, $this->storeUsers, $shifts, $approval))
            ->update($this->request(self::MANAGER, ['status' => 'approved'], ['id' => '10']));

        $this->assertSame(200, $response->status());
        $this->assertSame([[self::EMPLOYEE, 'shift_claim_approved'], [4, 'shift_claim_handed_over']], $notified);
    }

    public function testApprovingAClosedShiftIsAConflict(): void
    {
        $claim = ['id' => 10, 'shift_id' => 8, 'user_id' => self::EMPLOYEE, 'store_id' => 1, 'status' => 'pending'];
        $claims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $claims->method('findById')->willReturn($claim);
        $claims->expects($this->never())->method('approveIfPending');
        $shifts = $this->createStub(ShiftRepositoryInterface::class);
        $shifts->method('findById')->willReturn(['id' => 8, 'store_id' => 1, 'is_open' => 0, 'user_id' => 5]);
        $approval = new ShiftClaimApproval($claims, $shifts, $this->createStub(StoreRepositoryInterface::class),
            $this->createStub(NotificationService::class));

        $response = (new ShiftClaimController($claims, $this->permissions, $this->storeUsers, $shifts, $approval))
            ->update($this->request(self::MANAGER, ['status' => 'approved'], ['id' => '10']));

        $this->assertSame(409, $response->status());
    }
}
