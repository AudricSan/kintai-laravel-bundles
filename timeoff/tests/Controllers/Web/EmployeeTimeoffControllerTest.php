<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\TimeOff\Controllers\Web;


use kintai\Bundles\Installed\TimeOff\Controllers\Web\EmployeeTimeoffController;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Bundles\Installed\TimeOff\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class EmployeeTimeoffControllerTest extends TestCase
{
    private TimeoffRequestRepositoryInterface&Stub $timeoffRequests;
    private StoreRepositoryInterface&Stub $stores;
    private StoreUserRepositoryInterface&Stub $storeUsers;
    private \kintai\Core\Services\NotificationService $notifs;
    private ?EmployeeTimeoffController $controller = null;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-timeoff-views';
        $this->ensureViewFile($viewDir, 'employee-timeoff');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $this->timeoffRequests = $this->createStub(TimeoffRequestRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->notifs = $this->createStub(\kintai\Core\Services\NotificationService::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): EmployeeTimeoffController
    {
        if ($this->controller === null) {
            $viewDir = sys_get_temp_dir() . '/kintai-timeoff-views';
            $view = new ViewRenderer(sys_get_temp_dir());
            $view->addNamespace('timeoff', $viewDir);
            $this->controller = new EmployeeTimeoffController(
                $view,
                $this->timeoffRequests,
                $this->stores,
                $this->storeUsers,
                new AuditLogger(),
                $this->createStub(\kintai\Core\Repositories\UserRepositoryInterface::class),
                $this->notifs,
                new \kintai\Core\Auth\PermissionService(
                    $this->createStub(\kintai\Core\Repositories\RoleAssignmentRepositoryInterface::class),
                    $this->createStub(\kintai\Core\Repositories\RoleRepositoryInterface::class),
                ),
            );
        }

        return $this->controller;
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    public function testTimeoffRendersListForEmployee(): void
    {
        $this->timeoffRequests = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(9)->willReturn([['store_id' => 1, 'user_id' => 9]]);
        $this->stores->expects($this->atLeastOnce())->method('getFeatures')->with(1)->willReturn([]);
        $this->timeoffRequests->expects($this->atLeastOnce())->method('findByUser')->with(9)->willReturn([]);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $response = $this->controller()->timeoff($req);

        $this->assertSame(200, $response->status());
    }

    /** Ouvrir la liste marque comme lues les notifications des demandes affichées. */
    public function testTimeoffMarksTheNotificationsOfTheDisplayedRequestsAsRead(): void
    {
        $this->timeoffRequests = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->notifs = $this->createMock(\kintai\Core\Services\NotificationService::class);
        $this->storeUsers->method('findByUser')->willReturn([['store_id' => 1, 'user_id' => 9]]);
        $this->stores->method('getFeatures')->willReturn([]);
        $this->timeoffRequests->method('findByUser')->with(9)->willReturn([
            ['id' => 4, 'created_at' => '2026-01-01'], ['id' => 7, 'created_at' => '2026-02-01'],
        ]);
        $this->notifs->expects($this->once())->method('markReadFor')->with(9, 'timeoff', [7, 4]);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $this->assertSame(200, $this->controller()->timeoff($req)->status());
    }

    public function testTimeoffRedirectsWhenFeatureDisabledForStore(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(9)->willReturn([['store_id' => 1, 'user_id' => 9]]);
        $this->stores->expects($this->atLeastOnce())->method('getFeatures')->with(1)->willReturn(['shifts', 'timeclock']);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $response = $this->controller()->timeoff($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreTimeoffCreatesPendingRequest(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(9)->willReturn([['store_id' => 1, 'user_id' => 9]]);
        $this->stores->expects($this->atLeastOnce())->method('getFeatures')->with(1)->willReturn([]);

        $_POST = ['type' => 'sick', 'start_date' => '2026-08-01', 'end_date' => '2026-08-02', 'reason' => 'Grippe'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $captured = null;
        $this->timeoffRequests->method('save')->willReturnCallback(function (array $d) use (&$captured) {
            $captured = $d;
            return $d + ['id' => 7];
        });

        $response = $this->controller()->storeTimeoff($req);

        $this->assertSame(302, $response->status());
        $this->assertNotNull($captured);
        $this->assertSame('pending', $captured['status']);
        $this->assertSame(9, $captured['user_id']);
        $this->assertSame(1, $captured['store_id']);
    }

    /** Audit du 13/10/2026 : les dates étaient enregistrées telles quelles, sans aucun contrôle. */
    public function testStoreTimeoffRejectsInvalidDates(): void
    {
        $this->timeoffRequests = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(9)->willReturn([['store_id' => 1, 'user_id' => 9]]);
        $this->stores->expects($this->atLeastOnce())->method('getFeatures')->with(1)->willReturn([]);
        $this->timeoffRequests->expects($this->never())->method('save');

        $_POST = ['type' => 'sick', 'start_date' => 'pas-une-date', 'end_date' => ''];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->controller()->storeTimeoff($req);
    }

    public function testStoreTimeoffRejectsEndBeforeStart(): void
    {
        $this->timeoffRequests = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(9)->willReturn([['store_id' => 1, 'user_id' => 9]]);
        $this->stores->expects($this->atLeastOnce())->method('getFeatures')->with(1)->willReturn([]);
        $this->timeoffRequests->expects($this->never())->method('save');

        $_POST = ['type' => 'sick', 'start_date' => '2026-08-05', 'end_date' => '2026-08-01'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->controller()->storeTimeoff($req);
    }

    public function testStoreTimeoffUsesTheChosenStoreWhenMember(): void
    {
        $this->timeoffRequests = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(9)->willReturn([
            ['store_id' => 1, 'user_id' => 9], ['store_id' => 4, 'user_id' => 9],
        ]);
        $this->stores->method('getFeatures')->willReturn([]);

        $_POST = ['type' => 'sick', 'start_date' => '2026-08-01', 'store_id' => '4'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $this->timeoffRequests->expects($this->once())->method('save')->with($this->callback(
            fn(array $d) => $d['store_id'] === 4 && $d['end_date'] === '2026-08-01'
        ))->willReturn(['id' => 7]);

        $this->assertSame(302, $this->controller()->storeTimeoff($req)->status());
    }

    public function testStoreTimeoffRefusesAStoreTheEmployeeDoesNotBelongTo(): void
    {
        $this->timeoffRequests = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(9)->willReturn([['store_id' => 1, 'user_id' => 9]]);
        $this->stores->method('getFeatures')->willReturn([]);
        $this->timeoffRequests->expects($this->never())->method('save');

        $_POST = ['type' => 'sick', 'start_date' => '2026-08-01', 'store_id' => '99'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->controller()->storeTimeoff($req);
    }

    public function testCancelTimeoffOnlyAllowsOwnerOfPendingRequest(): void
    {
        $this->timeoffRequests = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);
        $req->setRouteParams(['id' => '5']);

        $this->timeoffRequests->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn([
            'id' => 5, 'user_id' => 9, 'status' => 'pending',
        ]);
        $this->timeoffRequests->expects($this->once())->method('save')->willReturnCallback(fn(array $d) => $d);

        $response = $this->controller()->cancelTimeoff($req);

        $this->assertSame(302, $response->status());
    }

    public function testCancelTimeoffForbiddenForAnotherUsersRequest(): void
    {
        $this->timeoffRequests = $this->createMock(TimeoffRequestRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);
        $req->setRouteParams(['id' => '5']);

        $this->timeoffRequests->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn([
            'id' => 5, 'user_id' => 42, 'status' => 'pending',
        ]);

        $this->expectException(ForbiddenException::class);
        $this->controller()->cancelTimeoff($req);
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
