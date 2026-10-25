<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ResignationReport\Controllers\Web;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 4));
}


use kintai\Bundles\Installed\ResignationReport\Controllers\Web\AdminResignationReportController;
use kintai\Bundles\Installed\ResignationReport\Services\ResignationDeactivation;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Container;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Core\Repositories\LogRepositoryInterface;
use kintai\Bundles\Installed\ResignationReport\Repositories\ResignationReportRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
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

final class AdminResignationReportControllerTest extends TestCase
{
    private ResignationReportRepositoryInterface&Stub $resignationReports;
    private StoreRepositoryInterface&Stub $stores;
    private UserRepositoryInterface&Stub $users;
    private StoreUserRepositoryInterface&Stub $storeUsers;
    private LogRepositoryInterface&Stub $logRepo;
    private RoleAssignmentRepositoryInterface&Stub $assignments;
    private RoleRepositoryInterface&Stub $roles;
    private ?AdminResignationReportController $controller = null;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-resignation-report-views';
        $this->ensureViewFile($viewDir, 'reports-resignation');
        $this->ensureViewFile($viewDir, 'reports-resignation-show');
        $this->ensureViewFile($viewDir, 'reports-resignation-form');
        $this->ensureViewFile($viewDir, 'reports-resignation-export-pdf');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $this->resignationReports = $this->createStub(ResignationReportRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->users = $this->createStub(UserRepositoryInterface::class);
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);

        $this->logRepo = $this->createStub(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);

