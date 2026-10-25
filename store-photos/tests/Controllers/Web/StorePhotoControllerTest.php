<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\StorePhoto\Controllers\Web;


use kintai\Bundles\Installed\StorePhoto\Controllers\Web\StorePhotoController;
use kintai\Core\Exceptions\ForbiddenException;
use kintai\Core\Repositories\AppSettingsRepositoryInterface;
use kintai\Bundles\Installed\StorePhoto\Repositories\StorePhotoRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Response;
use kintai\Core\Services\AuditLogger;
use kintai\Core\Services\ImageCompressionService;
use kintai\Core\Services\NotificationService;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

final class StorePhotoControllerTest extends TestCase
{
    private StorePhotoRepositoryInterface&Stub $photos;
    private StoreRepositoryInterface&Stub $stores;
    private AppSettingsRepositoryInterface&Stub $appSettings;
    private NotificationService $notifs;
    private ?StorePhotoController $controller = null;

    protected function setUp(): void
    {
        $viewDir = sys_get_temp_dir() . '/kintai-store-photos-views';
        $this->ensureViewFile($viewDir, 'store-photos');
        $this->ensureViewFile($viewDir, 'store-photos-settings');
        $this->ensureViewFile($viewDir, 'store-photos-form');
        $this->ensureViewFile($viewDir, 'store-photos-detail');
        $this->ensureViewFile(sys_get_temp_dir(), 'layout.app');

        $this->photos = $this->createStub(StorePhotoRepositoryInterface::class);
        $this->stores = $this->createStub(StoreRepositoryInterface::class);
        $this->appSettings = $this->createStub(AppSettingsRepositoryInterface::class);
        $this->notifs = $this->createStub(NotificationService::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function controller(): StorePhotoController
    {
        if ($this->controller === null) {
            $viewDir = sys_get_temp_dir() . '/kintai-store-photos-views';
            $view = new ViewRenderer(sys_get_temp_dir());
            $view->addNamespace('store-photos', $viewDir);
            $this->controller = new StorePhotoController(
                $view,
                $this->photos,
                $this->stores,
                $this->appSettings,
                new AuditLogger(),
                new ImageCompressionService(),
                $this->notifs,
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

    public function testIndexRendersSubmissionsFilteredByStore(): void
    {
        $this->stores->method('findAll')->willReturn([
            ['id' => 1, 'name' => 'Store A'],
            ['id' => 2, 'name' => 'Store B'],
        ]);
        $this->photos->method('findAllSubmissions')->willReturn([
            ['id' => 10, 'store_id' => 1],
            ['id' => 11, 'store_id' => 2],
        ]);
        $this->photos->method('findImagesBySubmission')->willReturn([]);

        $_GET['store_id'] = '1';
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller()->index($req);

        $this->assertSame(200, $response->status());
    }

    /**
     * Régression IDOR : index() appelait findAllSubmissions(null, ...) sans jamais tenir
     * compte de managed_store_ids, remontant les envois de TOUS les stores à un manager
     * restreint. Voir CHANGELOG.
     */
    public function testIndexScopesSubmissionsToManagedStoresOnly(): void
    {
        $this->photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $this->stores->method('findAll')->willReturn([
            ['id' => 1, 'name' => 'Store A'],
            ['id' => 2, 'name' => 'Store B'],
        ]);
        $this->photos->expects($this->once())->method('findAllSubmissions')
            ->with([1], 100)
            ->willReturn([['id' => 10, 'store_id' => 1]]);
        $this->photos->method('findImagesBySubmission')->willReturn([]);

        $req = new Request();
        $req->setAttribute('managed_store_ids', [1]);

        $response = $this->controller()->index($req);

        $this->assertSame(200, $response->status());
    }

    /** Un ?store_id= hors du périmètre géré est ignoré plutôt que d'être appliqué tel quel. */
    public function testIndexIgnoresStoreIdFilterOutsideManagedScope(): void
    {
        $this->photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->photos->expects($this->atLeastOnce())->method('findAllSubmissions')->with([1], 100)->willReturn([
            ['id' => 10, 'store_id' => 1],
        ]);
        $this->photos->method('findImagesBySubmission')->willReturn([]);

        $_GET['store_id'] = '2'; // store non géré par ce manager
        $req = new Request();
        $req->setAttribute('managed_store_ids', [1]);

        $response = $this->controller()->index($req);

        $this->assertSame(200, $response->status());
    }

    /**
     * Le filtre ?date= doit restreindre les envois au jour choisi, et la liste
     * des jours disponibles (pour le sélecteur) doit ignorer ce filtre pour
     * continuer à proposer tous les jours du store/périmètre sélectionné.
     */
    public function testIndexFiltersSubmissionsByDate(): void
    {
        $base = sys_get_temp_dir() . '/kintai-store-photos-date-test-' . uniqid();
        mkdir($base . '/store-photos', 0777, true);
        mkdir($base . '/layout', 0777, true);
        file_put_contents(
            $base . '/store-photos/store-photos.php',
            '<?php echo implode(",", array_column($submissions, "id")) . "|" . implode(",", array_keys($availableDates)) . "|" . $filterDate;'
        );
        file_put_contents($base . '/layout/app.php', '<?php echo $content;');

        $view = new ViewRenderer($base);
        $view->addNamespace('store-photos', $base . '/store-photos');

        $controller = new StorePhotoController(
            $view,
            $this->photos,
            $this->stores,
            $this->appSettings,
            new AuditLogger(),
            new ImageCompressionService(),
            $this->notifs,
        );

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->photos->method('findAllSubmissions')->willReturn([
            ['id' => 10, 'store_id' => 1, 'created_at' => '2026-09-08 10:00:00'],
            ['id' => 11, 'store_id' => 1, 'created_at' => '2026-09-07 09:00:00'],
        ]);
        $this->photos->method('findImagesBySubmission')->willReturn([]);

        $_GET['date'] = '2026-09-07';
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $controller->index($req);

        $this->assertSame(200, $response->status());
        $this->assertSame('11|2026-09-08,2026-09-07|2026-09-07', $response->body());
    }

    /** Un ?date= qui ne correspond à aucun envoi visible est silencieusement ignoré. */
    public function testIndexIgnoresUnknownDateFilter(): void
    {
        $base = sys_get_temp_dir() . '/kintai-store-photos-date-test-' . uniqid();
        mkdir($base . '/store-photos', 0777, true);
        mkdir($base . '/layout', 0777, true);
        file_put_contents(
            $base . '/store-photos/store-photos.php',
            '<?php echo implode(",", array_column($submissions, "id")) . "|" . $filterDate;'
        );
        file_put_contents($base . '/layout/app.php', '<?php echo $content;');

        $view = new ViewRenderer($base);
        $view->addNamespace('store-photos', $base . '/store-photos');

        $controller = new StorePhotoController(
            $view,
            $this->photos,
            $this->stores,
            $this->appSettings,
            new AuditLogger(),
            new ImageCompressionService(),
            $this->notifs,
        );

        $this->stores->method('findAll')->willReturn([['id' => 1, 'name' => 'Store A']]);
        $this->photos->method('findAllSubmissions')->willReturn([
            ['id' => 10, 'store_id' => 1, 'created_at' => '2026-09-08 10:00:00'],
        ]);
        $this->photos->method('findImagesBySubmission')->willReturn([]);

        $_GET['date'] = '2099-01-01';
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $controller->index($req);

        $this->assertSame(200, $response->status());
        $this->assertSame('10|', $response->body());
    }

    // -------------------------------------------------------------------------
    // show()
    // -------------------------------------------------------------------------

    /**
     * Régression IDOR : show() ne vérifiait jamais l'appartenance au store, un manager
     * pouvait consulter le détail d'un envoi de n'importe quel autre store en changeant
     * l'id dans l'URL. Voir CHANGELOG.
     */
    public function testShowForbiddenForSubmissionOutsideManagedScope(): void
    {
        $this->photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $this->photos->expects($this->atLeastOnce())->method('findSubmissionById')->with(10)->willReturn(['id' => 10, 'store_id' => 2]);

        $req = new Request();
        $req->setRouteParams(['id' => '10']);
        $req->setAttribute('managed_store_ids', [1]);

        $this->expectException(ForbiddenException::class);
        $this->controller()->show($req);
    }

    public function testShowSucceedsForSubmissionWithinManagedScope(): void
    {
        $this->photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $this->photos->expects($this->atLeastOnce())->method('findSubmissionById')->with(10)->willReturn(['id' => 10, 'store_id' => 1]);
        $this->photos->expects($this->atLeastOnce())->method('findImagesBySubmission')->with(10)->willReturn([]);
        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);

        $req = new Request();
        $req->setRouteParams(['id' => '10']);
        $req->setAttribute('managed_store_ids', [1]);

        $response = $this->controller()->show($req);

        $this->assertSame(200, $response->status());
    }

    /** Ouvrir l'envoi marque comme lue sa notification d'archivage, seulement pour un utilisateur connu. */
    public function testShowMarksTheArchiveNotificationAsRead(): void
    {
        $this->photos = $this->createStub(StorePhotoRepositoryInterface::class);
        $this->photos->method('findSubmissionById')->willReturn(['id' => 10, 'store_id' => 1]);
        $this->photos->method('findImagesBySubmission')->willReturn([]);
        $this->notifs = $this->createMock(NotificationService::class);
        $this->notifs->expects($this->once())->method('markReadFor')->with(7, 'photo_submission', 10);

        $req = new Request();
        $req->setRouteParams(['id' => '10']);
        $req->setAttribute('managed_store_ids', [1]);
        $req->setAttribute('auth_user', ['id' => 7]);

        $this->assertSame(200, $this->controller()->show($req)->status());
    }

    public function testShowForbiddenMarksNothing(): void
    {
        $this->photos = $this->createStub(StorePhotoRepositoryInterface::class);
        $this->photos->method('findSubmissionById')->willReturn(['id' => 10, 'store_id' => 2]);
        $this->notifs = $this->createMock(NotificationService::class);
        $this->notifs->expects($this->never())->method('markReadFor');

        $req = new Request();
        $req->setRouteParams(['id' => '10']);
        $req->setAttribute('managed_store_ids', [1]);
        $req->setAttribute('auth_user', ['id' => 7]);

        $this->expectException(ForbiddenException::class);
        $this->controller()->show($req);
    }

    /**
     * ?origin_store_id= porte le filtre actif au moment où l'utilisateur a cliqué
     * sur l'envoi (0 = vue "tous les stores") ; show() doit le transmettre tel
     * quel à la vue pour que le bouton retour y ramène.
     */
    public function testShowPassesOriginStoreIdAsBackStoreId(): void
    {
        $this->photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $viewDir = sys_get_temp_dir() . '/kintai-store-photos-views';
        file_put_contents($viewDir . DIRECTORY_SEPARATOR . 'store-photos-detail.php', '<?php echo $backStoreId;');
        file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'app.php', '<?php echo $content;');

        $_GET = ['origin_store_id' => '0'];
        $this->photos->expects($this->atLeastOnce())->method('findSubmissionById')->with(10)->willReturn(['id' => 10, 'store_id' => 1]);
        $this->photos->expects($this->atLeastOnce())->method('findImagesBySubmission')->with(10)->willReturn([]);
        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);

        $req = new Request();
        $req->setRouteParams(['id' => '10']);
        $req->setAttribute('managed_store_ids', [1]);

        $response = $this->controller()->show($req);

        $this->assertSame('0', $response->body());
    }

    /** Sans ?origin_store_id= (lien direct/ancien), show() retombe sur le store de l'envoi lui-même. */
    public function testShowFallsBackToSubmissionsStoreWithoutOrigin(): void
    {
        $this->photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $viewDir = sys_get_temp_dir() . '/kintai-store-photos-views';
        file_put_contents($viewDir . DIRECTORY_SEPARATOR . 'store-photos-detail.php', '<?php echo $backStoreId;');
        file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'app.php', '<?php echo $content;');

        $this->photos->expects($this->atLeastOnce())->method('findSubmissionById')->with(10)->willReturn(['id' => 10, 'store_id' => 1]);
        $this->photos->expects($this->atLeastOnce())->method('findImagesBySubmission')->with(10)->willReturn([]);
        $this->stores->expects($this->atLeastOnce())->method('findById')->with(1)->willReturn(['id' => 1, 'name' => 'Store A']);

        $req = new Request();
        $req->setRouteParams(['id' => '10']);
        $req->setAttribute('managed_store_ids', [1]);

        $response = $this->controller()->show($req);

        $this->assertSame('1', $response->body());
    }

    public function testStoreForbiddenWhenPhotosDisabledForStore(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $_POST = ['store_id' => '1'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        // Une ligne existe en base mais 'photos' n'y figure pas => désactivé pour ce store.
        $this->stores->expects($this->atLeastOnce())->method('getFeatures')->with(1)->willReturn(['shifts', 'timeclock']);

        $this->expectException(ForbiddenException::class);
        $this->controller()->store($req);
    }

    public function testCreateForbiddenWhenPhotosDisabledForRequestedStore(): void
    {
        $this->stores = $this->createMock(StoreRepositoryInterface::class);
        $_GET['store_id'] = '1';
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->stores->method('findAll')->willReturn([]);
        $this->stores->expects($this->atLeastOnce())->method('getFeatures')->with(1)->willReturn(['shifts']);

        $this->expectException(ForbiddenException::class);
        $this->controller()->create($req);
    }

    public function testCreateDropdownOnlyListsStoresWithPhotosFeatureEnabled(): void
    {
        // Le dropdown ne doit pas proposer un store où l'envoi serait ensuite
        // refusé (403) par store() faute de fonctionnalité "photos" activée.
        $viewDir = sys_get_temp_dir() . '/kintai-store-photos-views';
        file_put_contents($viewDir . DIRECTORY_SEPARATOR . 'store-photos-form.php', '<?php echo json_encode($myStores);');
        file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'app.php', '<?php echo $content;');

        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $this->stores->method('findAll')->willReturn([
            ['id' => 1, 'name' => 'Store A'],
            ['id' => 2, 'name' => 'Store B'],
        ]);
        $this->stores->method('getFeatures')->willReturnMap([
            [1, ['photos']],
            [2, ['shifts']],
        ]);

        $response = $this->controller()->create($req);

        $listed = json_decode($response->body(), true);
        $this->assertSame([1], array_column($listed, 'id'));
    }

    public function testStoreWithoutStoreIdRedirectsToCreate(): void
    {
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);

        $response = $this->controller()->store($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreCreatesSubmissionAndLogsAudit(): void
    {
        $_POST = ['store_id' => '1', 'week_label' => '2026-07-07', 'notes' => 'RAS'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 7]);

        $this->appSettings->method('get')->willReturn('14');
        $this->photos->method('saveSubmission')->willReturn(['id' => 55, 'store_id' => 1]);

        $response = $this->controller()->store($req);

        $this->assertSame(302, $response->status());
    }

    /**
     * Plusieurs envois de photos pour le même magasin le même jour doivent former
     * un seul rapport : store() se rattache à l'envoi du jour trouvé plutôt que
     * d'en créer un nouveau, et fusionne les notes.
     */
    public function testStoreMergesIntoExistingSameDaySubmissionInsteadOfCreatingNew(): void
    {
        $this->photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $_POST = ['store_id' => '1', 'notes' => 'Deuxième envoi'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 7]);

        $this->photos->expects($this->atLeastOnce())->method('findTodaySubmission')->with(1, date('Y-m-d'))
            ->willReturn(['id' => 55, 'store_id' => 1, 'notes' => 'Premier envoi', 'image_count' => 2]);

        $this->photos->expects($this->once())->method('saveSubmission')
            ->with($this->callback(function (array $data) {
                return ($data['id'] ?? null) === 55
                    && !array_key_exists('store_id', $data)
                    && $data['notes'] === "Premier envoi\nDeuxième envoi";
            }))
            ->willReturn(['id' => 55, 'store_id' => 1, 'image_count' => 2]);

        $response = $this->controller()->store($req);

        $this->assertSame(302, $response->status());
    }

