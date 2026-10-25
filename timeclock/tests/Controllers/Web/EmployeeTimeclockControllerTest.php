<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Timeclock\Controllers\Web;


use kintai\Bundles\Installed\Timeclock\Controllers\Web\EmployeeTimeclockController;
use kintai\Core\Container;
use kintai\Core\Exceptions\ConflictException;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Bundles\Installed\Timeclock\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class EmployeeTimeclockControllerTest extends TestCase
{
    private TimeclockRepositoryInterface&Stub $timeclocks;
    private StoreUserRepositoryInterface&Stub $storeUsers;
    private StoreRepositoryInterface&Stub $stores;
    private LogRepositoryInterface&Stub $logRepo;
    private ?EmployeeTimeclockController $controller = null;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-timeclock-views';
        $this->ensureViewFile($viewDir, 'employee-timeclock');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $this->timeclocks = $this->createStub(TimeclockRepositoryInterface::class);
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);

        $this->logRepo = $this->createStub(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): EmployeeTimeclockController
    {
        if ($this->controller === null) {
            $viewDir = sys_get_temp_dir() . '/kintai-timeclock-views';
            $view = new ViewRenderer(sys_get_temp_dir());
            $view->addNamespace('timeclock', $viewDir);
            $this->controller = new EmployeeTimeclockController(
                $view,
                $this->timeclocks,
                $this->storeUsers,
                $this->stores,
                new AuditLogger(),
            );
        }

        return $this->controller;
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
        Log::reset();
    }

    public function testTimeclockRendersWhenFeatureEnabled(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->timeclocks->expects($this->atLeastOnce())->method('findActiveByUser')->with(1)->willReturn(null);
        $this->timeclocks->method('findByUserAndDate')->willReturn([]);
        $this->timeclocks->expects($this->atLeastOnce())->method('findByUser')->with(1)->willReturn([]);

        $response = $this->controller()->timeclock($req);

        $this->assertSame(200, $response->status());
    }

    public function testTimeclockRedirectsWhenFeatureDisabled(): void
    {
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->stores->expects($this->atLeastOnce())->method('getFeatures')->with(5)->willReturn(['shifts']);

        $response = $this->controller()->timeclock($req);

        $this->assertSame(302, $response->status());
    }

    public function testClockInCreatesEntry(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->timeclocks->expects($this->atLeastOnce())->method('findActiveByUser')->with(1)->willReturn(null);
        $this->timeclocks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['user_id'] === 1 && $data['store_id'] === 5 && $data['clock_out_time'] === null
        ))->willReturn(['id' => 10]);

        $response = $this->controller()->clockIn($req);

        $this->assertSame(201, $response->status());
    }

    /** Un employé multi-magasins pointe sur le magasin qu'il choisit (avant : toujours le premier). */
    public function testClockInUsesTheChosenStoreWhenMember(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $_POST['store_id'] = '7';
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(1)->willReturn([['store_id' => 5], ['store_id' => 7]]);
        $this->timeclocks->expects($this->atLeastOnce())->method('findActiveByUser')->with(1)->willReturn(null);
        $this->timeclocks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['store_id'] === 7
        ))->willReturn(['id' => 10]);

        $this->assertSame(201, $this->controller()->clockIn($req)->status());
    }

    public function testClockInRefusesAStoreTheEmployeeDoesNotBelongTo(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $_POST['store_id'] = '99';
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->timeclocks->expects($this->atLeastOnce())->method('findActiveByUser')->with(1)->willReturn(null);
        $this->timeclocks->expects($this->never())->method('save');

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->controller()->clockIn($req);
    }

    /** Avant : la ligne était créée avec store_id = 0 pour un employé sans magasin. */
    public function testClockInRefusesAnEmployeeWithoutAnyStore(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(1)->willReturn([]);
        $this->timeclocks->expects($this->atLeastOnce())->method('findActiveByUser')->with(1)->willReturn(null);
        $this->timeclocks->expects($this->never())->method('save');

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->controller()->clockIn($req);
    }

    public function testClockInRejectsWhenAlreadyActive(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->timeclocks->expects($this->atLeastOnce())->method('findActiveByUser')->with(1)->willReturn(['id' => 9]);
        $this->timeclocks->expects($this->never())->method('save');

        $this->expectException(ConflictException::class);
        $this->controller()->clockIn($req);
    }

    public function testClockOutComputesDuration(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->timeclocks->expects($this->atLeastOnce())->method('findActiveByUser')->with(1)->willReturn([
            'id' => 9, 'store_id' => 5, 'clock_in_time' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
        ]);
        $this->timeclocks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['id'] === 9 && $data['duration_minutes'] >= 29 && $data['duration_minutes'] <= 31
        ))->willReturn(['id' => 9]);

        $response = $this->controller()->clockOut($req);

        $this->assertSame(200, $response->status());
    }

    public function testClockOutThrowsWhenNoActiveEntry(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->timeclocks->expects($this->atLeastOnce())->method('findActiveByUser')->with(1)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller()->clockOut($req);
    }

    /** Un pointage synchronisé après coup (hors-ligne) doit garder l'heure réelle du clic, pas l'heure de synchro. */
    public function testClockInUsesClientTimeWhenWithinTrustWindow(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $clientTime = (new \DateTimeImmutable('-2 hours'))->format('c');
        $_POST['client_time'] = $clientTime;

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->timeclocks->expects($this->atLeastOnce())->method('findActiveByUser')->with(1)->willReturn(null);

        $expected = (new \DateTimeImmutable($clientTime))->format('Y-m-d H:i:s');
        $this->timeclocks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['clock_in_time'] === $expected && $data['shift_date'] === substr($expected, 0, 10)
        ))->willReturn(['id' => 10]);

        $response = $this->controller()->clockIn($req);

        $this->assertSame(201, $response->status());
    }

    /** Un client_time trop ancien (dérive excessive) est ignoré au profit de l'heure serveur. */
    public function testClockInIgnoresClientTimeOutsideTrustWindow(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $_POST['client_time'] = (new \DateTimeImmutable('-2 days'))->format('c');

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(1)->willReturn([['store_id' => 5]]);
        $this->timeclocks->expects($this->atLeastOnce())->method('findActiveByUser')->with(1)->willReturn(null);

        $today = date('Y-m-d');
        $this->timeclocks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['shift_date'] === $today
        ))->willReturn(['id' => 10]);

        $this->controller()->clockIn($req);
    }

    /** La durée d'un pointage hors-ligne se calcule sur l'heure réelle de sortie, pas l'heure de synchro. */
    public function testClockOutUsesClientTimeForDuration(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $_POST['client_time'] = (new \DateTimeImmutable('-30 minutes'))->format('c');

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->timeclocks->expects($this->atLeastOnce())->method('findActiveByUser')->with(1)->willReturn([
            'id' => 9, 'store_id' => 5, 'clock_in_time' => (new \DateTimeImmutable('-90 minutes'))->format('Y-m-d H:i:s'),
        ]);
        $this->timeclocks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['duration_minutes'] >= 59 && $data['duration_minutes'] <= 61
        ))->willReturn(['id' => 9]);

        $response = $this->controller()->clockOut($req);

        $this->assertSame(200, $response->status());
    }

    /** Une sortie hors-ligne rejouée avant son entrée (désordre de synchro) retombe sur l'heure serveur. */
    public function testClockOutFallsBackToServerTimeWhenClientTimeBeforeClockIn(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $_POST['client_time'] = (new \DateTimeImmutable('-2 hours'))->format('c');

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);

        $this->timeclocks->expects($this->atLeastOnce())->method('findActiveByUser')->with(1)->willReturn([
            'id' => 9, 'store_id' => 5, 'clock_in_time' => (new \DateTimeImmutable('-30 minutes'))->format('Y-m-d H:i:s'),
        ]);
        $this->timeclocks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['duration_minutes'] >= 0
        ))->willReturn(['id' => 9]);

        $this->controller()->clockOut($req);
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
