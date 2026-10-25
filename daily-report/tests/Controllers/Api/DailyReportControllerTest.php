<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\DailyReport\Controllers\Api;


use kintai\Bundles\Installed\DailyReport\Controllers\Api\DailyReportController;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Exceptions\NotFoundException;
use kintai\Bundles\Installed\DailyReport\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Request;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportPermissionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * ApiDailyReportController n'appelait jusqu'ici jamais DailyReportPermissionService :
 * show/update/destroy/submit/validate opéraient sur n'importe quel rapport de
 * n'importe quel store pour tout porteur de token authentifié. Ce test vérifie
 * qu'un simple membre du store (sans permission RBAC dédiée) ne peut agir que
 * sur son propre brouillon, et qu'un gestionnaire avec la permission adéquate
 * peut agir sur n'importe quel rapport du store.
 */
final class DailyReportControllerTest extends TestCase
{
    private DailyReportRepositoryInterface&Stub $reports;
    private StoreRepositoryInterface&Stub $stores;
    private StoreUserRepositoryInterface&Stub $storeUsers;
    private RoleAssignmentRepositoryInterface&Stub $assignments;
    private RoleRepositoryInterface&Stub $roles;
    private ?DailyReportController $controller = null;

    protected function setUp(): void
    {
        $this->reports = $this->createStub(DailyReportRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->storeUsers = $this->createStub(StoreUserRepositoryInterface::class);
        $this->assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $this->roles = $this->createStub(RoleRepositoryInterface::class);

        $this->stores->method('findById')->willReturnCallback(fn($a0) => $a0 === 1 ? ['id' => 1, 'name' => 'Store A'] : null);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): DailyReportController
    {
        if ($this->controller === null) {
            $this->controller = new DailyReportController(
                $this->reports,
                $this->stores,
                $this->storeUsers,
                new DailyReportPermissionService(new PermissionService($this->assignments, $this->roles)),
            );
        }

        return $this->controller;
    }

    private function requestWithJson(array $json, array $routeParams = [], array $authUser = ['id' => 20]): Request
    {
        $req = new Request();
        $ref = new \ReflectionProperty(Request::class, 'jsonBody');
        $ref->setAccessible(true);
        $ref->setValue($req, $json);
        $req->setRouteParams($routeParams);
        $req->setAttribute('auth_user', $authUser);
        return $req;
    }

    private function noPermissions(): void
    {
        $this->assignments->method('findByUser')->willReturn([]);
    }

    private function grant(string $key, int $userId = 20, int $storeId = 1): void
    {
        $this->assignments->method('findByUser')->willReturnCallback(fn($a0) => $a0 === $userId ? [
            ['id' => 1, 'user_id' => $userId, 'role_id' => 5, 'scope_type' => 'store', 'scope_id' => $storeId],
        ] : []);
        $this->roles->method('findById')->willReturnCallback(fn($a0) => $a0 === 5 ? ['id' => 5, 'is_system' => 0] : null);
        $this->roles->method('getPermissions')->willReturnCallback(fn($a0) => $a0 === 5 ? [$key] : []);
    }

    // -------------------------------------------------------------------------
    // update() — un membre sans permission ne peut éditer que son propre brouillon
    // -------------------------------------------------------------------------

    public function testMemberCannotUpdateColleaguesSubmittedReport(): void
    {
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->noPermissions();
        $this->reports->expects($this->atLeastOnce())->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 99, 'status' => 'submitted',
        ]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->reports->expects($this->never())->method('save');

        $req = $this->requestWithJson(['sales_total' => 100], ['id' => '42']);
        $response = $this->controller()->update($req);

        $this->assertSame(403, $response->status());
    }

    public function testMemberCanUpdateOwnDraftReport(): void
    {
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->noPermissions();
        $this->reports->expects($this->atLeastOnce())->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 20, 'status' => 'draft',
        ]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->reports->expects($this->once())->method('save')->willReturnCallback(fn(array $d) => $d);

        $req = $this->requestWithJson(['sales_total' => 100], ['id' => '42']);
        $response = $this->controller()->update($req);