    /** Sans envoi existant pour aujourd'hui, store() crée bien une nouvelle soumission (image_count à 0). */
    public function testStoreCreatesNewSubmissionWhenNoneExistsForToday(): void
    {
        $this->photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $_POST = ['store_id' => '1', 'week_label' => '2026-07-07', 'notes' => 'RAS'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', null);
        $req->setAttribute('auth_user', ['id' => 7]);

        $this->appSettings->method('get')->willReturn('14');
        $this->photos->method('findTodaySubmission')->willReturn(null);
        $this->photos->expects($this->once())->method('saveSubmission')
            ->with($this->callback(fn (array $data) => ($data['store_id'] ?? null) === 1 && ($data['image_count'] ?? null) === 0))
            ->willReturn(['id' => 99, 'store_id' => 1, 'image_count' => 0]);

        $response = $this->controller()->store($req);

        $this->assertSame(302, $response->status());
    }

    public function testStoreForbiddenWhenStoreNotManaged(): void
    {
        $_POST = ['store_id' => '9'];
        $req = new Request();
        $req->setAttribute('managed_store_ids', [1, 2]);

        $this->expectException(ForbiddenException::class);
        $this->controller()->store($req);
    }

    public function testDeleteForbiddenForNonAdmin(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 3, 'is_admin' => false]);
        $req->setRouteParams(['id' => '1']);

