<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftSwap\Controllers\Web;


use kintai\Bundles\Installed\ShiftSwap\Controllers\Web\EmployeeSwapController;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Bundles\Installed\ShiftSwap\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
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

/**
 * Régression : storeSwap/acceptSwap/refuseSwap écrivaient et relisaient les clés
 * 'target_id'/'peer_accepted_at', qui n'existent pas dans la table shift_swap_requests
 * (colonnes réelles : target_user_id/accepted_at). En production, storeSwap plantait
 * avec une erreur SQL "unknown column 'target_id'" à chaque tentative d'échange.
 */
final class EmployeeSwapControllerTest extends TestCase
{
    private ShiftRepositoryInterface&Stub $shifts;
    private ShiftSwapRequestRepositoryInterface&Stub $swapRequests;
    private StoreRepositoryInterface&Stub $stores;
    private StoreUserRepositoryInterface&Stub $storeUsers;
    private NotificationService&Stub $notifs;
    private ?EmployeeSwapController $controller = null;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-shift-swap-views';
        $this->ensureViewFile($viewDir, 'swaps');
        $this->ensureViewFile($viewDir, 'swaps-form');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $this->shifts       = $this->createStub(ShiftRepositoryInterface::class);
        $this->swapRequests = $this->createStub(ShiftSwapRequestRepositoryInterface::class);
        $this->stores       = $this->createStub(StoreRepositoryInterface::class);
        $this->storeUsers   = $this->createStub(StoreUserRepositoryInterface::class);
        $this->notifs       = $this->createStub(NotificationService::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): EmployeeSwapController
    {
        if ($this->controller === null) {
            $viewDir = sys_get_temp_dir() . '/kintai-shift-swap-views';
            $view = new ViewRenderer(sys_get_temp_dir());
            $view->addNamespace('shift-swap', $viewDir);
            $this->controller = new EmployeeSwapController(
                $view,
                $this->swapRequests,
                $this->shifts,
                $this->createStub(ShiftTypeRepositoryInterface::class),
                $this->stores,
                $this->storeUsers,
                $this->createStub(UserRepositoryInterface::class),
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
    }

    public function testStoreSwapSavesTargetUserIdAndAcceptedAtColumns(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(1)->willReturn([['store_id' => 5, 'user_id' => 1]]);
        $this->stores->expects($this->atLeastOnce())->method('getFeatures')->with(5)->willReturn([]);

        $_POST = [
            'requester_shift_id' => '10',
            'target_id'          => '2',
            'target_shift_id'    => '20',
            'reason'             => 'Test',
        ];

        $myShift     = ['id' => 10, 'user_id' => 1, 'store_id' => 5];
        $targetShift = ['id' => 20, 'user_id' => 2, 'store_id' => 5];
        $this->shifts->method('findById')->willReturnMap([
            [10, $myShift],
            [20, $targetShift],
        ]);

        $captured = null;
        $this->swapRequests->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d + ['id' => 42];
        });

        $this->notifs->expects($this->once())->method('notify')->with(2, 'swap_requested', $this->anything(), $this->anything(), 42);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $response = $this->controller()->storeSwap($req);

        $this->assertSame(302, $response->status());
        $this->assertArrayHasKey('target_user_id', $captured);
        $this->assertSame(2, $captured['target_user_id']);
        $this->assertArrayNotHasKey('target_id', $captured);
        $this->assertArrayHasKey('accepted_at', $captured);
        $this->assertNull($captured['accepted_at']);
        $this->assertArrayNotHasKey('peer_accepted_at', $captured);
    }

    /** Ouvrir la liste marque comme lues les notifications des échanges affichés (envoyés et reçus, sans doublon). */
    public function testSwapsMarksTheNotificationsOfTheDisplayedSwapsAsRead(): void
    {
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->swapRequests = $this->createStub(ShiftSwapRequestRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $this->storeUsers->method('findByUser')->willReturn([['store_id' => 5, 'user_id' => 1]]);
        $this->stores->method('getFeatures')->willReturn([]);
        $this->swapRequests->method('findByRequester')->willReturn([['id' => 3, 'created_at' => '2026-01-01'], ['id' => 4, 'created_at' => '2026-01-02']]);
        $this->swapRequests->method('findByTarget')->willReturn([['id' => 4, 'created_at' => '2026-01-02'], ['id' => 9, 'created_at' => '2026-01-03']]);
        $this->notifs->expects($this->once())->method('markReadFor')->with(1, 'swap', [4, 3, 9]);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->assertSame(200, $this->controller()->swaps($req)->status());
    }

    public function testStoreSwapRedirectsWhenFeatureDisabledForStore(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(1)->willReturn([['store_id' => 5, 'user_id' => 1]]);
        $this->stores->expects($this->atLeastOnce())->method('getFeatures')->with(5)->willReturn(['shifts']);

        $_POST = ['requester_shift_id' => '10', 'target_id' => '2', 'target_shift_id' => '20'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $response = $this->controller()->storeSwap($req);

        $this->assertSame(302, $response->status());
    }

    public function testAcceptSwapReadsAndWritesAcceptedAtColumn(): void
    {
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $swap = [
            'id' => 5, 'store_id' => 3, 'requester_id' => 1,
            'target_user_id' => 9, 'status' => 'pending', 'accepted_at' => null,
        ];
        $this->swapRequests->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn($swap);

        $captured = null;
        $this->swapRequests->expects($this->once())->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d;
        });

        $this->notifs->expects($this->once())->method('notify')->with(1, 'swap_peer_accepted', $this->anything(), $this->anything(), 5);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller()->acceptSwap($req);

        $this->assertSame(302, $response->status());
        $this->assertArrayHasKey('accepted_at', $captured);
        $this->assertNotNull($captured['accepted_at']);
    }

    public function testAcceptSwapForbiddenWhenCurrentUserIsNotTheTarget(): void
    {
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $swap = [
            'id' => 5, 'store_id' => 3, 'requester_id' => 1,
            'target_user_id' => 9, 'status' => 'pending', 'accepted_at' => null,
        ];
        $this->swapRequests->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn($swap);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '5']);

        $this->expectException(ForbiddenException::class);
        $this->controller()->acceptSwap($req);
    }

    public function testRefuseSwapReadsTargetUserIdColumn(): void
    {
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $swap = [
            'id' => 5, 'store_id' => 3, 'requester_id' => 1,
            'target_user_id' => 9, 'status' => 'pending', 'accepted_at' => null,
        ];
        $this->swapRequests->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn($swap);

        $captured = null;
        $this->swapRequests->expects($this->once())->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d;
        });

        $this->notifs->expects($this->once())->method('notify')->with(1, 'swap_peer_refused', $this->anything(), $this->anything(), 5);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller()->refuseSwap($req);

        $this->assertSame(302, $response->status());
        $this->assertSame('refused', $captured['status']);
    }

    public function testCancelSwapNotifiesTargetUser(): void
    {
        $this->swapRequests = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $swap = [
            'id' => 5, 'store_id' => 3, 'requester_id' => 1,
            'target_user_id' => 9, 'status' => 'pending', 'accepted_at' => null,
        ];
        $this->swapRequests->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn($swap);
        $this->swapRequests->expects($this->once())->method('save');

        $this->notifs->expects($this->once())->method('notify')->with(9, 'swap_cancelled', $this->anything(), $this->anything(), 5);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '5']);

        $response = $this->controller()->cancelSwap($req);

        $this->assertSame(302, $response->status());
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
}