        $this->assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $this->roles = $this->createStub(RoleRepositoryInterface::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): AdminResignationReportController
    {
        if ($this->controller === null) {
            $viewDir = sys_get_temp_dir() . '/kintai-resignation-report-views';
            $view = new ViewRenderer(sys_get_temp_dir());
            $view->addNamespace('resignation-report', $viewDir);
            $this->controller = new AdminResignationReportController(
                $view,
                $this->stores,
                $this->users,
                $this->resignationReports,
                $this->storeUsers,
                new AuditLogger(),
                new PermissionService($this->assignments, $this->roles),
                new ResignationDeactivation($this->resignationReports, $this->users),
                new \kintai\Core\Auth\UserAnonymizer(
                    $this->users,
                    $this->assignments,
                    new \kintai\Core\Auth\CredentialRevoker(
                        $this->createStub(\kintai\Core\Repositories\RememberTokenRepositoryInterface::class),
                        $this->createStub(\kintai\Core\Repositories\ApiTokenRepositoryInterface::class),
                    ),
                    sys_get_temp_dir(),
                ),
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

    // -------------------------------------------------------------------------
    // Export de la liste (item 5)
    // -------------------------------------------------------------------------

    public function testExportResignationReportsJsonReturnsData(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->resignationReports->method('findAll')->willReturn([
            ['id' => 10, 'store_id' => 1, 'employee_name' => 'Jean Dupont'],
        ]);

        $response = $this->controller()->exportResignationReportsJson($req);
        $this->assertSame(200, $response->status());

        $data = json_decode($response->body(), true)['data'];
        $this->assertCount(1, $data);
        $this->assertSame('Jean Dupont', $data[0]['employee_name']);
    }

    public function testExportResignationReportsPdfReturnsHtmlPreview(): void
    {
        $_SERVER['PHP_SELF'] ??= '/index.php';

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->resignationReports->method('findAll')->willReturn([]);

        $response = $this->controller()->exportResignationReportsPdf($req);

        $this->assertSame(200, $response->status());
        $this->assertStringNotContainsString('%PDF', $response->body());
    }

    public function testExportResignationReportsPdfDownloadReturnsPdfResponse(): void
    {
        $_SERVER['PHP_SELF'] ??= '/index.php';

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->resignationReports->method('findAll')->willReturn([]);

        $response = $this->controller()->exportResignationReportsPdfDownload($req);

        $this->assertSame(200, $response->status());
        $this->assertStringStartsWith('%PDF', $response->body());
    }

    public function testAllResignationReportsPassesYearMonthPersonFiltersToRepository(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $_GET = ['year' => '2026', 'month' => '07', 'person' => 'Dupont', 'store_id' => '1'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->resignationReports->expects($this->once())->method('findAll')->with(
            [1],
            ['store_id' => 1, 'year' => '2026', 'month' => '07', 'person_in_charge' => 'Dupont']
        )->willReturn([]);

        $response = $this->controller()->allResignationReports($req);

        $this->assertSame(200, $response->status());
    }

    public function testAllResignationReportsWithoutFiltersPassesEmptyFilters(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->resignationReports->expects($this->once())->method('findAll')->with([], [])->willReturn([]);

        $response = $this->controller()->allResignationReports($req);

        $this->assertSame(200, $response->status());
    }

    public function testCreateResignationReportOnlyListsMembersOfTheStore(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([
            ['user_id' => 10, 'store_id' => 1],
            ['user_id' => 20, 'store_id' => 1],
        ]);
        $this->users->method('findById')->willReturnMap([
            [10, ['id' => 10, 'first_name' => 'Jean', 'last_name' => 'Dupont', 'employee_code' => 'EMP010']],
            [20, ['id' => 20, 'first_name' => 'Paul', 'last_name' => 'Martin', 'employee_code' => 'EMP020']],
        ]);

        $response = $this->controller()->createResignationReport($req);

        $this->assertSame(200, $response->status());
    }

    public function testGetManagersForReportFormIncludesOwnerEvenWithoutStoreMembership(): void
    {
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles = $this->createMock(RoleRepositoryInterface::class);
        // L'Owner n'a pas forcément de ligne store_user (voir install.php) mais doit
        // rester sélectionnable comme responsable sur n'importe quel store.
        $this->storeUsers->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([
            ['user_id' => 10, 'store_id' => 1, 'role' => 'manager'],
        ]);
        $this->users->method('findAll')->willReturn([
            ['id' => 99, 'first_name' => 'Owner', 'last_name' => 'Test'],
            ['id' => 10, 'first_name' => 'Jean', 'last_name' => 'Dupont'],
        ]);
        $this->users->method('findById')->willReturnMap([
            [10, ['id' => 10, 'first_name' => 'Jean', 'last_name' => 'Dupont', 'employee_code' => 'EMP010']],
        ]);
        // L'utilisateur 99 détient un rôle système (Owner) en portée globale (RBAC).
        $this->assignments->expects($this->atLeastOnce())->method('findByScope')->with('global', null)->willReturn([
            ['id' => 2, 'user_id' => 99, 'role_id' => 1, 'scope_type' => 'global', 'scope_id' => null],
        ]);
        // Le rôle RBAC affecté à l'utilisateur 10 accorde employees.update sur le store 1.
        $this->assignments->expects($this->atLeastOnce())->method('findByUser')->with(10)->willReturn([
            ['id' => 3, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->willReturnMap([
            [1, ['id' => 1, 'slug' => 'owner', 'is_system' => 1]],
            [2, ['id' => 2, 'is_system' => 0]],
        ]);
        $this->roles->expects($this->atLeastOnce())->method('getPermissions')->with(2)->willReturn(['employees.update']);

        $method = new \ReflectionMethod($this->controller(), 'getManagersForReportForm');
        $method->setAccessible(true);
        $managers = $method->invoke($this->controller(), 1);

        $ids = array_column($managers, 'id');
        $this->assertContains(99, $ids, 'Owner (is_admin) doit apparaître même sans adhésion au store');
        $this->assertContains(10, $ids, 'membre avec employees.update sur ce store doit apparaître');
    }

    public function testGetManagersForReportFormExcludesMemberWithoutEmployeesUpdatePermission(): void
    {
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles = $this->createMock(RoleRepositoryInterface::class);
        // Un membre du store sans la permission RBAC employees.update ne doit plus
        // apparaître, même si sa colonne legacy store_user.role vaut 'manager'
        // (colonne jamais resynchronisée quand les permissions d'un rôle changent).
        $this->storeUsers->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([
            ['user_id' => 10, 'store_id' => 1, 'role' => 'manager'],
        ]);
        $this->users->method('findAll')->willReturn([]);
        $this->users->method('findById')->willReturnMap([
            [10, ['id' => 10, 'first_name' => 'Jean', 'last_name' => 'Dupont', 'employee_code' => 'EMP010']],
        ]);
        $this->assignments->expects($this->atLeastOnce())->method('findByUser')->with(10)->willReturn([
            ['id' => 1, 'user_id' => 10, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->expects($this->atLeastOnce())->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->expects($this->atLeastOnce())->method('getPermissions')->with(2)->willReturn(['shifts.view']);

        $method = new \ReflectionMethod($this->controller(), 'getManagersForReportForm');
        $method->setAccessible(true);
        $managers = $method->invoke($this->controller(), 1);

        $this->assertNotContains(10, array_column($managers, 'id'));
    }

    public function testCreateResignationReportKeepsPresetUserEvenIfNotAStoreMember(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $_GET = ['user_id' => '99'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'display_name' => 'Owner']);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([]);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(99)->willReturn(['id' => 99, 'first_name' => 'Ex', 'last_name' => 'Employee', 'employee_code' => 'EMP099']);

        $response = $this->controller()->createResignationReport($req);

        $this->assertSame(200, $response->status());
    }

    public function testShowResignationReportLogsConsultation(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $this->logRepo = $this->createMock(LogRepositoryInterface::class);
        $container = new Container();
        $container->instance(LogRepositoryInterface::class, $this->logRepo);
        Log::setContainer($container);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '20']);

        $this->resignationReports->expects($this->atLeastOnce())->method('findById')->with(20)->willReturn(['id' => 20, 'store_id' => 1, 'employee_name' => 'Bob']);
        $this->stores->method('findById')->willReturn(['id' => 1, 'name' => 'Store A']);

        $this->logRepo->expects($this->once())->method('record')->with(
            $this->anything(), $this->anything(), $this->anything(),
            'resignation_report.viewed', 'resignation_report', 20,
            $this->anything(), $this->anything(), 1,
            $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(), $this->anything(),
        );

        $response = $this->controller()->showResignationReport($req);

        $this->assertSame(200, $response->status());
    }

    public function testShowResignationReportOfAnotherStoreThrowsNotFound(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '20']);

        // Le rapport existe mais appartient au store 2, pas au store 1 de l'URL
        $this->resignationReports->expects($this->atLeastOnce())->method('findById')->with(20)->willReturn(['id' => 20, 'store_id' => 2]);

        $this->expectException(NotFoundException::class);
        $this->controller()->showResignationReport($req);
    }

    public function testStoreResignationReportDeactivatesTheEmployee(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $_POST = [
            'employee_number'    => 'E-1',
            'employee_name'      => 'Alice Martin',
            'resignation_date'   => '2026-08-01',
            'reason'             => 'Départ volontaire',
            'resignation_notice' => '',
            'notes'              => '',
            'person_in_charge'   => 'Manager X',
            'user_id'            => '5',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '1']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 5)->willReturn(['id' => 3]);
        $this->resignationReports->method('save')->willReturnCallback(fn(array $d) => $d + ['id' => 99]);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn(['id' => 5, 'is_active' => 1]);

        $captured = null;
        $this->users->expects($this->once())->method('save')->willReturnCallback(function (array $u) use (&$captured) {
            $captured = $u;
            return $u;
        });

        $response = $this->controller()->storeResignationReport($req);

        $this->assertSame(302, $response->status());
        $this->assertNotNull($captured);
        $this->assertSame(0, $captured['is_active']);
    }

    /** Audit du 13/10/2026 : la création désactivait n'importe quel compte, y compris hors du magasin. */
    public function testStoreResignationReportRefusesUserOutsideTheStore(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $_POST = ['employee_name' => 'X', 'user_id' => '77'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1]);
        $req->setRouteParams(['id' => '1']);
        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->storeUsers->method('findMembership')->willReturn(null);
        $this->users->expects($this->never())->method('save');
        $this->resignationReports->expects($this->never())->method('save');

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->controller()->storeResignationReport($req);
    }

    public function testStoreResignationReportRefusesOwnerAccountForNonOwner(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $_POST = ['employee_name' => 'Owner', 'user_id' => '7'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', [1]);
        $req->setAttribute('auth_user', ['id' => 2]);
        $req->setRouteParams(['id' => '1']);
        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->storeUsers->method('findMembership')->willReturn(['id' => 3]);
        $this->assignments->method('findByScope')->willReturn([
            ['id' => 1, 'user_id' => 7, 'role_id' => 10, 'scope_type' => 'global', 'scope_id' => null],
        ]);
        $this->roles->method('findById')->willReturn(['id' => 10, 'is_system' => 1]);
        $this->users->expects($this->never())->method('save');

        $this->expectException(\kintai\Core\Exceptions\ForbiddenException::class);
        $this->controller()->storeResignationReport($req);
    }

    public function testDeletePermanentlyRefusesUserWhoBelongsToAnUnmanagedStore(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', [1]);
        $req->setAttribute('auth_user', ['id' => 2]);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);
        $this->resignationReports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1, 'user_id' => 5]);
        $this->assignments->method('findByScope')->willReturn([]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByUser')->with(5)->willReturn([['store_id' => 1], ['store_id' => 4]]);
        $this->users->expects($this->never())->method('delete');
        $this->resignationReports->expects($this->never())->method('delete');

        $this->expectException(\kintai\Core\Exceptions\ForbiddenException::class);
        $this->controller()->deleteResignationReportPermanently($req);
    }

    public function testUpdateResignationReportMergesPostFieldsAndRedirects(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $_POST = [
            'user_id'            => '5',
            'employee_number'    => 'E-42',
            'employee_name'      => 'Alice Martin',
            'resignation_date'   => '2026-08-01',
            'reason'             => '',
            'resignation_notice' => '',
            'notes'              => '',
            'person_in_charge'   => '',
        ];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 5)->willReturn(['id' => 3]);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->resignationReports->expects($this->atLeastOnce())->method('findById')->with(10)
            ->willReturn(['id' => 10, 'store_id' => 1, 'employee_name' => 'Ancien nom']);

        $this->resignationReports->expects($this->once())->method('save')->with($this->callback(
            fn(array $data) =>
                $data['id'] === 10
                && $data['user_id'] === 5
                && $data['employee_name'] === 'Alice Martin'
        ));

        $response = $this->controller()->updateResignationReport($req);

        $this->assertSame(302, $response->status());
    }

    /** Audit du 13/10/2026 : un rapport ne peut pas être lié à un compte étranger au magasin. */
    public function testUpdateResignationReportRejectsUserOutsideTheStore(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $_POST = ['user_id' => '99', 'employee_name' => 'X'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->resignationReports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1]);
        $this->storeUsers->method('findMembership')->willReturn(null);
        $this->resignationReports->expects($this->never())->method('save');

        $this->expectException(\kintai\Core\Exceptions\ValidationException::class);
        $this->controller()->updateResignationReport($req);
    }