        $this->assertSame(200, $response->status());
    }

    public function testManagerWithPermissionCanUpdateAnyReportInStore(): void
    {
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->grant('daily_reports.update');
        $this->reports->expects($this->atLeastOnce())->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 99, 'status' => 'submitted',
        ]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 20)->willReturn(null);
        $this->reports->expects($this->once())->method('save')->willReturnCallback(fn(array $d) => $d);

        $req = $this->requestWithJson(['sales_total' => 100], ['id' => '42']);
        $response = $this->controller()->update($req);

        $this->assertSame(200, $response->status());
    }

    // -------------------------------------------------------------------------
    // destroy() — exige daily_reports.delete, jamais de fallback libre-service
    // -------------------------------------------------------------------------

    public function testMemberCannotDeleteOwnDraftWithoutDeletePermission(): void
    {
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->noPermissions();
        $this->reports->expects($this->atLeastOnce())->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 20, 'status' => 'draft', 'deleted_at' => null,
        ]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->reports->expects($this->never())->method('delete');

        $req = $this->requestWithJson([], ['id' => '42']);
        $response = $this->controller()->destroy($req);

        $this->assertSame(403, $response->status());
    }

    public function testManagerWithDeletePermissionCanDelete(): void
    {
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->grant('daily_reports.delete');
        $this->reports->expects($this->atLeastOnce())->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 99, 'status' => 'submitted', 'deleted_at' => null,
        ]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 20)->willReturn(null);
        $this->reports->expects($this->once())->method('delete')->with(42);

        $req = $this->requestWithJson([], ['id' => '42']);
        $response = $this->controller()->destroy($req);

        $this->assertSame(204, $response->status());
    }

    // -------------------------------------------------------------------------
    // validate() — un manager avec daily_reports.approve peut valider n'importe
    // quel rapport soumis du store, un simple membre jamais
    // -------------------------------------------------------------------------

    public function testManagerWithApprovePermissionCanValidateAnySubmittedReport(): void
    {
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->grant('daily_reports.approve');
        $this->reports->expects($this->atLeastOnce())->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 99, 'status' => 'submitted',
        ]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 20)->willReturn(null);
        $this->reports->expects($this->once())->method('save')->willReturnCallback(fn(array $d) => $d);

        $req = $this->requestWithJson([], ['id' => '42']);
        $response = $this->controller()->validate($req);

        $this->assertSame(200, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertSame('validated', $body['status']);
    }

    public function testMemberWithoutApprovePermissionCannotValidate(): void
    {
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->noPermissions();
        $this->reports->expects($this->atLeastOnce())->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 20, 'status' => 'submitted',
        ]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->reports->expects($this->never())->method('save');

        $req = $this->requestWithJson([], ['id' => '42']);
        $response = $this->controller()->validate($req);

        $this->assertSame(403, $response->status());
    }

    // -------------------------------------------------------------------------
    // show() — sans permission, un membre ne voit que ses propres rapports
    // -------------------------------------------------------------------------

    public function testMemberCannotViewColleaguesReportWithoutPermission(): void
    {
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->noPermissions();
        $this->reports->expects($this->atLeastOnce())->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 99, 'status' => 'submitted',
        ]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);

        $req = $this->requestWithJson([], ['id' => '42']);
        $response = $this->controller()->show($req);

        $this->assertSame(403, $response->status());
    }

    public function testMemberCanViewOwnReport(): void
    {
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->noPermissions();
        $this->reports->expects($this->atLeastOnce())->method('findById')->with(42)->willReturn([
            'id' => 42, 'store_id' => 1, 'author_id' => 20, 'status' => 'submitted',
        ]);
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);

        $req = $this->requestWithJson([], ['id' => '42']);
        $response = $this->controller()->show($req);

        $this->assertSame(200, $response->status());
    }

    public function testShowThrowsNotFoundForMissingReport(): void
    {
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->noPermissions();
        $this->reports->expects($this->atLeastOnce())->method('findById')->with(999)->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->controller()->show($this->requestWithJson([], ['id' => '999']));
    }

    // -------------------------------------------------------------------------
    // store() — réservé au détenteur de daily_reports.create, plus de libre-service membre
    // -------------------------------------------------------------------------

    public function testNonMemberCannotCreateReport(): void
    {
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->noPermissions();
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 20)->willReturn(null);
        $this->reports->expects($this->never())->method('save');

        $req = $this->requestWithJson(['store_id' => 1, 'report_date' => '2026-08-05']);
        $response = $this->controller()->store($req);

        $this->assertSame(403, $response->status());
    }

    /**
     * Régression : le libre-service "tout membre du store peut créer son propre rapport" a
     * été retiré à la demande explicite du client — la création requiert désormais
     * daily_reports.create, même pour un simple membre du store. Voir CHANGELOG.
     */
    public function testStoreMemberWithoutCreatePermissionCannotCreateReport(): void
    {
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->noPermissions();
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->reports->expects($this->never())->method('save');

        $req = $this->requestWithJson(['store_id' => 1, 'report_date' => '2026-08-05']);
        $response = $this->controller()->store($req);

        $this->assertSame(403, $response->status());
    }

    /** daily_reports.create accordé seul (ex. à un employé spécifique) suffit à créer. */
    public function testMemberWithCreatePermissionCanCreateOwnReport(): void
    {
        $this->reports = $this->createMock(DailyReportRepositoryInterface::class);
        $this->storeUsers = $this->createMock(StoreUserRepositoryInterface::class);
        $this->grant('daily_reports.create');
        $this->storeUsers->expects($this->atLeastOnce())->method('findMembership')->with(1, 20)->willReturn(['store_id' => 1, 'user_id' => 20]);
        $this->reports->expects($this->once())->method('save')->willReturnCallback(fn(array $d) => $d + ['id' => 55]);

        $req = $this->requestWithJson(['store_id' => 1, 'report_date' => '2026-08-05']);
        $response = $this->controller()->store($req);

        $this->assertSame(201, $response->status());
        $body = json_decode($response->body(), true);
        $this->assertSame(20, $body['author_id']);
    }
}
