<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\StorePhoto\Controllers\Web;

use kintai\Contract\Http\Concerns\HasAdminAccess;
use kintai\Contract\Services\ImageCompressionService;
use kintai\Contract\Exceptions\ForbiddenException;
use kintai\Bundles\Installed\StorePhoto\Repositories\StorePhotoRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\AppSettingsRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;
use kintai\Contract\Services\NotificationService;
use kintai\Contract\View\ViewRenderer;

final class StorePhotoController
{
    use HasAdminAccess;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly StorePhotoRepositoryInterface $photos,
        private readonly StoreRepositoryInterface $stores,
        private readonly AppSettingsRepositoryInterface $appSettings,
        private readonly AuditLogger $auditLogger,
        private readonly ImageCompressionService $imageCompressor,
        private readonly NotificationService $notifs,
    ) {}

    public function index(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $storeId    = (int) ($request->query('store_id') ?: 0);
        // Un manager restreint ne doit jamais voir les envois d'un store hors de son périmètre,
        // ni via la liste complète (findAllSubmissions(null, ...) remontait tous les stores),
        // ni via ?store_id= pointant sur un store qu'il ne gère pas.
        if ($storeId > 0 && $managedIds !== null && !in_array($storeId, $managedIds, true)) {
            $storeId = 0;
        }
        $submissions = $this->photos->findAllSubmissions($managedIds, 100);

        if ($storeId > 0) {
            $submissions = array_values(array_filter($submissions, fn($s) => (int) ($s['store_id'] ?? 0) === $storeId));
        }

        // Jours disponibles pour le filtre, calculés avant le filtre par jour
        // lui-même : sinon le sélecteur ne proposerait plus que le jour choisi.
        $availableDates = [];
        foreach ($submissions as $s) {
            $day = date('Y-m-d', strtotime($s['created_at'] ?? 'now'));
            $availableDates[$day] = ($availableDates[$day] ?? 0) + 1;
        }
        krsort($availableDates);

        $filterDate = (string) ($request->query('date') ?? '');
        if ($filterDate !== '' && !isset($availableDates[$filterDate])) {
            $filterDate = '';
        }
        if ($filterDate !== '') {
            $submissions = array_values(array_filter(
                $submissions,
                fn($s) => date('Y-m-d', strtotime($s['created_at'] ?? 'now')) === $filterDate
            ));
        }

        $storeNames = $this->buildStoresMap(null);
        $submissionImages = [];
        foreach ($submissions as $s) {
            $sid = (int) $s['id'];
            $submissionImages[$sid] = $this->enrichWithVersion($this->photos->findImagesBySubmission($sid));
        }

        return Response::html($this->view->render('store-photos::store-photos', [
            'title'             => __('store_photos_photos_report'),
            'submissions'       => $submissions,
            'submissionImages'  => $submissionImages,
            'storeNames'        => $storeNames,
            'availableStores'   => $this->availableStores($managedIds),
            'filterStoreId'     => $storeId,
            'availableDates'    => $availableDates,
            'filterDate'        => $filterDate,
        ], 'layout.app'));
    }

    public function create(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $storeId    = (int) ($request->query('store_id') ?: 0);
        $myStores   = array_values(array_filter(
            $this->availableStores($managedIds),
            fn($s) => $this->isPhotosFeatureEnabled((int) $s['id'])
        ));
        $storeNames = $this->buildStoresMap($managedIds);

        if ($managedIds !== null && $storeId > 0) {
            $this->assertStoreAccess($request, $storeId);
        }
        if ($storeId > 0) {
            $this->assertPhotosFeatureEnabled($storeId);
        }

        return Response::html($this->view->render('store-photos::store-photos-form', [
            'title'       => __('store_photos_photo_new_submission'),
            'mode'        => 'create',
            'myStores'    => $myStores,
            'storeNames'  => $storeNames,
            'prefillStoreId' => $storeId,
        ], 'layout.app'));
    }

    public function store(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $storeId    = (int) ($request->post('store_id') ?: 0);
        if ($storeId <= 0) {
            return $request->isAjax()
                ? Response::json(['error' => 'store_id required'], 400)
                : Response::redirect($this->base() . '/admin/photos/create');
        }
        $this->assertStoreAccess($request, $storeId);
        $this->assertPhotosFeatureEnabled($storeId);

        $weekLabel = $request->post('week_label') ?: date('Y-m-d', strtotime('tuesday this week'));
        $notes     = $request->post('notes') ?? '';
        $createdBy = (int) ($request->getAttribute('auth_user')['id'] ?? 0);

        // Plusieurs envois pour le même magasin le même jour doivent former un seul
        // rapport : on rattache à l'envoi du jour déjà existant plutôt que d'en
        // recréer un (photos + notes s'accumulent dans la même soumission).
        $today   = date('Y-m-d');
        $existing = $this->photos->findTodaySubmission($storeId, $today);
        $isMerge  = $existing !== null;

        if ($isMerge) {
            $mergedNotes = trim($existing['notes'] ?? '');
            if ($notes !== '') {
                $mergedNotes = $mergedNotes !== '' ? $mergedNotes . "\n" . $notes : $notes;
            }
            $submission   = $this->photos->saveSubmission([
                'id'         => $existing['id'],
                'notes'      => $mergedNotes,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $submissionId = (int) $submission['id'];
        } else {
            $submission = $this->photos->saveSubmission([
                'store_id'       => $storeId,
                'week_label'     => $weekLabel,
                'notes'          => $notes,
                'image_count'    => 0,
                'retention_days' => max(1, (int) $this->appSettings->get('photo_retention_days', '14')),
                'created_by'     => $createdBy,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
            $submissionId = (int) $submission['id'];
        }

        $files = $request->file('photos');
        if ($files !== null && !empty($files['tmp_name'][0])) {
            $this->saveUploadedFiles($request, $storeId, $submissionId, $files, (int) ($submission['image_count'] ?? 0));
        }

        $this->auditLogger->log(
            $request,
            $isMerge ? 'photo.submission_merged' : 'photo.submission_created',
            'store_photo_submission',
            $submissionId,
            $submission,
            $storeId
        );

        if ($request->isAjax()) {
            return Response::json(['id' => $submissionId]);
        }
        return Response::redirect($this->base() . '/admin/photos?store_id=' . $storeId);
    }

    public function uploadFile(Request $request): Response
    {
        $submissionId = (int) $request->param('id');
        $submission   = $this->photos->findSubmissionById($submissionId);
        if (!$submission) {
            return Response::json(['error' => __('error_submission_not_found')], 404);
        }
        $this->assertStoreAccess($request, (int) $submission['store_id']);
        $this->assertPhotosFeatureEnabled((int) $submission['store_id']);

        $file = $request->file('photo');
        if ($file === null || !is_uploaded_file($file['tmp_name'])) {
            return Response::json(['error' => __('error_no_file_uploaded')], 400);
        }

        $ext = $this->safeImageExtension($file['name'] ?? '');
        if ($ext === null) {
            return Response::json(['error' => __('error_file_type_not_allowed')], 422);
        }

        $storeId = (int) $submission['store_id'];
        $index   = (int) ($submission['image_count'] ?? 0);

        $uploadDir = storage_path('uploads/img/');
        $storeDir  = $uploadDir . $storeId . '/';
        $subDir    = $storeDir . $submissionId . '/';
        if (!is_dir($subDir)) { mkdir($subDir, 0775, true); }

        $compressed = $this->imageCompressor->compress($file['tmp_name'], $subDir . 'photo_' . ($index + 1));
        if ($compressed === null) {
            return Response::json(['error' => __('error_file_type_not_allowed')], 422);
        }
        $safe = basename($compressed['path']);

        $this->photos->saveImage([
            'submission_id' => $submissionId,
            'filename'      => $file['name'],
            'filepath'      => 'storage/img/' . $storeId . '/' . $submissionId . '/' . $safe,
            'filesize'      => $compressed['size'],
            'mime_type'     => $compressed['mime'],
            'sort_order'    => $index,
        ]);

        $this->photos->saveSubmission([
            'id'          => $submissionId,
            'image_count' => $index + 1,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        return Response::json(['ok' => true, 'file' => $safe]);
    }

    /**
     * Le toggle "photos" par store (/admin/stores/{id}/edit) est décoratif tant qu'il
     * n'est pas vérifié ici — contrairement à EmployeeController/MessageController,
     * ce contrôleur est admin-only donc il n'y a aucune autre surface qui l'applique.
     */
    private function assertPhotosFeatureEnabled(int $storeId): void
    {
        if (!$this->isPhotosFeatureEnabled($storeId)) {
            throw new ForbiddenException(__('store_photos_error_photos_feature_disabled'));
        }
    }

    private function isPhotosFeatureEnabled(int $storeId): bool
    {
        $features = $this->stores->getFeatures($storeId);
        return $features === [] || in_array('photos', $features, true);
    }

    /**
     * Redresse manuellement une photo déjà stockée. Une fois compressée, l'image
     * n'a plus d'EXIF (voir ImageCompressionService::applyExifOrientation()) : une
     * photo de travers doit être corrigée à la main, il n'y a pas d'orientation
     * à relire automatiquement.
     */
    public function rotateImage(Request $request): Response
    {
        $imageId = (int) $request->param('image_id');
        $image   = $this->photos->findImageById($imageId);
        if (!$image) {
            return Response::redirect($this->base() . '/admin/photos');
        }

        $submission = $this->photos->findSubmissionById((int) $image['submission_id']);
        if (!$submission) {
            return Response::redirect($this->base() . '/admin/photos');
        }

        $storeId = (int) $submission['store_id'];
        $this->assertStoreAccess($request, $storeId);
        $this->assertPhotosFeatureEnabled($storeId);

        $degrees = $request->post('direction') === 'left' ? -90 : 90;
        $this->imageCompressor->rotateInPlace(
            $this->physicalPath((string) $image['filepath']),
            (string) ($image['mime_type'] ?? 'image/jpeg'),
            $degrees
        );

        $this->auditLogger->log($request, 'photo.image_rotated', 'store_photo_image', $imageId, ['degrees' => $degrees], $storeId);

        $backStoreId = $this->resolveBackStoreId($request, $storeId);
        $redirect    = $this->base() . '/admin/photos/' . $storeId . '/' . $submission['id']
            . ($backStoreId > 0 ? '?origin_store_id=' . $backStoreId : '');

        return Response::redirect($redirect);
    }

    /**
     * Détermine vers quelle vue liste revenir (tous les stores, ou un store filtré)
     * une fois l'envoi consulté/supprimé — d'après le filtre actif au moment où
     * l'utilisateur a cliqué sur l'envoi (porté par ?origin_store_id=, y compris
     * la valeur 0 pour "tous les stores"). Sans ce paramètre (lien direct/ancien),
     * on retombe sur le store de l'envoi lui-même, comme avant.
     */
    private function resolveBackStoreId(Request $request, int $fallbackStoreId): int
    {
        $origin = $request->query('origin_store_id');
        return ($origin === null || $origin === '') ? $fallbackStoreId : (int) $origin;
    }

    /** Chemin physique sur disque d'une image à partir de son filepath public (storage/img/... → storage/uploads/img/...). */
    private function physicalPath(string $publicFilepath): string
    {
        return storage_path('uploads/') . substr($publicFilepath, strlen('storage/'));
    }

    /**
     * Ajoute un paramètre ?v= (mtime du fichier) à chaque image pour invalider le
     * cache navigateur après une rotation : le fichier est réécrit en place, sous
     * le même nom, et resterait affiché de travers depuis le cache sans ce param.
     */
    private function enrichWithVersion(array $images): array
    {
        foreach ($images as &$img) {
            $physical = $this->physicalPath((string) $img['filepath']);
            $img['version'] = is_file($physical) ? (string) filemtime($physical) : '0';
        }
        unset($img);
        return $images;
    }

    /**
     * Retourne l'extension normalisée si le fichier est une image autorisée, null sinon.
     */
    private function safeImageExtension(string $filename): ?string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? $ext : null;
    }

    private function saveUploadedFiles(Request $request, int $storeId, int $submissionId, array $files, int $startIndex = 0): void
    {
        $uploadDir = storage_path('uploads/img/');
        $storeDir  = $uploadDir . $storeId . '/';
        $subDir    = $storeDir . $submissionId . '/';
        if (!is_dir($storeDir)) { mkdir($storeDir, 0775, true); }
        if (!is_dir($subDir))  { mkdir($subDir, 0775, true); }

        $count    = $startIndex;
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $names    = is_array($files['name'])      ? $files['name']      : [$files['name']];
        $types    = is_array($files['type'])      ? $files['type']      : [$files['type']];
        $errs     = is_array($files['error'])     ? $files['error']     : [$files['error']];

        foreach ($tmpNames as $i => $tmp) {
            if (!empty($errs[$i]) || !is_uploaded_file($tmp)) continue;
            $ext = $this->safeImageExtension($names[$i] ?? '');
            if ($ext === null) continue;

            $compressed = $this->imageCompressor->compress($tmp, $subDir . 'photo_' . ($count + 1));
            if ($compressed === null) continue;

            $this->photos->saveImage([
                'submission_id' => $submissionId,
                'filename'      => $names[$i],
                'filepath'      => 'storage/img/' . $storeId . '/' . $submissionId . '/' . basename($compressed['path']),
                'filesize'      => $compressed['size'],
                'mime_type'     => $compressed['mime'],
                'sort_order'    => $count,
            ]);
            $count++;
        }

        $this->photos->saveSubmission([
            'id'          => $submissionId,
            'image_count' => $count,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        $submission = $this->photos->findSubmissionById($id);
        if (!$submission) {
            return Response::html($this->view->render('errors.404', ['title' => '404'], 'layout.app'), 404);
        }
        $this->assertStoreAccess($request, (int) $submission['store_id']);

        // L'envoi est ouvert par quelqu'un qui y a accès : sa notification d'archivage est lue.
        $userId = (int) ($request->getAttribute('auth_user')['id'] ?? 0);
        if ($userId > 0) {
            $this->notifs->markReadFor($userId, 'photo_submission', $id);
        }

        $images = $this->enrichWithVersion($this->photos->findImagesBySubmission($id));
        $store  = $this->stores->findById((int) $submission['store_id']);

        return Response::html($this->view->render('store-photos::store-photos-detail', [
            'title'        => __('store_photos_photo_submission') . ' #' . $id,
            'submission'   => $submission,
            'images'       => $images,
            'store'        => $store,
            'isOwner'      => !empty($request->getAttribute('auth_user')['is_admin']),
            'backStoreId'  => $this->resolveBackStoreId($request, (int) $submission['store_id']),
        ], 'layout.app'));
    }

    public function delete(Request $request): Response
    {
        $authUser = $request->getAttribute('auth_user');
        if (empty($authUser['is_admin'])) {
            throw new ForbiddenException(__('store_photos_error_owner_only_delete_photos'));
        }

        $id = (int) $request->param('id');
        $submission = $this->photos->findSubmissionById($id);
        if (!$submission) {
            return Response::redirect($this->base() . '/admin/photos');
        }

        $storeId    = (int) $submission['store_id'];
        $backStoreId = $this->resolveBackStoreId($request, $storeId);

        $uploadDir = storage_path('uploads/img/') . $storeId . '/' . $id . '/';
        if (is_dir($uploadDir)) {
            foreach (glob($uploadDir . '*') as $f) { if (is_file($f)) unlink($f); }
            rmdir($uploadDir);
            $storeDir = dirname($uploadDir);
            if (is_dir($storeDir) && count(array_diff(scandir($storeDir), ['.', '..'])) === 0) {
                rmdir($storeDir);
            }
        }

        $this->photos->deleteSubmission($id);
        $this->auditLogger->log($request, 'photo.submission_deleted', 'store_photo_submission', $id, $submission, $storeId);

        // Revenir là où l'utilisateur se trouvait (tous les stores, ou un store
        // filtré) plutôt que de forcer un filtre sur le store de l'élément
        // supprimé — sinon un envoi supprimé depuis la vue "tous les stores"
        // ramenait à tort sur le store de cet envoi.
        return Response::redirect($this->base() . '/admin/photos' . ($backStoreId > 0 ? '?store_id=' . $backStoreId : ''));
    }

    public function settings(Request $request): Response
    {
        $authUser = $request->getAttribute('auth_user');
        if (empty($authUser['is_admin'])) {
            throw new ForbiddenException(__('error_access_denied'));
        }

        return Response::html($this->view->render('store-photos::store-photos-settings', [
            'title'               => __('store_photos_photo_settings'),
            'retentionDays'       => $this->appSettings->get('photo_retention_days', '14'),
            'cleanupDelay'        => $this->appSettings->get('photo_cleanup_delay', '7'),
        ], 'layout.app'));
    }

    public function saveSettings(Request $request): Response
    {
        $authUser = $request->getAttribute('auth_user');
        if (empty($authUser['is_admin'])) {
            throw new ForbiddenException(__('error_access_denied'));
        }

        $retentionDays = max(1, (int) ($request->post('photo_retention_days') ?: 14));
        $cleanupDelay  = max(1, (int) ($request->post('photo_cleanup_delay') ?: 7));

        $this->appSettings->setMany([
            'photo_retention_days' => (string) $retentionDays,
            'photo_cleanup_delay'  => (string) $cleanupDelay,
        ]);

        $this->auditLogger->logUpdate($request, 'photo.settings_updated', 'app_settings', 0, [], [], [], 0);

        return Response::redirect($this->base() . '/admin/photos/settings');
    }
}
