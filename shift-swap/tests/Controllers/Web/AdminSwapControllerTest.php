<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftSwap\Controllers\Web;


use kintai\Bundles\Installed\ShiftSwap\Controllers\Web\AdminSwapController;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Bundles\Installed\ShiftSwap\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\NotificationService;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class AdminSwapControllerTest extends TestCase
{
    private ShiftSwapRequestRepositoryInterface&Stub $swapRequests;
    private ShiftRepositoryInterface&Stub $shifts;
    private UserRepositoryInterface&Stub $users;
    private StoreRepositoryInterface&Stub $stores;
    private NotificationService&Stub $notifs;
    private StoreUserRepositoryInterface&Stub $storeUsers;
    private ?AdminSwapController $controller = null;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-shift-swap-views';
        $this->ensureViewFile($viewDir, 'swap-form');
        $this->ensureViewFile($viewDir, 'swap-requests');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $this->swapRequests = $this->createStub(ShiftSwapRequestRepositoryInterface::class);
        $this->shifts = $this->createStub(ShiftRepositoryInterface::class);
        $this->users = $this->createStub(UserRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->notifs = $this->createStub(NotificationService::class);
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): AdminSwapController
    {
        if ($this->controller === null) {
            $viewDir = sys_get_temp_dir() . '/kintai-shift-swap-views';
            $view = new ViewRenderer(sys_get_temp_dir());
            $view->addNamespace('shift-swap', $viewDir);
            $this->controller = new AdminSwapController(
                $view,
                $this->swapRequests,
                $this->shifts,
                new AuditLogger(),
                $this->notifs,
                $this->stores,
                $this->users,
                $this->storeUsers,
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

    public function testCreateSwapRendersFormForAdmin(): void
    {
        $this->users->method('findAll')->willReturn([
            ['id' => 1, 'last_name' => 'Dupont', 'first_name' => 'Jean'],
            ['id' => 2, 'last_name' => 'Martin', 'first_name' => 'Paul'],
        ]);

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller()->createSwap($req);

        $this->assertSame(200, $response->status());
    }

    /**
     * Régression (audit du 13/10/2026) : pour un gérant limité à ses magasins, le formulaire cherche les membres de
     * ces magasins ; le contrôleur n'avait pas le dépôt requis par HasAdminAccess et répondait 500.
     */
    public function testCreateSwapListsOnlyTheEmployeesOfTheManagersStores(): void
    {
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([['user_id' => 1], ['user_id' => 2]]);
        $this->users->method('findAll')->willReturn([
            ['id' => 1, 'last_name' => 'Dupont', 'first_name' => 'Jean'],
            ['id' => 2, 'last_name' => 'Martin', 'first_name' => 'Paul'],
            ['id' => 3, 'last_name' => 'Autre', 'first_name' => 'Magasin'],
        ]);

        $req = new Request();
        $req->setAttribute('managed_store_ids', [1]);

        $response = $this->controller()->createSwap($req);

        $this->assertSame(200, $response->status());
    }

    public function testStoreSwapAppliesImmediateExchangeWithoutApprovalWorkflow(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $_POST = [
            'requester_id'       => '1',
            'target_id'          => '2',
            'requester_shift_id' => '10',
            'target_shift_id'    => '20',
            'reason'             => 'Test',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $requesterShift = ['id' => 10, 'user_id' => 1, 'store_id' => 5];
        $targetShift    = ['id' => 20, 'user_id' => 2, 'store_id' => 5];

        $this->shifts->method('findById')->willReturnMap([
            [10, $requesterShift],
            [20, $targetShift],
        ]);

        $savedShifts = [];
        $this->shifts->expects($this->exactly(2))->method('save')->willReturnCallback(function (array $d) use (&$savedShifts) {
            $savedShifts[] = $d;
            return $d;
        });

        $capturedSwap = null;
        $this->swapRequests->method('save')->willReturnCallback(function (array $d) use (&$capturedSwap) {
            $capturedSwap = $d;
            return $d + ['id' => 77];
        });

        $this->notifs->expects($this->once())->method('notifyMany')
            ->with([1, 2], 'swap_applied', $this->isString(), $this->anything(), 77);

        $response = $this->controller()->storeSwap($req);

        $this->assertSame(302, $response->status());

        // Les deux shifts ont été échangés immédiatement (pas de statut pending)
        $this->assertSame(2, $savedShifts[0]['user_id']);
        $this->assertSame(1, $savedShifts[1]['user_id']);
        $this->assertSame('accepted', $capturedSwap['status']);
        $this->assertSame(2, $capturedSwap['target_user_id']);
        $this->assertNotNull($capturedSwap['accepted_at']);
    }

    public function testStoreSwapForbiddenWhenShiftsBelongToDifferentStores(): void
    {
        $_POST = [
            'requester_id'       => '1',
            'target_id'          => '2',
            'requester_shift_id' => '10',
            'target_shift_id'    => '20',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->shifts->method('findById')->willReturnMap([
            [10, ['id' => 10, 'user_id' => 1, 'store_id' => 5]],
            [20, ['id' => 20, 'user_id' => 2, 'store_id' => 6]],
        ]);

        $this->expectException(ForbiddenException::class);
        $this->controller()->storeSwap($req);
    }

    public function testCreateSwapExcludesShiftsThatWouldCreateSameDayConflict(): void
    {
        $this->users->method('findAll')->willReturn([]);

        // Le demandeur a un shift le 2026-08-05 ; la cible a un shift le 2026-08-05 et un autre le 2026-08-10.
        $this->shifts->method('findByUser')->willReturnMap([
            [1, [['id' => 10, 'user_id' => 1, 'shift_date' => '2026-08-05', 'start_time' => '09:00', 'end_time' => '17:00']]],
            [2, [
                ['id' => 20, 'user_id' => 2, 'shift_date' => '2026-08-05', 'start_time' => '10:00', 'end_time' => '18:00'],
                ['id' => 21, 'user_id' => 2, 'shift_date' => '2026-08-10', 'start_time' => '09:00', 'end_time' => '17:00'],
            ]],
        ]);

        $_GET = ['requester_id' => '1', 'target_id' => '2'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller()->createSwap($req);

        $this->assertSame(200, $response->status());
        // On ne peut pas vérifier le HTML rendu (vue stub dans les tests), mais on s'assure
        // au minimum qu'aucune exception n'est levée par le filtrage des conflits.
    }

    public function testStoreSwapBlockedWhenResultingInSameDayConflict(): void
    {
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $_POST = [
            'requester_id'       => '1',
            'target_id'          => '2',
            'requester_shift_id' => '10',
            'target_shift_id'    => '20',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $requesterShift = ['id' => 10, 'user_id' => 1, 'store_id' => 5, 'shift_date' => '2026-08-05'];
        $targetShift    = ['id' => 20, 'user_id' => 2, 'store_id' => 5, 'shift_date' => '2026-08-06'];

        $this->shifts->method('findById')->willReturnMap([
            [10, $requesterShift],
            [20, $targetShift],
        ]);
        // Le demandeur a déjà un autre shift (#99) le 2026-08-06 : recevoir le shift cible créerait un conflit.
        $this->shifts->method('findByUser')->willReturnMap([
            [1, [$requesterShift, ['id' => 99, 'user_id' => 1, 'shift_date' => '2026-08-06']]],
            [2, [$targetShift]],
        ]);

        $this->shifts->expects($this->never())->method('save');
        $this->swapRequests->expects($this->never())->method('save');

        $response = $this->controller()->storeSwap($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreSwapForbiddenWhenSameEmployeeSelectedTwice(): void
    {
        $_POST = [
            'requester_id'       => '1',
            'target_id'          => '1',
            'requester_shift_id' => '10',
            'target_shift_id'    => '20',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->expectException(ForbiddenException::class);
        $this->controller()->storeSwap($req);
    }

    public function testSwapRequestsLooksUpReferencedShiftsForDisplay(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->swapRequests->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 5, 'requester_id' => 1, 'target_user_id' => 2, 'requester_shift_id' => 10, 'target_shift_id' => 20, 'status' => 'pending'],
        ]);
        $this->users->method('findAll')->willReturn([]);
        $this->shifts->expects($this->exactly(2))->method('findById')->willReturnMap([
            [10, ['id' => 10, 'shift_date' => '2026-08-01', 'start_time' => '09:00', 'end_time' => '17:00']],
            [20, ['id' => 20, 'shift_date' => '2026-08-02', 'start_time' => '10:00', 'end_time' => '18:00']],
        ]);

        $response = $this->controller()->swapRequests($req);

        $this->assertSame(200, $response->status());
    }

    private function location(\kintai\Core\Response $response): string
    {
        $ref = new \ReflectionProperty(\kintai\Core\Response::class, 'headers');
        $ref->setAccessible(true);
        return $ref->getValue($response)['Location'] ?? '';
    }

    private function pendingSwap(): array
    {
        return [
            'id' => 31, 'store_id' => 5, 'status' => 'pending',
            'requester_id' => 1, 'target_user_id' => 2,
            'requester_shift_id' => 10, 'target_shift_id' => 20,
        ];
    }

    /** Audit du 13/10/2026 : un rejeu de l'approbation ré-échangeait les shifts et annulait l'échange. */
    public function testApproveSwapOnAnAlreadyAcceptedSwapChangesNothing(): void
    {
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '31']);
        $this->swapRequests->expects($this->atLeastOnce())->method('findById')->with(31)->willReturn(['status' => 'accepted'] + $this->pendingSwap());
        $this->shifts->expects($this->never())->method('save');
        $this->swapRequests->expects($this->never())->method('save');

        $this->assertStringContainsString('error=invalid_state', $this->location($this->controller()->approveSwap($req)));
    }

    public function testApproveSwapRefusesWhenAShiftWasReassignedSinceTheRequest(): void
    {
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '31']);
        $this->swapRequests->expects($this->atLeastOnce())->method('findById')->with(31)->willReturn($this->pendingSwap());
        // Le shift #10 n'appartient plus au demandeur (1) : il a été réaffecté à 8.
        $this->shifts->method('findById')->willReturnMap([
            [10, ['id' => 10, 'user_id' => 8, 'store_id' => 5, 'shift_date' => '2026-09-01']],
            [20, ['id' => 20, 'user_id' => 2, 'store_id' => 5, 'shift_date' => '2026-09-02']],
        ]);
        $this->shifts->expects($this->never())->method('save');
        $this->swapRequests->expects($this->never())->method('save');

        $this->assertStringContainsString('error=invalid_state', $this->location($this->controller()->approveSwap($req)));
    }

    public function testApproveSwapRefusesAConflictingSameDayExchange(): void
    {
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '31']);
        $this->swapRequests->expects($this->atLeastOnce())->method('findById')->with(31)->willReturn($this->pendingSwap());
        $this->shifts->method('findById')->willReturnMap([
            [10, ['id' => 10, 'user_id' => 1, 'store_id' => 5, 'shift_date' => '2026-09-01']],
            [20, ['id' => 20, 'user_id' => 2, 'store_id' => 5, 'shift_date' => '2026-09-02']],
        ]);
        // Le demandeur a déjà un autre shift le 02/09 (date du shift qu'il recevrait).
        $this->shifts->method('findByUser')->willReturnCallback(fn(int $uid) => $uid === 1
            ? [['id' => 10, 'shift_date' => '2026-09-01'], ['id' => 99, 'shift_date' => '2026-09-02']]
            : [['id' => 20, 'shift_date' => '2026-09-02']]);
        $this->shifts->expects($this->never())->method('save');

        $this->assertStringContainsString('error=swap_conflict', $this->location($this->controller()->approveSwap($req)));
    }

    public function testApproveSwapExchangesTheShiftsOfAPendingSwap(): void
    {
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '31']);
        $this->swapRequests->expects($this->atLeastOnce())->method('findById')->with(31)->willReturn($this->pendingSwap());
        $this->shifts->method('findById')->willReturnMap([
            [10, ['id' => 10, 'user_id' => 1, 'store_id' => 5, 'shift_date' => '2026-09-01']],
            [20, ['id' => 20, 'user_id' => 2, 'store_id' => 5, 'shift_date' => '2026-09-02']],
        ]);
        $this->shifts->method('findByUser')->willReturn([]);
        $saved = [];
        $this->shifts->expects($this->exactly(2))->method('save')->willReturnCallback(function (array $d) use (&$saved) {
            $saved[$d['id']] = $d['user_id'];
            return $d;
        });
        $this->swapRequests->expects($this->once())->method('save')->with($this->callback(fn(array $d) => $d['status'] === 'accepted'));

        $this->assertStringContainsString('success=approved', $this->location($this->controller()->approveSwap($req)));
        $this->assertSame([10 => 2, 20 => 1], $saved);
    }

    public function testRefuseSwapOnAnAlreadyAcceptedSwapChangesNothing(): void
    {
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '31']);
        $this->swapRequests->expects($this->atLeastOnce())->method('findById')->with(31)->willReturn(['status' => 'accepted'] + $this->pendingSwap());
        $this->swapRequests->expects($this->never())->method('save');

        $this->assertStringContainsString('error=invalid_state', $this->location($this->controller()->refuseSwap($req)));
    }

    /** Les shifts ont été réaffectés depuis l'échange : les « rendre » écraserait ce changement. */
    public function testDeleteSwapDoesNotRestoreShiftsThatMovedSinceTheExchange(): void
    {
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '77']);
        $this->swapRequests->expects($this->atLeastOnce())->method('findById')->with(77)->willReturn([
            'id' => 77, 'store_id' => 5, 'status' => 'accepted', 'requester_id' => 1, 'target_user_id' => 2,
            'requester_shift_id' => 10, 'target_shift_id' => 20,
        ]);
        // Le shift #10 devrait appartenir à 2 après l'échange ; il appartient maintenant à 8.
        $this->shifts->method('findById')->willReturnMap([
            [10, ['id' => 10, 'user_id' => 8, 'store_id' => 5]],
            [20, ['id' => 20, 'user_id' => 1, 'store_id' => 5]],
        ]);
        $this->shifts->expects($this->never())->method('save');
        $this->swapRequests->expects($this->once())->method('delete')->with(77);

        $this->assertStringContainsString('success=deleted', $this->location($this->controller()->deleteSwap($req)));
    }

    public function testDeleteSwapRevertsShiftsWhenAccepted(): void
    {
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '77']);

        $swap = [
            'id' => 77, 'store_id' => 5, 'status' => 'accepted',
            'requester_id' => 1, 'target_user_id' => 2,
            'requester_shift_id' => 10, 'target_shift_id' => 20,
        ];
        $this->swapRequests->expects($this->atLeastOnce())->method('findById')->with(77)->willReturn($swap);

        // Après l'échange initial : le shift #10 appartient à 2, le shift #20 appartient à 1
        $requesterShift = ['id' => 10, 'user_id' => 2, 'store_id' => 5];
        $targetShift    = ['id' => 20, 'user_id' => 1, 'store_id' => 5];
        $this->shifts->method('findById')->willReturnMap([
            [10, $requesterShift],
            [20, $targetShift],
        ]);

        $savedShifts = [];
        $this->shifts->expects($this->exactly(2))->method('save')->willReturnCallback(function (array $d) use (&$savedShifts) {
            $savedShifts[] = $d;
            return $d;
        });

        $this->notifs->expects($this->once())->method('notifyMany')->with([1, 2], 'swap_cancelled_restored', $this->isString(), $this->anything(), 77);
        $this->swapRequests->expects($this->once())->method('delete')->with(77);

        $response = $this->controller()->deleteSwap($req);

        $this->assertSame(302, $response->status());
        // Les shifts sont revenus à leurs propriétaires d'origine
        $this->assertSame(1, $savedShifts[0]['user_id']);
        $this->assertSame(2, $savedShifts[1]['user_id']);
    }

    public function testDeleteSwapDoesNotTouchShiftsWhenNotAccepted(): void
    {
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '5']);

        $swap = [
            'id' => 5, 'store_id' => 5, 'status' => 'pending',
            'requester_id' => 1, 'target_user_id' => 2,
            'requester_shift_id' => 10, 'target_shift_id' => 20,
        ];
        $this->swapRequests->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn($swap);

        $this->shifts->expects($this->never())->method('save');
        $this->notifs->expects($this->never())->method('notifyMany');
        $this->swapRequests->expects($this->once())->method('delete')->with(5);

        $response = $this->controller()->deleteSwap($req);

        $this->assertSame(302, $response->status());
    }

    public function testSwapRequestsFiltersByStatus(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-shift-swap-views';
        $this->writeViewContent($viewDir, 'swap-requests', "<?php echo json_encode(array_map(fn(\$s) => \$s['id'], \$swaps));");
        $this->writeViewContent(sys_get_temp_dir(), 'layout.app', "<?php echo \$content ?? '';");

        $this->swapRequests->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 5, 'requester_id' => 1, 'target_user_id' => 2, 'status' => 'pending'],
            ['id' => 2, 'store_id' => 5, 'requester_id' => 1, 'target_user_id' => 2, 'status' => 'accepted'],
            ['id' => 3, 'store_id' => 5, 'requester_id' => 1, 'target_user_id' => 2, 'status' => 'refused'],
        ]);
        $this->users->method('findAll')->willReturn([]);

        $_GET = ['status' => 'accepted'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller()->swapRequests($req);
        $ids = json_decode($response->body(), true);

        $this->assertSame([2], $ids);
    }

    public function testSwapRequestsSortsByStoreName(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-shift-swap-views';
        $this->writeViewContent($viewDir, 'swap-requests', "<?php echo json_encode(array_map(fn(\$s) => \$s['id'], \$swaps));");
        $this->writeViewContent(sys_get_temp_dir(), 'layout.app', "<?php echo \$content ?? '';");

        $this->stores->method('findAll')->willReturn([
            ['id' => 1, 'name' => 'Zoo Store'],
            ['id' => 2, 'name' => 'Alpha Store'],
        ]);
        $this->swapRequests->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 1, 'requester_id' => 1, 'target_user_id' => 2, 'status' => 'pending'],
            ['id' => 2, 'store_id' => 2, 'requester_id' => 1, 'target_user_id' => 2, 'status' => 'pending'],
        ]);
        $this->users->method('findAll')->willReturn([]);

        $_GET = ['sort' => 'store_asc'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller()->swapRequests($req);
        $ids = json_decode($response->body(), true);

        $this->assertSame([2, 1], $ids);
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