        $this->expectException(ForbiddenException::class);
        $this->controller()->delete($req);
    }

    public function testDeleteRemovesSubmissionForAdmin(): void
    {
        $this->photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => '42']);

        $this->photos->expects($this->atLeastOnce())->method('findSubmissionById')->with(42)->willReturn(['id' => 42, 'store_id' => 3]);
        $this->photos->expects($this->once())->method('deleteSubmission')->with(42);

        $response = $this->controller()->delete($req);

        $this->assertSame(302, $response->status());
    }

    /**
     * Sans ?origin_store_id= (lien direct/ancien), delete() retombe sur le store
     * de l'envoi supprimé — comportement historique préservé.
     */
    public function testDeleteWithoutOriginRedirectsToTheSubmissionsOwnStore(): void
    {
        $this->photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => '42']);

        $this->photos->expects($this->atLeastOnce())->method('findSubmissionById')->with(42)->willReturn(['id' => 42, 'store_id' => 3]);

        $response = $this->controller()->delete($req);

        $this->assertSame(302, $response->status());
        $this->assertRedirectLocation($response, '/admin/photos?store_id=3');
    }

    /**
     * Régression : supprimer un envoi consulté depuis la vue "tous les stores"
     * (?origin_store_id=0) doit revenir sur "tous les stores", pas se retrouver
     * filtré sur le store de l'envoi supprimé.
     */
    public function testDeleteFromAllStoresViewReturnsToAllStores(): void
    {
        $this->photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_GET = ['origin_store_id' => '0'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => '42']);

        $this->photos->expects($this->atLeastOnce())->method('findSubmissionById')->with(42)->willReturn(['id' => 42, 'store_id' => 3]);

        $response = $this->controller()->delete($req);

        $this->assertSame(302, $response->status());
        $this->assertRedirectLocation($response, '/admin/photos');
    }

    /** Supprimé depuis une vue filtrée sur un AUTRE store que celui de l'envoi : on y revient, pas sur le store de l'envoi. */
    public function testDeleteFromDifferentFilteredStoreReturnsToThatStore(): void
    {
        $this->photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_GET = ['origin_store_id' => '9'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);
        $req->setRouteParams(['id' => '42']);

        $this->photos->expects($this->atLeastOnce())->method('findSubmissionById')->with(42)->willReturn(['id' => 42, 'store_id' => 3]);

        $response = $this->controller()->delete($req);

        $this->assertSame(302, $response->status());
        $this->assertRedirectLocation($response, '/admin/photos?store_id=9');
    }

    public function testSettingsForbiddenForNonAdmin(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 3, 'is_admin' => false]);

        $this->expectException(ForbiddenException::class);
        $this->controller()->settings($req);
    }

    public function testSettingsRendersForAdmin(): void
    {
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->appSettings->method('get')->willReturn('14');

        $response = $this->controller()->settings($req);

        $this->assertSame(200, $response->status());
    }

    public function testSaveSettingsPersistsRetentionAndCleanupDelay(): void
    {
        $this->appSettings = $this->createMock(AppSettingsRepositoryInterface::class);
        $_POST = ['photo_retention_days' => '30', 'photo_cleanup_delay' => '10'];
        $req = new Request();
        $req->setAttribute('auth_user', ['id' => 1, 'is_admin' => true]);

        $this->appSettings->expects($this->once())->method('setMany')->with([
            'photo_retention_days' => '30',
            'photo_cleanup_delay'  => '10',
        ]);

        $response = $this->controller()->saveSettings($req);

        $this->assertSame(302, $response->status());
    }

    private function assertRedirectLocation(Response $response, string $expected): void
    {
        $ref = new \ReflectionProperty($response, 'headers');
        $ref->setAccessible(true);
        $this->assertSame($expected, $ref->getValue($response)['Location'] ?? null);
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
