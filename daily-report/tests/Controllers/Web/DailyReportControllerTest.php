<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\DailyReport\Controllers\Web;


use kintai\Bundles\Installed\DailyReport\Controllers\Web\DailyReportController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Mail\MailerService;
use kintai\Bundles\Installed\DailyReport\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TranslationRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportMailService;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportPdfService;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportPermissionService;
use kintai\Core\Services\NotificationService;
use kintai\Core\Services\TranslationService;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Deux employés de magasins différents peuvent partager le même nom de famille.
 * Un shift mal assigné (import Excel, correction manuelle...) au mauvais user_id
 * ne doit jamais faire apparaître un staff d'un autre magasin dans la section
 * "shifts à venir / du lendemain" du formulaire de rapport journalier.
 */
final class DailyReportControllerTest extends TestCase
{
    private ShiftRepositoryInterface&Stub $shifts;
    private ShiftTypeRepositoryInterface&Stub $shiftTypes;
    private UserRepositoryInterface&Stub $users;
    private StoreUserRepositoryInterface&Stub $storeUsers;
    private StoreRepositoryInterface&Stub $stores;
    private DailyReportRepositoryInterface&Stub $reports;
    private RoleAssignmentRepositoryInterface&Stub $assignments;
    private RoleRepositoryInterface&Stub $roles;
    private NotificationService&Stub $notifs;
    private ?DailyReportController $controller = null;

    protected function setUp(): void
    {
        $this->shifts     = $this->createStub(ShiftRepositoryInterface::class);
        $this->shiftTypes = $this->createStub(ShiftTypeRepositoryInterface::class);
        $this->users      = $this->createStub(UserRepositoryInterface::class);
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->notifs     = $this->createStub(NotificationService::class);

        $this->assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $this->roles = $this->createStub(RoleRepositoryInterface::class);

        $this->reports = $this->createStub(DailyReportRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): DailyReportController
    {
        if ($this->controller === null) {
            $translations = new TranslationService(
                $this->createStub(TranslationRepositoryInterface::class),
                $this->createStub(LanguageRepositoryInterface::class),
            );
            $permissionService = new PermissionService($this->assignments, $this->roles);
            $permissions = new DailyReportPermissionService($permissionService);
            $pdfService = new DailyReportPdfService(
                new ViewRenderer(sys_get_temp_dir()),
                $translations,
                $this->shifts,
                $this->shiftTypes,
                $this->users,
            );
            $mailService = new DailyReportMailService(
                $permissions,
                new MailerService(['driver' => 'native', 'from' => ['address' => 'test@kintai.test', 'name' => 'Kintai']]),
                $translations,
                new \kintai\Core\Services\UserSelection($this->storeUsers, $this->users, $permissionService),
            );
            $this->controller = new DailyReportController(
                new ViewRenderer(sys_get_temp_dir()),
                $this->reports,
                $this->stores,
                $this->storeUsers,
                $this->users,
                $permissions,
                $pdfService,
                $mailService,
                new AuditLogger(),
                $translations,
                $this->shifts,
                $this->shiftTypes,
                $this->notifs,
                $permissionService,
                new \kintai\Core\Services\UserSelection($this->storeUsers, $this->users, $permissionService),
            );
        }

        return $this->controller;
    }

    public function testGetFormShiftRowsExcludesShiftsFromUsersNotMemberOfStore(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftTypes = $this->createMock(ShiftTypeRepositoryInterface::class);
        $storeId = 1;
        $date    = '2026-08-01';

        // Deux "Dupont" homonymes : #10 appartient au magasin 1, #20 appartient à un
        // autre magasin. Un import erroné a néanmoins créé un shift store_id=1 pour #20.
        $this->shifts->expects($this->atLeastOnce())->method('findByDate')->with($storeId, $date)->willReturn([
            ['user_id' => 10, 'shift_type_id' => 0, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'duration_minutes' => 480, 'pause_minutes' => 60, 'deleted_at' => null],
            ['user_id' => 20, 'shift_type_id' => 0, 'start_time' => '10:00:00', 'end_time' => '18:00:00', 'duration_minutes' => 480, 'pause_minutes' => 60, 'deleted_at' => null],
        ]);
        $this->shiftTypes->expects($this->atLeastOnce())->method('findByStore')->with($storeId)->willReturn([]);

        $this->storeUsers->method('findMembership')->willReturnCallback(
            fn(int $sid, int $uid) => ($sid === $storeId && $uid === 10) ? ['id' => 1, 'store_id' => $sid, 'user_id' => $uid] : null
        );

        $this->users->method('findById')->willReturnMap([
            [10, ['id' => 10, 'last_name' => 'Dupont', 'first_name' => 'Jean']],
            [20, ['id' => 20, 'last_name' => 'Dupont', 'first_name' => 'Marie']],
        ]);

        $method = new \ReflectionMethod($this->controller(), 'getFormShiftRows');
        $method->setAccessible(true);
        $rows = $method->invoke($this->controller(), $storeId, $date);

        $this->assertCount(1, $rows, 'le shift du user #20 (non membre du magasin) doit être exclu');
        $this->assertSame('Dupont Jean', $rows[0]['employee']);
    }

    public function testSubmitNotifiesManagersWithApprovePermission(): void
    {
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->reports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn([
            'id' => 10, 'store_id' => 1, 'status' => 'draft', 'author_id' => 9, 'report_date' => '2026-08-05',
        ]);
        $this->reports->method('save')->willReturnCallback(fn(array $d) => $d);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 9)->willReturn(['store_id' => 1, 'user_id' => 9]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findByStore')->with(1)->willReturn([
            ['user_id' => 9],  // l'auteur, avec seulement le droit de soumettre (pas de valider)
            ['user_id' => 20], // manager avec daily_reports.approve
        ]);
        $this->users->method('findById')->willReturnMap([
            [9, ['id' => 9]],
            [20, ['id' => 20]],
        ]);
        // Régression : soumettre son propre rapport n'est plus du libre-service, l'auteur a
        // donc ici besoin de daily_reports.submit (accordée seule, sans .approve) — voir
        // CHANGELOG.
        $this->assignments->method('findByUser')->willReturnMap([
            [9, [['id' => 2, 'user_id' => 9, 'role_id' => 3, 'scope_type' => 'store', 'scope_id' => 1]]],
            [20, [['id' => 1, 'user_id' => 20, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1]]],
        ]);
        $this->roles->method('findById')->willReturnMap([
            [2, ['id' => 2, 'is_system' => 0]],
            [3, ['id' => 3, 'is_system' => 0]],
        ]);
        $this->roles->method('getPermissions')->willReturnMap([
            [2, ['daily_reports.submit', 'daily_reports.approve']],
            [3, ['daily_reports.submit']],
        ]);

