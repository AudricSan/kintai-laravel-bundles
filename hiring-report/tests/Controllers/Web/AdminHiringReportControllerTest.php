<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\HiringReport\Controllers\Web;


use kintai\Bundles\Installed\HiringReport\Controllers\Web\AdminHiringReportController;
use kintai\Core\Container;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Bundles\Installed\HiringReport\Repositories\HiringReportRepositoryInterface;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class AdminHiringReportControllerTest extends TestCase
{
    private HiringReportRepositoryInterface&Stub $hiringReports;
    private StoreRepositoryInterface&Stub $stores;
    private UserRepositoryInterface&Stub $users;
    private StoreUserRepositoryInterface&Stub $storeUsers;
    private LogRepositoryInterface&Stub $logRepo;
    private ?AdminHiringReportController $controller = null;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-hiring-report-views';
        $this->ensureViewFile($viewDir, 'reports-hiring');
        $this->ensureViewFile($viewDir, 'reports-hiring-show');
        $this->ensureViewFile($viewDir, 'reports-hiring-form');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $this->hiringReports = $this->createStub(HiringReportRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->users = $this->createStub(UserRepositoryInterface::class);
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);

        $this->logRepo = $this->createStub(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): AdminHiringReportController
    {
        if ($this->controller === null) {
            $viewDir = sys_get_temp_dir() . '/kintai-hiring-report-views';
            $view = new ViewRenderer(sys_get_temp_dir());
            $view->addNamespace('hiring-report', $viewDir);
            $this->controller = new AdminHiringReportController(
                $view,
                $this->stores,
                $this->users,
                $this->hiringReports,
                new AuditLogger(),
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
        Log::reset();
    }

    public function testAllHiringReportsListsAcrossStoresForAdmin(): void
    {
        $this->hiringReports = $this->createMock(HiringReportRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setAttribute('managed_store_ids', null);

        $this->stores->method('findAll')->willReturn([
            ['id' => 1, 'name' => 'Store A'],
            ['id' => 2, 'name' => 'Store B'],
        ]);
        $this->hiringReports->expects($this->once())->method('findAll')
            ->with([], [])
            ->willReturn([
                ['id' => 10, 'store_id' => 1, 'employee_name' => 'Alice'],
                ['id' => 11, 'store_id' => 2, 'employee_name' => 'Bob'],
            ]);

        $response = $this->controller()->allHiringReports($req);

        $this->assertSame(200, $response->status());
    }

    public function testAllHiringReportsAppliesStoreYearMonthFilters(): void
    {
        $this->hiringReports = $this->createMock(HiringReportRepositoryInterface::class);
        $_GET = ['store_id' => '2', 'year' => '2026', 'month' => '08'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setAttribute('managed_store_ids', null);

        $this->stores->method('findAll')->willReturn([
            ['id' => 1, 'name' => 'Store A'],
            ['id' => 2, 'name' => 'Store B'],
        ]);
        $this->hiringReports->expects($this->once())->method('findAll')
            ->with([2], ['store_id' => 2, 'year' => '2026', 'month' => '08'])
            ->willReturn([]);

        $response = $this->controller()->allHiringReports($req);

        $this->assertSame(200, $response->status());
    }

    public function testAllHiringReportsScopesNonAdminToManagedStores(): void
    {
        $this->hiringReports = $this->createMock(HiringReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setAttribute('managed_store_ids', [3]);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(3)->willReturn(['id' => 3, 'name' => 'Store C']);
        $this->hiringReports->expects($this->once())->method('findAll')
            ->with([3], [])
            ->willReturn([]);

        $response = $this->controller()->allHiringReports($req);

        $this->assertSame(200, $response->status());
    }

    public function testShowHiringReportLogsConsultation(): void
    {
        $this->hiringReports = $this->createMock(HiringReportRepositoryInterface::class);
        $this->logRepo = $this->createMock(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->hiringReports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1, 'employee_name' => 'Alice']);
        $this->stores->method('findById')->willReturn(['id' => 1, 'name' => 'Store A']);

        $this->logRepo->expects($this->once())->method('record')->with(
            $this->anything(), $this->anything(), $this->anything(),
            'hiring_report.viewed', 'hiring_report', 10,
            $this->anything(), $this->anything(), 1,
            $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(),
        );

        $response = $this->controller()->showHiringReport($req);

        $this->assertSame(200, $response->status());
    }

    public function testShowHiringReportOfAnotherStoreThrowsNotFound(): void
    {
        $this->hiringReports = $this->createMock(HiringReportRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        // Le rapport existe mais appartient au store 2, pas au store 1 de l'URL
        $this->hiringReports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 2]);

        $this->expectException(NotFoundException::class);
        $this->controller()->showHiringReport($req);
    }

    /** Audit du 13/10/2026 : un rapport d'embauche ne peut pas être lié à un compte étranger au magasin. */
    public function testStoreHiringReportRefusesUserOutsideTheStore(): void
    {
        $this->hiringReports = $this->createMock(HiringReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $_POST = ['user_id' => '77', 'employee_name' => 'X'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '1']);
        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->storeUsers->method('findMembership')->willReturn(null);
        $this->hiringReports->expects($this->never())->method('save');

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->controller()->storeHiringReport($req);
    }

    /** Le formulaire ne propose que les membres du magasin, pas tous les comptes de l'instance. */
    public function testCreateHiringReportOnlyListsMembersOfTheStore(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => '1']);
        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'A']);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([['user_id' => 5]]);
        $this->users->expects($this->never())->method('findAll');
        $this->users->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn(['id' => 5]);

        $this->assertSame(200, $this->controller()->createHiringReport($req)->status());
    }

    public function testStoreHiringReportSavesAndRedirects(): void
    {
        $this->hiringReports = $this->createMock(HiringReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $_POST = [
            'user_id'             => '5',
            'employee_number'     => 'E-1',
            'employee_name'       => 'Alice Martin',
            'furigana_last_name'  => '',
            'furigana_first_name' => '',
            'gender'              => '',
            'tax_classification'  => '',
            'birth_date'          => '',
            'hire_date'           => '2026-08-01',
            'education'           => '',
            'postal_code'         => '',
            'address'             => '',
            'phone'               => '',
            'mobile_phone'        => '',
            'email'               => '',
            'guarantor_name'      => '',
            'guarantor_phone'     => '',
            'store_name'          => '',
            'hired_by'            => 'Manager X',
            'notes'               => '',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 5)->willReturn(['id' => 3]);
        $this->hiringReports->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) =>
                $data['store_id'] === 1
                && $data['user_id'] === 5
                && $data['employee_name'] === 'Alice Martin'
        ))->willReturn(['id' => 99]);

        $response = $this->controller()->storeHiringReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testUpdateHiringReportMergesPostFieldsAndRedirects(): void
    {
        $this->hiringReports = $this->createMock(HiringReportRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $_POST = [
            'user_id'         => '5',
            'employee_number' => 'E-42',
            'employee_name'   => 'Alice Martin',
            'notes'           => '',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 5)->willReturn(['id' => 3]);
        $this->hiringReports->expects($this->atLeastOnce())->method('findById')->with(10)
            ->willReturn(['id' => 10, 'store_id' => 1, 'employee_name' => 'Ancien nom']);

        $this->hiringReports->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) =>
                $data['id'] === 10
                && $data['user_id'] === 5
                && $data['employee_number'] === 'E-42'
                && $data['employee_name'] === 'Alice Martin'
                && $data['notes'] === null // champ vide → null
        ));

        $response = $this->controller()->updateHiringReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testDeleteHiringReportDeletesAndLogs(): void
    {
        $this->hiringReports = $this->createMock(HiringReportRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->hiringReports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1]);
        $this->hiringReports->expects($this->once())->method('delete')->with(10);

        $response = $this->controller()->deleteHiringReport($req);

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