    public function testDeleteResignationReportDeletesAndLogs(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->resignationReports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1]);
        $this->resignationReports->expects($this->once())->method('delete')->with(10);

        $response = $this->controller()->deleteResignationReport($req);

        $this->assertSame(302, $response->status());
    }

    public function testDeleteResignationReportReactivatesTheAssociatedAccount(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        // Rapport appliqué (deactivated_at renseigné) : sa suppression rend l'accès.
        $this->resignationReports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1, 'user_id' => 5, 'deactivated_at' => '2026-08-01 09:00:00']);
        $this->resignationReports->expects($this->once())->method('delete')->with(10);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn(['id' => 5, 'is_active' => 0]);

        $captured = null;
        $this->users->expects($this->once())->method('save')->willReturnCallback(function (array $u) use (&$captured) {
            $captured = $u;
            return $u;
        });

        $response = $this->controller()->deleteResignationReport($req);

        $this->assertSame(302, $response->status());
        $this->assertSame(1, $captured['is_active']);
    }

    public function testDeleteResignationReportWithoutLinkedUserDoesNotTouchUsers(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->resignationReports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1]);
        $this->resignationReports->expects($this->once())->method('delete')->with(10);
        $this->users->expects($this->never())->method('save');

        $response = $this->controller()->deleteResignationReport($req);

        $this->assertSame(302, $response->status());
    }

    /** Audit n°8 : le compte est anonymisé (contrat 1.21), plus seulement supprimé en douceur avec ses données. */
    public function testDeleteResignationReportPermanentlyDeletesReportAndAnonymizesUser(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->resignationReports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1, 'user_id' => 5]);
        $this->resignationReports->expects($this->once())->method('delete')->with(10);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn(['id' => 5, 'email' => 'alice@example.test', 'phone' => '0600000000']);
        $this->users->expects($this->never())->method('delete');
        $this->users->expects($this->once())->method('save')->with($this->callback(
            fn(array $u) => $u['id'] === 5 && $u['email'] === 'deleted-5@kintai.local' && $u['phone'] === null && $u['is_active'] === 0
        ));

        $response = $this->controller()->deleteResignationReportPermanently($req);

        $this->assertSame(302, $response->status());
    }

    public function testDeleteResignationReportPermanentlyWithoutLinkedUserOnlyDeletesReport(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->resignationReports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1]);
        $this->resignationReports->expects($this->once())->method('delete')->with(10);
        $this->users->expects($this->never())->method('delete');

        $response = $this->controller()->deleteResignationReportPermanently($req);

        $this->assertSame(302, $response->status());
    }

    public function testReactivateUserReactivatesTheAssociatedAccount(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->resignationReports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1, 'user_id' => 5]);
        $this->users->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn(['id' => 5, 'is_active' => 0]);

        $captured = null;
        $this->users->expects($this->once())->method('save')->willReturnCallback(function (array $u) use (&$captured) {
            $captured = $u;
            return $u;
        });

        $response = $this->controller()->reactivateUser($req);

        $this->assertSame(302, $response->status());
        $this->assertSame(1, $captured['is_active']);
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

    // -------------------------------------------------------------------------
    // Audit n°8 (D2) : désactivation à la date de démission, jamais sur soi ni sur un autre gérant
    // -------------------------------------------------------------------------

    private function storeRequest(int $callerId, string $date, int $userId = 5): Request
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $_POST = ['employee_name' => 'Alice', 'resignation_date' => $date, 'user_id' => (string) $userId];
        $req = new Request();
        $req->setAttribute('managed_store_ids', [1]);
        $req->setAttribute('auth_user', ['id' => $callerId]);
        $req->setRouteParams(['id' => '1']);
        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1]);
        $this->storeUsers->method('findMembership')->willReturn(['id' => 3]);
        return $req;
    }

    public function testAFutureResignationDateDoesNotDeactivateTheAccountYet(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $req = $this->storeRequest(2, date('Y-m-d', strtotime('+20 days')));
        $this->resignationReports->expects($this->once())->method('save')->willReturnCallback(fn(array $d) => $d + ['id' => 99]);
        $this->users->expects($this->never())->method('save');

        $this->assertSame(302, $this->controller()->storeResignationReport($req)->status());
    }

    public function testTodaysResignationDeactivatesNowAndMarksTheReport(): void
    {
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $req = $this->storeRequest(2, date('Y-m-d'));
        $saved = [];
        $this->resignationReports->method('save')->willReturnCallback(function (array $d) use (&$saved) {
            $saved[] = $d;
            return $d + ['id' => 99];
        });
        $this->users->expects($this->atLeastOnce())->method('findById')->with(5)->willReturn(['id' => 5, 'is_active' => 1]);
        $this->users->expects($this->once())->method('save')->with($this->callback(fn(array $u) => $u['is_active'] === 0));

        $this->controller()->storeResignationReport($req);

        $this->assertNotEmpty($saved[1]['deactivated_at'] ?? null, 'rapport marqué appliqué');
    }

    public function testAManagerCannotFileTheirOwnResignation(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $req = $this->storeRequest(5, date('Y-m-d'));
        $this->resignationReports->expects($this->never())->method('save');
        $this->users->expects($this->never())->method('save');

        $this->expectException(\kintai\Core\Exceptions\ForbiddenException::class);
        $this->controller()->storeResignationReport($req);
    }

    public function testAManagerCannotFileAnotherManagersResignation(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $req = $this->storeRequest(2, date('Y-m-d'));
        $this->assignments->method('findByScope')->willReturn([]);
        $this->assignments->method('findByUser')->willReturnCallback(fn(int $u) => $u === 5
            ? [['id' => 4, 'user_id' => 5, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1]]
            : []);
        $this->roles->method('findById')->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->willReturn(['shifts.view']);
        $this->resignationReports->expects($this->never())->method('save');

        $this->expectException(\kintai\Core\Exceptions\ForbiddenException::class);
        $this->controller()->storeResignationReport($req);
    }

    public function testAnOwnerCanFileAManagersResignation(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $req = $this->storeRequest(7, date('Y-m-d', strtotime('+3 days')));
        $this->assignments->method('findByScope')->willReturn([
            ['id' => 1, 'user_id' => 7, 'role_id' => 10, 'scope_type' => 'global', 'scope_id' => null],
        ]);
        $this->assignments->method('findByUser')->willReturn([
            ['id' => 4, 'user_id' => 5, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->willReturnCallback(fn(int $id) => ['id' => $id, 'is_system' => $id === 10 ? 1 : 0]);
        $this->roles->method('getPermissions')->willReturn(['shifts.view']);
        $this->resignationReports->expects($this->once())->method('save')->willReturnCallback(fn(array $d) => $d + ['id' => 99]);

        $this->assertSame(302, $this->controller()->storeResignationReport($req)->status());
    }

    public function testDeletingAReportNotYetAppliedLeavesTheAccountAlone(): void
    {
        $this->resignationReports = $this->createMock(ResignationReportRepositoryInterface::class);
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);
        $this->resignationReports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn(['id' => 10, 'store_id' => 1, 'user_id' => 5, 'deactivated_at' => null]);
        $this->users->expects($this->never())->method('save');

        $this->controller()->deleteResignationReport($req);
    }
}