        $this->notifs->expects($this->once())->method('notifyMany')
            ->with([20], 'daily_report_submitted', $this->anything(), $this->anything(), 10);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->controller()->submit($req);
    }

    public function testValidateNotifiesAuthorButNotSelf(): void
    {
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles = $this->createMock(RoleRepositoryInterface::class);
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->reports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn([
            'id' => 10, 'store_id' => 1, 'status' => 'submitted', 'author_id' => 9, 'report_date' => '2026-08-05',
        ]);
        $this->reports->method('save')->willReturnCallback(fn(array $d) => $d);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->assignments->expects($this->atLeastOnce())->method('findByUser')->with(20)->willReturn([
            ['id' => 1, 'user_id' => 20, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->expects($this->atLeastOnce())->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->expects($this->atLeastOnce())->method('getPermissions')->with(2)->willReturn(['daily_reports.approve']);

        $this->notifs->expects($this->once())->method('notify')
            ->with(9, 'daily_report_validated', $this->anything(), $this->anything(), 10);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 20]);
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->controller()->validate($req);
    }

    /** Ouvrir un rapport qu'on a le droit de voir marque ses notifications comme lues. */
    public function testShowMarksTheReportNotificationsAsRead(): void
    {
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->storeUsers->method('findMembership')->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->notifs = $this->createMock(NotificationService::class);
        $this->assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $this->roles = $this->createStub(RoleRepositoryInterface::class);
        $this->reports = $this->createStub(DailyReportRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->stores->method('findById')->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->reports->method('findById')->willReturn([
            'id' => 10, 'store_id' => 1, 'status' => 'submitted', 'author_id' => 9, 'validated_by' => null, 'deleted_at' => null, 'report_date' => '2026-08-05',
        ]);
        $this->assignments->method('findByUser')->willReturn([
            ['id' => 1, 'user_id' => 20, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->method('findById')->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->method('getPermissions')->willReturn(['daily_reports.view']);

        $this->notifs->expects($this->once())->method('markReadFor')->with(20, 'daily_report', 10);

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 20]);
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        try {
            $this->controller()->show($req);
        } catch (\Throwable) {
            // Le rendu de la vue n'est pas l'objet du test : « lu » est marqué avant.
        }
    }

    /** Sans le droit de voir le rapport, rien n'est marqué lu. */
    public function testShowByAUserWhoCannotSeeTheReportMarksNothing(): void
    {
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->storeUsers->method('findMembership')->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->notifs = $this->createMock(NotificationService::class);
        $this->reports = $this->createStub(DailyReportRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->stores->method('findById')->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->reports->method('findById')->willReturn([
            'id' => 10, 'store_id' => 1, 'status' => 'submitted', 'author_id' => 9, 'report_date' => '2026-08-05',
        ]);

        $this->notifs->expects($this->never())->method('markReadFor');

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 20]);
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->expectException(\kintai\Core\Exceptions\ForbiddenException::class);
        $this->controller()->show($req);
    }

    public function testValidateBySelfDoesNotSelfNotify(): void
    {
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->notifs = $this->createMock(NotificationService::class);
        $this->assignments = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $this->roles = $this->createMock(RoleRepositoryInterface::class);
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);
        $this->reports->expects($this->atLeastOnce())->method('findById')->with(10)->willReturn([
            'id' => 10, 'store_id' => 1, 'status' => 'submitted', 'author_id' => 9, 'report_date' => '2026-08-05',
        ]);
        $this->reports->method('save')->willReturnCallback(fn(array $d) => $d);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 9)->willReturn(['store_id' => 1, 'user_id' => 9]);
        $this->assignments->expects($this->atLeastOnce())->method('findByUser')->with(9)->willReturn([
            ['id' => 1, 'user_id' => 9, 'role_id' => 2, 'scope_type' => 'store', 'scope_id' => 1],
        ]);
        $this->roles->expects($this->atLeastOnce())->method('findById')->with(2)->willReturn(['id' => 2, 'is_system' => 0]);
        $this->roles->expects($this->atLeastOnce())->method('getPermissions')->with(2)->willReturn(['daily_reports.approve']);

        $this->notifs->expects($this->never())->method('notify');

        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 9]);
        $req->setAttribute('managed_store_ids', null);
        $req->setRouteParams(['id' => '1', 'rid' => '10']);

        $this->controller()->validate($req);
    }
}
