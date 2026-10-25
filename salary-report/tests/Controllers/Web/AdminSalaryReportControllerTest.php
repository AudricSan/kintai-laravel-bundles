<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\SalaryReport\Controllers\Web;

use kintai\Bundles\Installed\SalaryReport\Controllers\Web\AdminSalaryReportController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Container;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Contract\Provider\DailySales;
use kintai\Contract\Provider\SalesProvider;
use kintai\Core\BundleContract\Bundle;
use kintai\Core\BundleManager;
use kintai\Core\Extension\ExtensionRegistry;
use kintai\Core\Extension\Sales;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Bundles\Installed\SalaryReport\Repositories\SalaryReportRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Repositories\UserShiftTypeRateRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\Log;
use kintai\Core\Services\StoreStatsServiceInterface;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class AdminSalaryReportControllerTest extends TestCase
{
    private SalaryReportRepositoryInterface&Stub $salaryReports;
    private StoreRepositoryInterface&Stub $stores;
    private UserRepositoryInterface&Stub $users;
    private StoreUserRepositoryInterface&Stub $storeUsers;
    /** @var list<array<string, mixed>> Jours renvoyés par le fournisseur de ventes factice (rapports journaliers) */
    private array $dailyReports = [];
    private ShiftRepositoryInterface&Stub $shifts;
    private ShiftTypeRepositoryInterface&Stub $shiftTypes;
    private UserShiftTypeRateRepositoryInterface&Stub $userRates;
    private LogRepositoryInterface&Stub $logRepo;
    private StoreStatsServiceInterface&Stub $storeStatsService;
    private ?AdminSalaryReportController $controller = null;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-salary-report-views';
        $this->ensureViewFile($viewDir, 'reports-salary');
        $this->ensureViewFile($viewDir, 'reports-salary-show');
        $this->ensureViewFile($viewDir, 'reports-salary-form');
        $this->ensureViewFile($viewDir, 'reports-salary-export-pdf');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $this->salaryReports = $this->createStub(SalaryReportRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->users = $this->createStub(UserRepositoryInterface::class);
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->dailyReports = [];
        $this->shifts = $this->createStub(ShiftRepositoryInterface::class);
        $this->shiftTypes = $this->createStub(ShiftTypeRepositoryInterface::class);
        $this->userRates = $this->createStub(UserShiftTypeRateRepositoryInterface::class);
        $this->storeStatsService = $this->createStub(StoreStatsServiceInterface::class);

        $this->logRepo = $this->createStub(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): AdminSalaryReportController
    {
        if ($this->controller === null) {
            $viewDir = sys_get_temp_dir() . '/kintai-salary-report-views';
            $view = new ViewRenderer(sys_get_temp_dir());
            $view->addNamespace('salary-report', $viewDir);
            $this->controller = new AdminSalaryReportController(
                $view,
                $this->stores,
                $this->users,
                $this->salaryReports,
                $this->storeUsers,
                $this->sales(),
                $this->shifts,
                $this->shiftTypes,
                $this->userRates,
                new AuditLogger(),
                $this->storeStatsService,
                new PermissionService(
                    $this->createStub(RoleAssignmentRepositoryInterface::class),
                    $this->createStub(RoleRepositoryInterface::class),
                ),
            );
        }

        return $this->controller;
    }

    /**
     * Ventes lues par le contrat (fournisseurs, 1.14), alimentées par un fournisseur factice qui renvoie
     * $this->dailyReports filtrés par statut, comme le fait daily-report.
     */
    private function sales(): Sales
    {
        $provider = new class(fn(): array => $this->dailyReports) implements SalesProvider {
            public function __construct(private readonly \Closure $rows) {}

            public function dailySales(int $storeId, string $from, string $to, ?array $statuses = null): array
            {
                $days = [];
                foreach (($this->rows)() as $r) {
                    if ($statuses === null || in_array($r['status'], $statuses, true)) {
                        $days[] = new DailySales($r['report_date'], $r['status'], (float) $r['sales_total']);
                    }
                }
                return $days;
            }
        };
        $bundle = new class($provider) extends Bundle {
            public function __construct(private readonly SalesProvider $provider) {}

            public function getName(): string
            {
                return 'daily-report';
            }

            public function register(): void {}

            public function salesProviders(): array
            {
                return [$this->provider];
            }
        };
        $manager = (new \ReflectionClass(BundleManager::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($manager, 'bundles'))->setValue($manager, ['daily-report' => $bundle]);

        return new Sales(new ExtensionRegistry($manager));
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER = [];
        Log::reset();
    }

    // -------------------------------------------------------------------------
    // Export de la liste (item 5)
    // -------------------------------------------------------------------------

    public function testAllSalaryReportsBuildsStoreMembersByStoreForTheCreateModal(): void
    {
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([
            ['id' => 1, 'name' => 'Store A'],
            ['id' => 2, 'name' => 'Store B'],
        ]);
        $this->salaryReports->method('findAll')->willReturn([]);
        $this->storeUsers->method('findByStore')->willReturnMap([
            [1, [['user_id' => 10, 'store_id' => 1]]],
            [2, []],
        ]);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'first_name' => 'Jean', 'last_name' => 'Dupont']);

        $response = $this->controller()->allSalaryReports($req);

        $this->assertSame(200, $response->status());
    }

    public function testExportSalaryReportsJsonReturnsData(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->salaryReports->method('findAll')->willReturn([
            ['id' => 10, 'store_id' => 1, 'target_month' => '2026-07'],
        ]);

        $response = $this->controller()->exportSalaryReportsJson($req);
        $this->assertSame(200, $response->status());

        $data = json_decode($response->body(), true)['data'];
        $this->assertCount(1, $data);
        $this->assertSame('2026-07', $data[0]['target_month']);
    }

    public function testExportSalaryReportsPdfReturnsHtmlPreview(): void
    {
        $_SERVER['PHP_SELF'] ??= '/index.php';

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->salaryReports->method('findAll')->willReturn([]);

        $response = $this->controller()->exportSalaryReportsPdf($req);

        $this->assertSame(200, $response->status());
        $this->assertStringNotContainsString('%PDF', $response->body());
    }

    public function testExportSalaryReportsPdfDownloadReturnsPdfResponse(): void
    {
        $_SERVER['PHP_SELF'] ??= '/index.php';

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->salaryReports->method('findAll')->willReturn([]);

        $response = $this->controller()->exportSalaryReportsPdfDownload($req);

        $this->assertSame(200, $response->status());
        $this->assertStringStartsWith('%PDF', $response->body());
    }

    public function testSalaryReportsPassesStoreMembersForTheEmployeePicker(): void
    {
        $this->salaryReports = $this->createMock(SalaryReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->salaryReports->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([]);
        $this->storeUsers->expects($this->once())->method('findByStore')->with(1)->willReturn([
            ['user_id' => 10, 'store_id' => 1],
        ]);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'first_name' => 'Jean', 'last_name' => 'Dupont']);

        $response = $this->controller()->salaryReports($req);

        $this->assertSame(200, $response->status());
    }

    public function testShowSalaryReportLogsConsultation(): void
    {
        $this->salaryReports = $this->createMock(SalaryReportRepositoryInterface::class);
        $this->logRepo = $this->createMock(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '30']);

        $this->salaryReports->expects($this->atLeastOnce())->method('findById')->with(30)->willReturn(['id' => 30, 'store_id' => 1, 'target_month' => '2026-08']);
        $this->stores->method('findById')->willReturn(['id' => 1, 'name' => 'Store A']);

        $this->logRepo->expects($this->once())->method('record')->with(
            $this->anything(), $this->anything(), $this->anything(),
            'salary_report.viewed', 'salary_report', 30,
            $this->anything(), $this->anything(), 1,
            $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(),
        );

        $response = $this->controller()->showSalaryReport($req);

        $this->assertSame(200, $response->status());
    }

    public function testShowSalaryReportOfAnotherStoreThrowsNotFound(): void
    {
        $this->salaryReports = $this->createMock(SalaryReportRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '30']);

        // Le rapport existe mais appartient au store 2, pas au store 1 de l'URL
        $this->salaryReports->expects($this->atLeastOnce())->method('findById')->with(30)->willReturn(['id' => 30, 'store_id' => 2]);

        $this->expectException(NotFoundException::class);
        $this->controller()->showSalaryReport($req);
    }

    public function testStoreSalaryReportRejectsDuplicateMonth(): void
    {
        $this->salaryReports = $this->createMock(SalaryReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $_POST = ['target_month' => '2026-08'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->salaryReports->expects($this->atLeastOnce())->method('findByStoreAndMonth')->with(1, '2026-08')
            ->willReturn(['id' => 99, 'store_id' => 1, 'target_month' => '2026-08']);
        $this->salaryReports->expects($this->never())->method('save');

        $response = $this->controller()->storeSalaryReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreSalaryReportSavesAndRedirects(): void
    {
        $this->salaryReports = $this->createMock(SalaryReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $_POST = [
            'target_month'          => '2026-08',
            'store_name'            => 'Store A',
            'person_in_charge'      => 'Manager X',
            'total_payment'         => '100000',
            'total_deductions'      => '',
            'income_tax_base'       => '',
            'withholding_tax'       => '',
            'residence_tax'         => '',
            'other_deductions'      => '',
            'net_payment'           => '',
            'active_employees'      => '3',
            'hand_delivered_salary' => '',
            'staff_man_hours'       => '',
            'staff_total_payment'   => '',
            'staff_avg_hourly_wage' => '',
            'employee_work_hours'   => '',
            'new_hires'             => '0',
            'resigned_staff'        => '0',
            'hire_registrations'    => '',
            'remarks'               => '',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->salaryReports->expects($this->atLeastOnce())->method('findByStoreAndMonth')->with(1, '2026-08')->willReturn(null);
        $this->salaryReports->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) =>
                $data['store_id'] === 1
                && $data['target_month'] === '2026-08'
                && $data['total_payment'] === 100000.0
                && $data['active_employees'] === 3
        ))->willReturn(['id' => 55]);

        $response = $this->controller()->storeSalaryReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testUpdateSalaryReportMergesPostFieldsAndRedirects(): void
    {
        $this->salaryReports = $this->createMock(SalaryReportRepositoryInterface::class);
        $_POST = [
            'target_month'          => '2026-09',
            'store_name'            => 'Store A',
            'person_in_charge'      => 'Manager Y',
            'total_payment'         => '200000',
            'total_deductions'      => '0',
            'income_tax_base'       => '0',
            'withholding_tax'       => '0',
            'residence_tax'         => '0',
            'other_deductions'      => '0',
            'net_payment'           => '0',
            'active_employees'      => '4',
            'hand_delivered_salary' => '0',
            'staff_man_hours'       => '0',
            'staff_total_payment'   => '0',
            'staff_avg_hourly_wage' => '0',
            'employee_work_hours'   => '',
            'new_hires'             => '0',
            'resigned_staff'        => '0',
            'hire_registrations'    => '',
            'remarks'               => '',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => '1', 'rid' => '30']);

        $this->salaryReports->expects($this->atLeastOnce())->method('findById')->with(30)
            ->willReturn(['id' => 30, 'store_id' => 1, 'target_month' => '2026-08']);

        $this->salaryReports->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) =>
                $data['id'] === 30
                && $data['person_in_charge'] === 'Manager Y'
                && $data['total_payment'] === 200000.0
                // Régression : la date du rapport modifiée dans le formulaire doit être persistée (voir sr-target-month).
                && $data['target_month'] === '2026-09'
        ));

        $response = $this->controller()->updateSalaryReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testDeleteSalaryReportDeletesAndLogs(): void
    {
        $this->salaryReports = $this->createMock(SalaryReportRepositoryInterface::class);
        $this->logRepo = $this->createMock(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '30']);

        $this->salaryReports->expects($this->atLeastOnce())->method('findById')->with(30)->willReturn(['id' => 30, 'store_id' => 1]);
        $this->salaryReports->expects($this->once())->method('delete')->with(30);

        $this->logRepo->expects($this->once())->method('record')->with(
            $this->anything(), $this->anything(), $this->anything(),
            'salary_report.deleted', 'salary_report', 30,
            $this->anything(), $this->anything(), $this->anything(),
            $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(),
        );

        $response = $this->controller()->deleteSalaryReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testCreateSalaryReportPresetsFromDailyReportsAndShifts(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'display_name' => 'Manager X']);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([]);

        $this->dailyReports = ([
            ['report_date' => date('Y-m') . '-01', 'status' => 'validated', 'sales_total' => '10000'],
            ['report_date' => date('Y-m') . '-02', 'status' => 'submitted', 'sales_total' => '5000'],
            ['report_date' => date('Y-m') . '-03', 'status' => 'draft', 'sales_total' => '9999'],
        ]);
        $this->shifts->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([
            ['shift_date' => date('Y-m') . '-05', 'duration_minutes' => 480, 'estimated_salary' => 6000, 'user_id' => 7],
        ]);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(7)->willReturn(['id' => 7, 'display_name' => 'Employé']);

        $response = $this->controller()->createSalaryReport($req);

        $this->assertSame(200, $response->status());
    }

    // -------------------------------------------------------------------------
    // calculateSalaryReport — recalcul AJAX pour le sélecteur de période
    // -------------------------------------------------------------------------

    public function testCalculateSalaryReportReturnsRecalculatedPresetForStoreWideRange(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $_GET = ['from' => '2026-08-01', 'to' => '2026-08-31'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'display_name' => 'Manager X']);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->dailyReports = ([
            ['report_date' => '2026-08-01', 'status' => 'validated', 'sales_total' => 10000],
        ]);
        $this->shifts->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([
            ['shift_date' => '2026-08-05', 'duration_minutes' => 480, 'estimated_salary' => 6000, 'user_id' => 7],
        ]);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(7)->willReturn(['id' => 7, 'last_name' => 'Dupont', 'first_name' => 'Jean']);

        $response = $this->controller()->calculateSalaryReport($req);

        $this->assertSame(200, $response->status());
        $data = json_decode($response->body(), true);
        // json_decode() renvoie un int pour les flottants sans décimales (10000.0 -> 10000).
        $this->assertEquals(10000.0, $data['preset']['total_payment']);
        $this->assertEquals(8.0, $data['preset']['staff_man_hours']);
        $this->assertSame('2026-08', $data['preset']['target_month']);
        $this->assertSame('store', $data['detail']['mode']);
        $this->assertCount(1, $data['detail']['employees']);
        $this->assertSame('Dupont Jean', $data['detail']['employees'][0]['name']);
    }

    /**
     * Un shift à cheval sur deux types d'horaire (ex : 07:00-18:00 traverse un
     * type 05:00-08:00 puis un type 08:00-22:00) doit être facturé par tranche,
     * pas au taux unique d'un seul type — c'est le bug corrigé par
     * ShiftWageCalculator::costOf(). Le rapport magasin doit aussi exposer la
     * répartition des heures par type.
     */
    public function testCalculateSalaryReportSplitsMultiTypeShiftAcrossTranches(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftTypes = $this->createMock(ShiftTypeRepositoryInterface::class);
        $_GET = ['from' => '2026-08-01', 'to' => '2026-08-31'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'display_name' => 'Manager X']);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->dailyReports = ([]);
        $this->shifts->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([
            [
                'shift_date' => '2026-08-05', 'start_time' => '07:00', 'end_time' => '18:00',
                'duration_minutes' => 660, 'pause_minutes' => 0, 'user_id' => 7,
            ],
        ]);
        $this->shiftTypes->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([
            ['id' => 1, 'name' => 'Tôt',  'start_time' => '05:00', 'end_time' => '08:00', 'hourly_rate' => 1200.0],
            ['id' => 2, 'name' => 'Jour', 'start_time' => '08:00', 'end_time' => '22:00', 'hourly_rate' => 1000.0],
        ]);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(7)->willReturn(['id' => 7, 'last_name' => 'Dupont', 'first_name' => 'Jean']);

        $response = $this->controller()->calculateSalaryReport($req);

        $this->assertSame(200, $response->status());
        $data = json_decode($response->body(), true);

        // 1h × 1200 (Tôt) + 10h × 1000 (Jour) = 11200, pas 11h × un seul taux.
        $this->assertEquals(11200.0, $data['preset']['staff_total_payment']);
        $this->assertEquals(11200.0, $data['detail']['employees'][0]['cost']);

        $breakdown = array_column($data['detail']['type_breakdown'], null, 'name');
        $this->assertEquals(60, $breakdown['Tôt']['minutes']);
        $this->assertEquals(600, $breakdown['Jour']['minutes']);
        $this->assertEquals(1200.0, $breakdown['Tôt']['amount']);
        $this->assertEquals(10000.0, $breakdown['Jour']['amount']);
    }

    public function testCalculateSalaryReportUsesPayslipDataForEmployeeScopedRange(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftTypes = $this->createMock(ShiftTypeRepositoryInterface::class);
        $this->storeStatsService = $this->createMock(StoreStatsServiceInterface::class);
        $_GET = ['from' => '2026-08-01', 'to' => '2026-08-15', 'user_id' => '7'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'display_name' => 'Manager X']);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->dailyReports = ([]);
        $this->shifts->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([
            ['shift_date' => '2026-08-05', 'duration_minutes' => 480, 'pause_minutes' => 0, 'shift_type_id' => 1, 'user_id' => 7],
        ]);
        $this->shiftTypes->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([['id' => 1, 'hourly_rate' => 750.0]]);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(7)->willReturn(['id' => 7, 'last_name' => 'Dupont', 'first_name' => 'Jean']);

        $this->storeStatsService->expects($this->once())->method('buildPayslipData')
            ->with(1, 7, '2026-08-01', '2026-08-15')
            ->willReturn([
                'shiftRows' => [], 'totalGrossMin' => 0, 'totalNetMin' => 0, 'totalCost' => 0.0,
                'anyRate' => false, 'deductions' => [], 'totalDeductions' => 0.0, 'netPay' => 0.0,
                'deductionsEnabled' => false,
            ]);

        $response = $this->controller()->calculateSalaryReport($req);

        $this->assertSame(200, $response->status());
        $data = json_decode($response->body(), true);
        // Seul l'employé 7 est comptabilisé dans le preset (8h à 6000).
        $this->assertEquals(6000.0, $data['preset']['staff_total_payment']);
        $this->assertArrayHasKey('shiftRows', $data['detail']);
    }

    public function testCalculateSalaryReportRejectsInvalidRange(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $_GET = ['from' => '2026-08-31', 'to' => '2026-08-01'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);

        $response = $this->controller()->calculateSalaryReport($req);

        $this->assertSame(422, $response->status());
    }

    public function testCalculateSalaryReportRejectsMalformedDates(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $_GET = ['from' => 'not-a-date', 'to' => '2026-08-01'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);

        $response = $this->controller()->calculateSalaryReport($req);

        $this->assertSame(422, $response->status());
    }

    // -------------------------------------------------------------------------
    // calculateSalaryPreset — mode de saisie cumulatif du rapport journalier (item 4)
    // -------------------------------------------------------------------------

    public function testCalculateSalaryPresetSumsRawValuesInPerDayMode(): void
    {
        $store = ['id' => 1, 'name' => 'Store A', 'daily_report_settings' => null];
        $authUser = ['id' => 1, 'display_name' => 'Manager X'];

        $this->dailyReports = ([
            ['report_date' => date('Y-m') . '-01', 'status' => 'validated', 'sales_total' => 1000],
            ['report_date' => date('Y-m') . '-02', 'status' => 'validated', 'sales_total' => 2000],
        ]);
        $this->shifts->method('findByStore')->willReturn([]);

        $preset = $this->invokeCalculateSalaryPreset($store, date('Y-m'), $authUser);

        $this->assertSame(3000.0, $preset['total_payment']);
    }

    /**
     * Magasin en saisie cumulée : le fournisseur de ventes renvoie déjà les valeurs du jour (1000, 1100, 1200 pour des
     * cumuls 1000, 2100, 3300) ; le rapport additionne ces valeurs et retrouve le total du mois, sans recompter.
     */
    public function testCalculateSalaryPresetSumsTheDayValuesOfACumulativeStore(): void
    {
        $store = ['id' => 1, 'name' => 'Store A', 'daily_report_settings' => json_encode(['cumulative_mode' => 'cumulative_input'])];
        $authUser = ['id' => 1, 'display_name' => 'Manager X'];

        $this->dailyReports = ([
            ['report_date' => date('Y-m') . '-01', 'status' => 'validated', 'sales_total' => 1000],
            ['report_date' => date('Y-m') . '-02', 'status' => 'validated', 'sales_total' => 1100],
            ['report_date' => date('Y-m') . '-03', 'status' => 'validated', 'sales_total' => 1200],
        ]);
        $this->shifts->method('findByStore')->willReturn([]);

        $preset = $this->invokeCalculateSalaryPreset($store, date('Y-m'), $authUser);

        $this->assertSame(3300.0, $preset['total_payment']);
    }

    // -------------------------------------------------------------------------
    // Rapport de salaire par employé (item 7)
    // -------------------------------------------------------------------------

    public function testCreateSalaryReportWithUserIdPresetsEmployeeAndScopesHours(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $_GET['user_id'] = '7';
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'display_name' => 'Manager X']);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([]);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(7)->willReturn(['id' => 7, 'last_name' => 'Dupont', 'first_name' => 'Jean']);
        $this->dailyReports = ([]);
        $this->shifts->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([
            ['shift_date' => date('Y-m') . '-05', 'duration_minutes' => 480, 'estimated_salary' => 6000, 'user_id' => 7],
            ['shift_date' => date('Y-m') . '-06', 'duration_minutes' => 240, 'estimated_salary' => 3000, 'user_id' => 9],
        ]);

        $response = $this->controller()->createSalaryReport($req);
        $this->assertSame(200, $response->status());
    }

    public function testCalculateSalaryPresetScopesHoursToOneEmployeeWhenUserIdGiven(): void
    {
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $store = ['id' => 1, 'name' => 'Store A', 'daily_report_settings' => null];
        $authUser = ['id' => 1, 'display_name' => 'Manager X'];

        $this->dailyReports = ([]);
        $this->shifts->method('findByStore')->willReturn([
            ['shift_date' => date('Y-m') . '-05', 'duration_minutes' => 480, 'pause_minutes' => 0, 'shift_type_id' => 1, 'user_id' => 7],
            ['shift_date' => date('Y-m') . '-06', 'duration_minutes' => 240, 'pause_minutes' => 0, 'shift_type_id' => 1, 'user_id' => 9],
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([['id' => 1, 'hourly_rate' => 750.0]]);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(7)->willReturn(['id' => 7, 'last_name' => 'Dupont', 'first_name' => 'Jean']);

        $preset = $this->invokeCalculateSalaryPreset($store, date('Y-m'), $authUser, 7);

        // Seul l'employé 7 est compté : 8h à 6000, pas les 4h de l'employé 9.
        $this->assertSame(8.0, $preset['staff_man_hours']);
        $this->assertSame(6000.0, $preset['staff_total_payment']);
        $this->assertSame(1, $preset['active_employees']);
        // Le total des ventes du magasin n'a pas de sens pour un rapport individuel.
        $this->assertSame(0.0, $preset['total_payment']);
    }

    public function testCalculateSalaryPresetPrefillsDeductionsForSingleEmployee(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $store = ['id' => 1, 'name' => 'Store A', 'daily_report_settings' => null];
        $authUser = ['id' => 1, 'display_name' => 'Manager X'];

        $this->dailyReports = ([]);
        $this->shifts->method('findByStore')->willReturn([
            ['shift_date' => date('Y-m') . '-05', 'duration_minutes' => 480, 'pause_minutes' => 0, 'shift_type_id' => 1, 'user_id' => 7],
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([['id' => 1, 'hourly_rate' => 750.0]]);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(7)->willReturn(['id' => 7, 'last_name' => 'Dupont', 'first_name' => 'Jean']);
        $this->stores->expects($this->atLeastOnce())->method('getDeductionSettings')->with(1)->willReturn([
            'enabled' => true,
            'health_insurance_rate' => 5,
            'pension_rate' => 9,
            'employment_insurance_rate' => 1,
            'income_tax_rate' => 3,
            'resident_tax_monthly' => 1000,
        ]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 7)->willReturn(['id' => 55]);
        $this->storeUsers->expects($this->atLeastOnce())->method('getSubjectToDeductions')->with(55)->willReturn(true);

        $preset = $this->invokeCalculateSalaryPreset($store, date('Y-m'), $authUser, 7);

        $this->assertSame(6000.0, $preset['income_tax_base']);
        $this->assertSame(900.0, $preset['other_deductions']);
        $this->assertSame(180.0, $preset['withholding_tax']);
        $this->assertSame(1000.0, $preset['residence_tax']);
        $this->assertSame(2080.0, $preset['total_deductions']);
        $this->assertSame(3920.0, $preset['net_payment']);
        $this->assertSame(3920.0, $preset['bank_transfer_salary']);
        $this->assertArrayNotHasKey('hand_delivered_salary', $preset);
    }

    public function testCalculateSalaryPresetSumsDeductionsAcrossEmployeesForStoreWideReport(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $store = ['id' => 1, 'name' => 'Store A', 'daily_report_settings' => null];
        $authUser = ['id' => 1, 'display_name' => 'Manager X'];

        $this->dailyReports = ([]);
        $this->shifts->method('findByStore')->willReturn([
            ['shift_date' => date('Y-m') . '-05', 'duration_minutes' => 480, 'pause_minutes' => 0, 'shift_type_id' => 1, 'user_id' => 7],
            ['shift_date' => date('Y-m') . '-06', 'duration_minutes' => 240, 'pause_minutes' => 0, 'shift_type_id' => 1, 'user_id' => 9],
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([['id' => 1, 'hourly_rate' => 750.0]]);
        $this->users->method('findById')->willReturnMap([
            [7, ['id' => 7, 'last_name' => 'Dupont', 'first_name' => 'Jean']],
            [9, ['id' => 9, 'last_name' => 'Martin', 'first_name' => 'Léa']],
        ]);
        $this->stores->expects($this->atLeastOnce())->method('getDeductionSettings')->with(1)->willReturn([
            'enabled' => true,
            'health_insurance_rate' => 5,
            'pension_rate' => 9,
            'employment_insurance_rate' => 1,
            'income_tax_rate' => 3,
            'resident_tax_monthly' => 1000,
        ]);
        $this->storeUsers->method('findMembership')->willReturnMap([
            [1, 7, ['id' => 55]],
            [1, 9, ['id' => 56]],
        ]);
        $this->storeUsers->method('getSubjectToDeductions')->willReturnMap([
            [55, true],
            [56, true],
        ]);

        $preset = $this->invokeCalculateSalaryPreset($store, date('Y-m'), $authUser);

        $this->assertSame(9000.0, $preset['income_tax_base']);
        $this->assertSame(1350.0, $preset['other_deductions']);
        $this->assertSame(270.0, $preset['withholding_tax']);
        $this->assertSame(2000.0, $preset['residence_tax']);
        $this->assertSame(3620.0, $preset['total_deductions']);
        $this->assertSame(5380.0, $preset['net_payment']);
    }

    public function testCalculateSalaryPresetSkipsDeductionsWhenEmployeeNotSubject(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $store = ['id' => 1, 'name' => 'Store A', 'daily_report_settings' => null];
        $authUser = ['id' => 1, 'display_name' => 'Manager X'];

        $this->dailyReports = ([]);
        $this->shifts->method('findByStore')->willReturn([
            ['shift_date' => date('Y-m') . '-05', 'duration_minutes' => 480, 'estimated_salary' => 6000, 'user_id' => 7],
        ]);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(7)->willReturn(['id' => 7, 'last_name' => 'Dupont', 'first_name' => 'Jean']);
        $this->stores->expects($this->atLeastOnce())->method('getDeductionSettings')->with(1)->willReturn([
            'enabled' => true,
            'health_insurance_rate' => 5,
            'pension_rate' => 9,
            'employment_insurance_rate' => 1,
            'income_tax_rate' => 3,
            'resident_tax_monthly' => 1000,
        ]);
        $this->storeUsers->method('findMembership')->willReturn(['id' => 55]);
        $this->storeUsers->method('getSubjectToDeductions')->willReturn(false);

        $preset = $this->invokeCalculateSalaryPreset($store, date('Y-m'), $authUser, 7);

        $this->assertArrayNotHasKey('total_deductions', $preset);
    }

    public function testShowSalaryReportWithUserIdIncludesShiftDetailFromPayslipData(): void
    {
        $this->salaryReports = $this->createMock(SalaryReportRepositoryInterface::class);
        $this->storeStatsService = $this->createMock(StoreStatsServiceInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '30']);

        $this->salaryReports->expects($this->atLeastOnce())->method('findById')->with(30)->willReturn([
            'id' => 30, 'store_id' => 1, 'user_id' => 7, 'target_month' => '2026-08',
        ]);
        $this->stores->method('findById')->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->storeStatsService->expects($this->once())->method('buildPayslipData')
            ->with(1, 7, '2026-08-01', '2026-08-31')
            ->willReturn(['shiftRows' => [], 'totalGrossMin' => 0, 'totalNetMin' => 0, 'totalCost' => 0.0, 'anyRate' => false, 'deductions' => [], 'totalDeductions' => 0.0, 'netPay' => 0.0, 'deductionsEnabled' => false]);

        $response = $this->controller()->showSalaryReport($req);

        $this->assertSame(200, $response->status());
    }

    public function testStoreSalaryReportSavesUserIdWhenProvided(): void
    {
        $this->salaryReports = $this->createMock(SalaryReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $_POST = ['target_month' => '2026-08', 'user_id' => '7', 'employee_name' => 'Jean Dupont'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 7)->willReturn(['id' => 3]);
        $this->salaryReports->expects($this->atLeastOnce())->method('findByStoreAndMonth')->with(1, '2026-08', 7)->willReturn(null);
        $this->salaryReports->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) => $data['user_id'] === 7 && $data['employee_name'] === 'Jean Dupont'
        ))->willReturn(['id' => 60]);

        $response = $this->controller()->storeSalaryReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreSalaryReportAllowsEmployeeReportAlongsideStoreWideReport(): void
    {
        $this->salaryReports = $this->createMock(SalaryReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $_POST = ['target_month' => '2026-08', 'user_id' => '7'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1]);
        // Un rapport global existe déjà pour ce mois (user_id=null), mais celui de l'employé 7 n'existe pas encore.
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 7)->willReturn(['id' => 3]);
        $this->salaryReports->expects($this->atLeastOnce())->method('findByStoreAndMonth')->with(1, '2026-08', 7)->willReturn(null);
        $this->salaryReports->expects($this->once())->method('save')->willReturn(['id' => 61]);

        $response = $this->controller()->storeSalaryReport($req);

        $this->assertSame(302, $response->status());
    }

    /** Audit du 13/10/2026 : un rapport de salaire ne peut pas viser un compte étranger au magasin. */
    public function testStoreSalaryReportRefusesUserOutsideTheStore(): void
    {
        $this->salaryReports = $this->createMock(SalaryReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $_POST = ['target_month' => '2026-08', 'user_id' => '77'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->storeUsers->method('findMembership')->willReturn(null);
        $this->salaryReports->expects($this->never())->method('save');

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->controller()->storeSalaryReport($req);
    }

    private function invokeCalculateSalaryPreset(array $store, string $targetMonth, array $authUser, ?int $userId = null): array
    {
        $from = $targetMonth . '-01';
        $to = date('Y-m-t', strtotime($from));

        $method = new \ReflectionMethod($this->controller(), 'calculateSalaryPreset');
        $method->setAccessible(true);
        return $method->invoke($this->controller(), $store, $from, $to, $authUser, $userId);
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
