<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\StorePhoto\Services;

use kintai\Bundles\Installed\StorePhoto\Repositories\StorePhotoRepositoryInterface;
use kintai\Contract\Auth\PermissionService;
use kintai\Contract\Data\AppSettingsRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Services\NotificationService;

/**
 * Rétention des photos (reprise du script CLI scripts/photo-retention.php du Core à l'extraction du bundle) :
 * 1. les envois plus vieux que photo_retention_days sont archivés (photos zippées, envoi marqué « compressé ») ;
 * 2. les Owners sont prévenus une fois par envoi archivé (notification photo_archived ; mail_sent_at sert de marqueur,
 *    comme pour l'ancien e-mail) ;
 * 3. après photo_cleanup_delay jours de plus, l'envoi, ses fichiers et son archive sont supprimés définitivement.
 */
final class PhotoRetentionService
{
    public function __construct(
        private readonly StorePhotoRepositoryInterface $photos,
        private readonly AppSettingsRepositoryInterface $settings,
        private readonly StoreRepositoryInterface $stores,
        private readonly PermissionService $permissions,
        private readonly NotificationService $notifications,
        private readonly ?string $photoDir = null,
    ) {}

    /** @return array{retention_days: int, cleanup_delay: int, compressed: int, notified: int, purged: int, errors: list<string>} */
    public function run(): array
    {
        $retentionDays = max(1, (int) $this->settings->get('photo_retention_days', '14'));
        $cleanupDelay  = max(1, (int) $this->settings->get('photo_cleanup_delay', '7'));
        $photoDir      = $this->photoDir ?? storage_path('uploads/img/');
        $result = ['retention_days' => $retentionDays, 'cleanup_delay' => $cleanupDelay, 'compressed' => 0, 'notified' => 0, 'purged' => 0, 'errors' => []];

        $owners = null;
        foreach ($this->photos->getPendingCompression($retentionDays) as $sub) {
            $sid     = (int) $sub['id'];
            $storeId = (int) ($sub['store_id'] ?? 0);
            $now     = date('Y-m-d H:i:s');

            if (!$this->archive($photoDir . $storeId . '/' . $sid . '/', $photoDir . $storeId . '/' . $sid . '.zip')) {
                $result['errors'][] = "archive {$sid}";
                continue;
            }
            $this->photos->saveSubmission(['id' => $sid, 'compressed' => 1, 'compressed_at' => $now, 'updated_at' => $now]);
            $result['compressed']++;

            if (empty($sub['mail_sent_at'])) {
                $owners ??= $this->permissions->ownerUserIds();
                if ($owners !== []) {
                    $store = $this->stores->findById($storeId);
                    $this->notifications->notifyMany($owners, 'photo_archived', 'notif_photo_archived_body', [
                        'store' => $store['name'] ?? '#' . $storeId,
                        'date'  => (string) ($sub['week_label'] ?? '?'),
                        'days'  => (string) $cleanupDelay,
                    ], $sid, '/admin/photos/' . $storeId . '/' . $sid);
                    $this->photos->saveSubmission(['id' => $sid, 'mail_sent_at' => $now, 'updated_at' => $now]);
                    $result['notified']++;
                }
            }
        }

        foreach ($this->photos->getExpiredSubmissions($retentionDays + $cleanupDelay) as $sub) {
            $sid     = (int) $sub['id'];
            $storeId = (int) ($sub['store_id'] ?? 0);
            $this->purge($photoDir . $storeId . '/' . $sid . '/', $photoDir . $storeId . '/' . $sid . '.zip');
            $this->photos->deleteSubmission($sid);
            $result['purged']++;
        }

        return $result;
    }

    /** Zippe les photos de l'envoi. Un dossier absent compte comme archivé (rien à garder). */
    private function archive(string $subDir, string $zipPath): bool
    {
        if (!is_dir($subDir)) {
            return true;
        }
        if (!is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0775, true);
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        foreach (glob($subDir . '*') ?: [] as $file) {
            if (is_file($file)) {
                $zip->addFile($file, basename($file));
            }
        }
        return $zip->close();
    }

    /**
     * Supprime l'archive, les photos et le dossier du magasin s'il devient vide. L'archive est rangée dans ce dossier :
     * elle part en premier (le script du Core la supprimait après, ce qui laissait le dossier vide en place).
     */
    private function purge(string $subDir, string $zipPath): void
    {
        if (is_file($zipPath)) {
            unlink($zipPath);
        }
        if (is_dir($subDir)) {
            foreach (glob($subDir . '*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($subDir);
        }
        $storeDir = dirname($subDir);
        if (is_dir($storeDir) && count(array_diff(scandir($storeDir) ?: [], ['.', '..'])) === 0) {
            rmdir($storeDir);
        }
    }
}
