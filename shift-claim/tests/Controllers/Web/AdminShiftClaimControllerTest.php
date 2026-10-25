<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftClaim\Controllers\Web;


use kintai\Bundles\Installed\ShiftClaim\Controllers\Web\AdminShiftClaimController;
use kintai\Bundles\Installed\ShiftClaim\Repositories\ShiftClaimRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\NotificationService;
use kintai\Core\Services\ShiftServiceInterface;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class AdminShiftClaimControllerTest extends TestCase
{
    private ShiftRepositoryInterface&Stub $shifts;
    private ShiftTypeRepositoryInterface&Stub $shiftTypes;
    private StoreRepositoryInterface&Stub $stores;
    private StoreUserRepositoryInterface&Stub $storeUsers;
    private ShiftClaimRepositoryInterface&Stub $shiftClaims;
    private ShiftServiceInterface&Stub $shiftService;
    private NotificationService&Stub $notifs;
    private ?AdminShiftClaimController $controller = null;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-shift-claim-views';
        $this->ensureViewFile($viewDir, 'open-shifts');
        $this->ensureViewFile($viewDir, 'open-shifts-select');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $this->shifts = $this->createStub(ShiftRepositoryInterface::class);
        $this->shiftTypes = $this->createStub(ShiftTypeRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->shiftClaims = $this->createStub(ShiftClaimRepositoryInterface::class);
        $this->shiftService = $this->createStub(ShiftServiceInterface::class);
        $this->notifs = $this->createStub(NotificationService::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): AdminShiftClaimController
    {
        if ($this->controller === null) {
            $viewDir = sys_get_temp_dir() . '/kintai-shift-claim-views';
            $view = new ViewRenderer(sys_get_temp_dir());
            $view->addNamespace('shift-claim', $viewDir);
            $this->controller = new AdminShiftClaimController(
                $view,
                $this->createStub(UserRepositoryInterface::class),
                $this->stores,
                $this->storeUsers,
                $this->shifts,
                $this->shiftTypes,
                $this->shiftClaims,
                $this->shiftService,
                new AuditLogger(),
                $this->notifs,
            );
        }

        return $this->controller;
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];

        // La vue "open-shifts" est partagée avec EmployeeShiftClaimControllerTest (même
        // namespace de bundle) : on la remet à un stub vide pour ne pas polluer les tests
        // qui s'exécutent après ceux-ci dans le même process PHPUnit.
        $file = sys_get_temp_dir() . '/kintai-shift-claim-views/open-shifts.php';
        if (file_exists($file)) {
            file_put_contents($file, '');
        }
    }

    public function testOpenShiftsEnrichesEachShiftWithItsClaims(): void
    {
        $this->shiftClaims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $this->shifts->method('findOpen')->willReturn([
            ['id' => 1, 'shift_date' => '2026-08-01', 'store_id' => 5],
        ]);
        $this->shiftTypes->method('findAll')->willReturn([]);
        $this->stores->method('findAll')->willReturn([['id' => 5, 'name' => 'Store A']]);
        $this->shiftClaims->expects($this->atLeastOnce())->method('findByShift')->with(1)->willReturn([
            ['id' => 10, 'user_id' => 9, 'status' => 'pending'],
        ]);

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller()->openShifts($req);

        $this->assertSame(200, $response->status());
    }

    public function testOpenShiftsMarksTheNotificationsOfTheDisplayedShiftsAsRead(): void
    {
        $this->notifs = $this->createMock(NotificationService::class);
        $this->shiftClaims = $this->createStub(ShiftClaimRepositoryInterface::class);
        $this->shifts->method('findOpen')->willReturn([
            ['id' => 1, 'shift_date' => '2026-08-01', 'store_id' => 5],
            ['id' => 2, 'shift_date' => '2026-08-02', 'store_id' => 5],
        ]);
        $this->shiftTypes->method('findAll')->willReturn([]);
        $this->stores->method('findAll')->willReturn([['id' => 5, 'name' => 'Store A']]);
        $this->shiftClaims->method('findByShift')->willReturn([]);
        $this->notifs->expects($this->once())->method('markReadFor')->with(4, 'open_shift', [1, 2]);

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 4]);

        $this->assertSame(200, $this->controller()->openShifts($req)->status());
    }

    public function testSelectShiftToPublishListsOnlyUpcomingUnpublishedAssignedShifts(): void
    {
        $today = date('Y-m-d');
        $past  = date('Y-m-d', strtotime('-1 day'));
        $future = date('Y-m-d', strtotime('+1 day'));

        $this->shifts->method('findAll')->willReturn([
            ['id' => 1, 'shift_date' => $future, 'is_open' => 0, 'user_id' => 5, 'store_id' => 1],
            ['id' => 2, 'shift_date' => $past,   'is_open' => 0, 'user_id' => 5, 'store_id' => 1], // passé
            ['id' => 3, 'shift_date' => $future, 'is_open' => 1, 'user_id' => 5, 'store_id' => 1], // déjà publié
            ['id' => 4, 'shift_date' => $future, 'is_open' => 0, 'user_id' => null, 'store_id' => 1], // non assigné
        ]);

        $this->shiftService->method('getUsersMap')->willReturn([5 => 'Alice']);
        $this->stores->method('findAll')->willReturn([]);

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller()->selectShiftToPublish($req);

        $this->assertSame(200, $response->status());
    }

    public function testPublishShiftSetsIsOpen(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $shift = ['id' => 1, 'store_id' => 5, 'is_open' => 0];
        $this->shifts->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn($shift);

        $captured = null;
        $this->shifts->expects($this->once())->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d;
        });

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1']);

        $response = $this->controller()->publishShift($req);

        $this->assertSame(302, $response->status());
        $this->assertSame(1, $captured['is_open']);
    }

    public function testPublishShiftNotifiesEligibleStoreMembersExcludingHolder(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $shift = ['id' => 1, 'store_id' => 5, 'is_open' => 0, 'user_id' => 9];
        $this->shifts->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn($shift);
        $this->shifts->method('save')->willReturnCallback(fn(array $d) => $d);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByStore')->with(5)->willReturn([
            ['user_id' => 9], // le titulaire actuel du shift : ne doit pas être notifié de son propre shift
            ['user_id' => 20],
            ['user_id' => 21],
        ]);

        $this->notifs->expects($this->once())->method('notifyMany')
            ->with($this->callback(fn($ids) => !in_array(9, $ids, true) && in_array(20, $ids, true) && in_array(21, $ids, true)), 'open_shift_published', $this->anything(), $this->anything(), 1);

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1']);

        $this->controller()->publishShift($req);
    }

    public function testUnpublishShiftWithdrawsPendingClaims(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftClaims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $shift = ['id' => 1, 'store_id' => 5, 'is_open' => 1];
        $this->shifts->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn($shift);
        $this->shifts->method('save')->willReturnCallback(fn(array $d) => $d);

        $this->shiftClaims->expects($this->atLeastOnce())->method('findByShift')->with(1)->willReturn([
            ['id' => 10, 'user_id' => 7, 'status' => 'pending'],
            ['id' => 11, 'user_id' => 8, 'status' => 'withdrawn'],
        ]);
        $this->shiftClaims->expects($this->once())->method('save')
            ->with($this->callback(fn($d) => $d['id'] === 10 && $d['status'] === 'withdrawn'));
        $this->notifs->expects($this->once())->method('notify')->with(7, 'shift_claim_withdrawn', $this->anything(), $this->anything(), 1);

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1']);

        $response = $this->controller()->unpublishShift($req);

        $this->assertSame(302, $response->status());
    }

    public function testApproveShiftClaimAssignsShiftAndRejectsOtherPendingClaims(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftClaims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $claim = ['id' => 10, 'shift_id' => 1, 'user_id' => 9, 'status' => 'pending'];
        $shift = ['id' => 1, 'store_id' => 5, 'is_open' => 1];

        $this->shiftClaims->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn($claim);
        $this->shifts->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn($shift);
        $this->shiftClaims->expects($this->atLeastOnce())->method('findByShift')->with(1)->willReturn([
            $claim,
            ['id' => 11, 'user_id' => 3, 'status' => 'pending'],
        ]);

        $this->shiftClaims->expects($this->once())->method('approveIfPending')
            ->with(10, $this->anything(), 1)
            ->willReturn(array_merge($claim, ['status' => 'approved']));

        $closedShift = null;
        $this->shifts->expects($this->once())->method('closeOpenShiftTo')
            ->with(1, 9)
            ->willReturnCallback(function (int $id, int $userId) use (&$closedShift, $shift) {
                $closedShift = array_merge($shift, ['user_id' => $userId, 'is_open' => 0]);
                return $closedShift;
            });

        $savedClaims = [];
        $this->shiftClaims->method('save')->willReturnCallback(function (array $d) use (&$savedClaims) {
            $savedClaims[] = $d;
            return $d;
        });

        $notified = [];
        $this->notifs->method('notify')->willReturnCallback(function (int $userId, string $type) use (&$notified) {
            $notified[] = [$userId, $type];
        });

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '10']);

        $response = $this->controller()->approveShiftClaim($req);

        $this->assertSame(302, $response->status());
        $this->assertSame(9, $closedShift['user_id']);
        $this->assertSame(0, $closedShift['is_open']);
        // Seule la candidature #11 (l'autre pending) passe par save() : #10 est
        // gérée par approveIfPending(), pas par save().
        $this->assertCount(1, $savedClaims);
        $this->assertSame('rejected', $savedClaims[0]['status']);
        $this->assertContains([9, 'shift_claim_approved'], $notified);
        $this->assertContains([3, 'shift_claim_rejected'], $notified);
    }

    /** Shift cédé (audit n°8, B3) : à l'approbation, l'ancien titulaire est prévenu qu'il ne l'a plus. */
    public function testApprovingAHandedOverShiftNotifiesTheFormerHolder(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftClaims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $claim = ['id' => 10, 'shift_id' => 1, 'user_id' => 9, 'status' => 'pending'];
        $shift = ['id' => 1, 'store_id' => 5, 'is_open' => 1, 'user_id' => 4];

        $this->shiftClaims->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn($claim);
        $this->shifts->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn($shift);
        $this->shiftClaims->expects($this->atLeastOnce())->method('findByShift')->with(1)->willReturn([$claim]);
        $this->shiftClaims->method('approveIfPending')->willReturn(array_merge($claim, ['status' => 'approved']));
        $this->shifts->expects($this->atLeastOnce())->method('closeOpenShiftTo')->with(1, 9)->willReturn(array_merge($shift, ['user_id' => 9, 'is_open' => 0]));

        $notified = [];
        $this->notifs->method('notify')->willReturnCallback(function (int $userId, string $type) use (&$notified) {
            $notified[] = [$userId, $type];
        });

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '10']);

        $this->controller()->approveShiftClaim($req);

        $this->assertSame([[9, 'shift_claim_approved'], [4, 'shift_claim_handed_over']], $notified);
    }

    /**
     * Régression : deux admins approuvent deux candidatures différentes du même
     * shift en même temps. approveIfPending() modélise la perte de la course sur
     * la candidature elle-même (déjà résolue par l'autre admin entre la lecture
     * et l'écriture) — aucune écriture supplémentaire ne doit avoir lieu.
     */
    public function testApproveShiftClaimReturnsAlreadyResolvedWhenClaimLosesRace(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftClaims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $claim = ['id' => 10, 'shift_id' => 1, 'user_id' => 9, 'status' => 'pending'];
        $shift = ['id' => 1, 'store_id' => 5, 'is_open' => 1];

        $this->shiftClaims->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn($claim);
        $this->shifts->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn($shift);
        $this->shiftClaims->expects($this->once())->method('approveIfPending')->willReturn(null);
        $this->shifts->expects($this->never())->method('closeOpenShiftTo');
        $this->shiftClaims->expects($this->never())->method('save');
        $this->notifs->expects($this->never())->method('notify');

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '10']);

        $response = $this->controller()->approveShiftClaim($req);

        $this->assertSame(302, $response->status());
        $this->assertStringContainsString('error=already_resolved', $this->locationOf($response));
    }

    /**
     * Régression : la candidature #10 gagne sa propre course (approveIfPending
     * réussit) mais le shift a déjà été fermé entre-temps par l'approbation
     * concurrente d'une autre candidature — on doit annuler l'approbation de
     * #10 (la repasser en pending) plutôt que de la laisser "approved" alors
     * que le shift est attribué à quelqu'un d'autre.
     */
    public function testApproveShiftClaimRevertsApprovalWhenShiftAlreadyClosedByConcurrentApproval(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftClaims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $claim = ['id' => 10, 'shift_id' => 1, 'user_id' => 9, 'status' => 'pending'];
        $shift = ['id' => 1, 'store_id' => 5, 'is_open' => 1];

        $this->shiftClaims->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn($claim);
        $this->shifts->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn($shift);
        $this->shiftClaims->method('approveIfPending')->willReturn(array_merge($claim, ['status' => 'approved']));
        $this->shifts->expects($this->once())->method('closeOpenShiftTo')->with(1, 9)->willReturn(null);

        $reverted = null;
        $this->shiftClaims->expects($this->once())->method('save')->willReturnCallback(function (array $d) use (&$reverted) {
            $reverted = $d;
            return $d;
        });
        $this->notifs->expects($this->never())->method('notify');

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '10']);

        $response = $this->controller()->approveShiftClaim($req);

        $this->assertSame(302, $response->status());
        $this->assertStringContainsString('error=already_resolved', $this->locationOf($response));
        $this->assertSame('pending', $reverted['status']);
        $this->assertNull($reverted['resolved_at']);
        $this->assertNull($reverted['resolved_by']);
    }

    private function locationOf(\kintai\Core\Response $response): string
    {
        $headersRef = new \ReflectionProperty($response, 'headers');
        $headersRef->setAccessible(true);
        return $headersRef->getValue($response)['Location'] ?? '';
    }

    public function testRejectShiftClaimSetsRejectedStatus(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftClaims = $this->createMock(ShiftClaimRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $claim = ['id' => 10, 'shift_id' => 1, 'user_id' => 9, 'status' => 'pending'];
        $shift = ['id' => 1, 'store_id' => 5];

        $this->shiftClaims->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn($claim);
        $this->shifts->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn($shift);

        $captured = null;
        $this->shiftClaims->expects($this->once())->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d;
        });
        $this->notifs->expects($this->once())->method('notify')->with(9, 'shift_claim_rejected', $this->anything(), $this->anything(), 1);

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '10']);

        $response = $this->controller()->rejectShiftClaim($req);

        $this->assertSame(302, $response->status());
        $this->assertSame('rejected', $captured['status']);
    }

    public function testOpenShiftsFiltersShiftsByTypeWithoutAffectingClaimsList(): void
    {
        $this->writeOpenShiftsViewStub();

        $this->shifts->method('findOpen')->willReturn([
            ['id' => 1, 'shift_date' => '2026-08-01', 'store_id' => 5, 'shift_type_id' => 10],
            ['id' => 2, 'shift_date' => '2026-08-02', 'store_id' => 5, 'shift_type_id' => 20],
        ]);
        $this->shiftTypes->method('findAll')->willReturn([
            ['id' => 10, 'name' => 'Matin'],
            ['id' => 20, 'name' => 'Soir'],
        ]);
        $this->stores->method('findAll')->willReturn([['id' => 5, 'name' => 'Store A']]);
        $this->shiftClaims->method('findByShift')->willReturnMap([
            [1, [['id' => 100, 'user_id' => 9, 'status' => 'pending']]],
            [2, [['id' => 200, 'user_id' => 9, 'status' => 'approved']]],
        ]);

        $_GET = ['type' => '10'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller()->openShifts($req);
        $data = json_decode($response->body(), true);

        $this->assertSame([1], $data['shift_ids']);
        // Le filtre "type" du tableau des shifts ne doit pas restreindre le tableau des candidatures.
        $this->assertSame([100, 200], $data['claim_ids']);
    }

    public function testOpenShiftsFiltersClaimsByStatusWithoutAffectingShiftsList(): void
    {
        $this->writeOpenShiftsViewStub();

        $this->shifts->method('findOpen')->willReturn([
            ['id' => 1, 'shift_date' => '2026-08-01', 'store_id' => 5, 'shift_type_id' => 10],
            ['id' => 2, 'shift_date' => '2026-08-02', 'store_id' => 5, 'shift_type_id' => 20],
        ]);
        $this->shiftTypes->method('findAll')->willReturn([]);
        $this->stores->method('findAll')->willReturn([['id' => 5, 'name' => 'Store A']]);
        $this->shiftClaims->method('findByShift')->willReturnMap([
            [1, [['id' => 100, 'user_id' => 9, 'status' => 'pending']]],
            [2, [['id' => 200, 'user_id' => 9, 'status' => 'approved']]],
        ]);

        $_GET = ['claim_status' => 'approved'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller()->openShifts($req);
        $data = json_decode($response->body(), true);

        $this->assertSame([200], $data['claim_ids']);
        $this->assertSame([1, 2], $data['shift_ids']);
    }

    public function testOpenShiftsSortsShiftsByStoreNameIndependentlyFromClaims(): void
    {
        $this->writeOpenShiftsViewStub();

        $this->shifts->method('findOpen')->willReturn([
            ['id' => 1, 'shift_date' => '2026-08-01', 'store_id' => 1],
            ['id' => 2, 'shift_date' => '2026-08-02', 'store_id' => 2],
        ]);
        $this->shiftTypes->method('findAll')->willReturn([]);
        $this->stores->method('findAll')->willReturn([
            ['id' => 1, 'name' => 'Zoo Store'],
            ['id' => 2, 'name' => 'Alpha Store'],
        ]);
        $this->shiftClaims->method('findByShift')->willReturnMap([
            [1, [['id' => 100, 'user_id' => 9, 'status' => 'pending', 'claimed_at' => '2026-08-01 10:00:00']]],
            [2, [['id' => 200, 'user_id' => 9, 'status' => 'pending', 'claimed_at' => '2026-08-02 10:00:00']]],
        ]);

        $_GET = ['sort' => 'store_asc'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller()->openShifts($req);
        $data = json_decode($response->body(), true);

        // "Alpha Store" (store 2 → shift 2) doit passer avant "Zoo Store" (store 1 → shift 1).
        $this->assertSame([2, 1], $data['shift_ids']);
        // Le tri du tableau des shifts ne doit pas affecter l'ordre des candidatures.
        $this->assertSame([100, 200], $data['claim_ids']);
    }

    public function testOpenShiftsSortsClaimsByStoreNameIndependentlyFromShifts(): void
    {
        $this->writeOpenShiftsViewStub();

        $this->shifts->method('findOpen')->willReturn([
            ['id' => 1, 'shift_date' => '2026-08-01', 'store_id' => 1],
            ['id' => 2, 'shift_date' => '2026-08-02', 'store_id' => 2],
        ]);
        $this->shiftTypes->method('findAll')->willReturn([]);
        $this->stores->method('findAll')->willReturn([
            ['id' => 1, 'name' => 'Zoo Store'],
            ['id' => 2, 'name' => 'Alpha Store'],
        ]);
        $this->shiftClaims->method('findByShift')->willReturnMap([
            [1, [['id' => 100, 'user_id' => 9, 'status' => 'pending', 'claimed_at' => '2026-08-01 10:00:00']]],
            [2, [['id' => 200, 'user_id' => 9, 'status' => 'pending', 'claimed_at' => '2026-08-02 10:00:00']]],
        ]);

        $_GET = ['claim_sort' => 'store_asc'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller()->openShifts($req);
        $data = json_decode($response->body(), true);

        // Candidature #200 (store 2 → "Alpha Store") doit passer avant #100 (store 1 → "Zoo Store").
        $this->assertSame([200, 100], $data['claim_ids']);
        // Le tri des candidatures ne doit pas affecter l'ordre du tableau des shifts (date par défaut).
        $this->assertSame([1, 2], $data['shift_ids']);
    }

    private function writeOpenShiftsViewStub(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-shift-claim-views';
        $this->writeViewContent($viewDir, 'open-shifts', "<?php echo json_encode(['shift_ids' => array_map(fn(\$s) => \$s['id'], \$shifts), 'claim_ids' => array_map(fn(\$c) => \$c['id'], \$claims)]);");
        $this->writeViewContent(sys_get_temp_dir(), 'layout.app', "<?php echo \$content ?? '';");
    }

    private function ensureViewFile(string $dir, string $view): void
    {
        $file = $dir . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $parent = dirname($file);
        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }
        touch($file);
    }

    private function writeViewContent(string $dir, string $view, string $content): void
    {
        $file = $dir . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        $parent = dirname($file);
        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }
        file_put_contents($file, $content);
    }
}
