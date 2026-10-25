<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Timeclock\Controllers\Web;


use kintai\Bundles\Installed\Timeclock\Controllers\Web\AdminTimeclockController;
use kintai\Core\Container;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Bundles\Installed\Timeclock\Repositories\TimeclockRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class AdminTimeclockControllerTest extends TestCase
{
    private TimeclockRepositoryInterface&Stub $timeclocks;
    private StoreRepositoryInterface&Stub $stores;
    private UserRepositoryInterface&Stub $users;
    private LogRepositoryInterface&Stub $logRepo;
    private ?AdminTimeclockController $controller = null;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-timeclock-views';
        $this->ensureViewFile($viewDir, 'timeclocks');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $this->timeclocks = $this->createStub(TimeclockRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->users = $this->createStub(UserRepositoryInterface::class);

        $this->logRepo = $this->createStub(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): AdminTimeclockController
    {
        if ($this->controller === null) {
            $viewDir = sys_get_temp_dir() . '/kintai-timeclock-views';
            $view = new ViewRenderer(sys_get_temp_dir());
            $view->addNamespace('timeclock', $viewDir);
            $this->controller = new AdminTimeclockController(
                $view,
                $this->timeclocks,
                new AuditLogger(),
                $this->stores,
                $this->users,
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

    public function testTimeclocksListsEntriesForFilterDate(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $_GET = ['date' => '2026-08-01'];

        $this->users->method('findAll')->willReturn([]);
        $this->stores->method('findAll')->willReturn([['id' => 5, 'name' => 'Store A']]);
        $this->timeclocks->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 5, 'shift_date' => '2026-08-01', 'clock_in_time' => '2026-08-01 09:00:00'],
            ['id' => 2, 'store_id' => 5, 'shift_date' => '2026-08-02', 'clock_in_time' => '2026-08-02 09:00:00'],
        ]);

        $response = $this->controller()->timeclocks($req);

        $this->assertSame(200, $response->status());
    }

    /**
     * Régression (16/09/2026) : un pointage réel n'apparaissait jamais sur /admin/timeclocks
     * car le filtre date valait date('Y-m-d') (aujourd'hui) par défaut au lieu d'un filtre vide
     * — impossible de voir un pointage passé sans deviner la bonne date dans le champ.
     */
    public function testTimeclocksWithoutDateFilterReturnsAllEntriesMostRecentFirst(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-timeclock-views';
        $this->writeViewContent($viewDir, 'timeclocks', "<?php echo json_encode(['total' => \$total, 'ids' => array_column(\$entries, 'id')]);");
        $this->writeViewContent(sys_get_temp_dir(), 'layout.app', "<?php echo \$content ?? '';");

        $_GET = [];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->users->method('findAll')->willReturn([]);
        $this->stores->method('findAll')->willReturn([['id' => 5, 'name' => 'Store A']]);
        $this->timeclocks->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 5, 'shift_date' => '2026-08-01', 'clock_in_time' => '2026-08-01 09:00:00'],
            ['id' => 2, 'store_id' => 5, 'shift_date' => '2026-09-15', 'clock_in_time' => '2026-09-15 13:52:46'],
        ]);

        $response = $this->controller()->timeclocks($req);
        $result   = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertSame(2, $result['total']);
        $this->assertSame([2, 1], $result['ids']);
    }

    public function testTimeclocksPaginatesTo20PerPage(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-timeclock-views';
        $this->writeViewContent($viewDir, 'timeclocks', "<?php echo json_encode(['total' => \$total, 'total_pages' => \$total_pages, 'count' => count(\$entries)]);");
        $this->writeViewContent(sys_get_temp_dir(), 'layout.app', "<?php echo \$content ?? '';");

        $_GET = [];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->users->method('findAll')->willReturn([]);
        $this->stores->method('findAll')->willReturn([['id' => 5, 'name' => 'Store A']]);

        $all = [];
        for ($i = 1; $i <= 25; $i++) {
            $all[] = ['id' => $i, 'store_id' => 5, 'shift_date' => '2026-09-01', 'clock_in_time' => sprintf('2026-09-01 %02d:00:00', $i % 24)];
        }
        $this->timeclocks->method('findAll')->willReturn($all);

        $response = $this->controller()->timeclocks($req);
        $result   = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertSame(25, $result['total']);
        $this->assertSame(2, $result['total_pages']);
        $this->assertSame(20, $result['count']);
    }

    /**
     * Régression (16/09/2026) : filtrer par store_id passait directement à
     * findByStoreAndDate() sans vérifier managedIds — un manager restreint au
     * store 5 pouvait lire les pointages du store 9 en changeant l'URL.
     */
    public function testTimeclocksStoreFilterIsRestrictedToManagedStores(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-timeclock-views';
        $this->writeViewContent($viewDir, 'timeclocks', "<?php echo json_encode(['total' => \$total]);");
        $this->writeViewContent(sys_get_temp_dir(), 'layout.app', "<?php echo \$content ?? '';");

        $_GET = ['store_id' => '9'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', [5]);

        $this->users->method('findAll')->willReturn([]);
        $this->stores->method('findAll')->willReturn([
            ['id' => 5, 'name' => 'Store A'],
            ['id' => 9, 'name' => 'Store B'],
        ]);
        $this->timeclocks->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 5, 'shift_date' => '2026-08-01', 'clock_in_time' => '2026-08-01 09:00:00'],
            ['id' => 2, 'store_id' => 9, 'shift_date' => '2026-08-01', 'clock_in_time' => '2026-08-01 09:00:00'],
        ]);

        $response = $this->controller()->timeclocks($req);
        $result   = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertSame(0, $result['total']);
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

    public function testTimeclocksEditRecomputesDuration(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $_POST = ['clock_in_time' => '2026-08-01 09:00:00', 'clock_out_time' => '2026-08-01 17:00:00'];
        $req = new Request();
        $req->setRouteParams(['id' => '10']);

        $this->timeclocks->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn([
            'id' => 10, 'store_id' => 5, 'clock_in_time' => '2026-08-01 09:00:00', 'clock_out_time' => null,
        ]);
        $this->timeclocks->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['duration_minutes'] === 480
        ));

        $response = $this->controller()->timeclocksEdit($req);

        $this->assertSame(302, $response->status());
    }

    public function testTimeclocksEditThrowsNotFoundForMissingEntry(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $req = new Request();
        $req->setRouteParams(['id' => '99']);

        $this->timeclocks->expects($this->atLeastOnce())->method('findById')->with(99)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller()->timeclocksEdit($req);
    }

    public function testTimeclocksDeleteDeletesAndLogs(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $req = new Request();
        $req->setRouteParams(['id' => '10']);

        $this->timeclocks->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 5]);
        $this->timeclocks->expects($this->once())->method('delete')->with(10);

        $response = $this->controller()->timeclocksDelete($req);

        $this->assertSame(302, $response->status());
    }

    /**
     * Régression (audit RBAC du 11/09/2026) : timeclocksEdit()/timeclocksDelete()
     * n'appelaient jamais assertStoreAccess() — un manager restreint au store 5
     * pouvait éditer/supprimer un pointage du store 9.
     */
    public function testTimeclocksEditRejectsEntryFromUnmanagedStore(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $_POST = ['clock_in_time' => '2026-08-01 09:00:00'];
        $req = new Request();
        $req->setRouteParams(['id' => '10']);
        $req->setAttribute('managed_store_ids', [5]);

        $this->timeclocks->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 9]);
        $this->timeclocks->expects($this->never())->method('save');

        $this->expectException(\kintai\Core\Exceptions\ForbiddenException::class);
        $this->controller()->timeclocksEdit($req);
    }

    public function testTimeclocksDeleteRejectsEntryFromUnmanagedStore(): void
    {
        $this->timeclocks = $this->createMock(TimeclockRepositoryInterface::class);
        $req = new Request();
        $req->setRouteParams(['id' => '10']);
        $req->setAttribute('managed_store_ids', [5]);

        $this->timeclocks->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 9]);
        $this->timeclocks->expects($this->never())->method('delete');

        $this->expectException(\kintai\Core\Exceptions\ForbiddenException::class);
        $this->controller()->timeclocksDelete($req);
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
