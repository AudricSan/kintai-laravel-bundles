<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\StorePhoto\Services;

use kintai\Bundles\Installed\StorePhoto\Repositories\StorePhotoRepositoryInterface;
use kintai\Bundles\Installed\StorePhoto\Services\PhotoRetentionService;
use kintai\Contract\Auth\PermissionService;
use kintai\Contract\Data\AppSettingsRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Services\NotificationService;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Rétention des photos (reprise du script CLI du Core) : archivage des envois expirés, avertissement des Owners une
 * seule fois par envoi, purge définitive après le délai de nettoyage.
 */
final class PhotoRetentionServiceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/kintai-photo-retention-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->dir . '5/10', 0777, true);
        file_put_contents($this->dir . '5/10/photo_1.jpg', 'a');
        file_put_contents($this->dir . '5/10/photo_2.jpg', 'b');
        mkdir($this->dir . '6/20', 0777, true);
        file_put_contents($this->dir . '6/20/photo_1.jpg', 'c');
        file_put_contents($this->dir . '6/20.zip', 'zip');
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
    }

    /** PermissionService (classe finale) dont les Owners sont les utilisateurs donnés (rôle système en portée globale). */
    private function permissions(array $ownerIds): PermissionService
    {
        $assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $assignments->method('findByScope')->willReturn(array_map(fn(int $id): array => ['user_id' => $id, 'role_id' => 1, 'scope_type' => 'global'], $ownerIds));
        $roles = $this->createStub(RoleRepositoryInterface::class);
        $roles->method('findById')->willReturn(['id' => 1, 'is_system' => 1]);
        return new PermissionService($assignments, $roles);
    }

    public function testArchivesNotifiesOnceAndPurges(): void
    {
        $settings = $this->createStub(AppSettingsRepositoryInterface::class);
        $settings->method('get')->willReturnMap([['photo_retention_days', '14', '10'], ['photo_cleanup_delay', '7', '3']]);

        $photos = $this->createMock(StorePhotoRepositoryInterface::class);
        $photos->expects($this->once())->method('getPendingCompression')->with(10)->willReturn([
            ['id' => 10, 'store_id' => 5, 'week_label' => '2026-09-01', 'mail_sent_at' => null],
            ['id' => 11, 'store_id' => 5, 'week_label' => '2026-09-02', 'mail_sent_at' => '2026-09-20 00:00:00'],
        ]);
        $photos->expects($this->once())->method('getExpiredSubmissions')->with(13)->willReturn([
            ['id' => 20, 'store_id' => 6],
        ]);
        $saved = [];
        $photos->method('saveSubmission')->willReturnCallback(function (array $d) use (&$saved): array {
            $saved[] = array_keys($d);
            return $d;
        });
        $photos->expects($this->once())->method('deleteSubmission')->with(20)->willReturn(1);

        $stores = $this->createStub(StoreRepositoryInterface::class);
        $stores->method('findById')->willReturn(['id' => 5, 'name' => 'Shibuya']);
        $permissions = $this->permissions([1, 2]);
        $notifications = $this->createMock(NotificationService::class);
        $notifications->expects($this->once())->method('notifyMany')->with(
            [1, 2], 'photo_archived', 'notif_photo_archived_body',
            ['store' => 'Shibuya', 'date' => '2026-09-01', 'days' => '3'], 10, '/admin/photos/5/10'
        );

        $result = (new PhotoRetentionService($photos, $settings, $stores, $permissions, $notifications, $this->dir))->run();

        $this->assertSame(2, $result['compressed']);
        $this->assertSame(1, $result['notified'], 'envoi déjà signalé : pas de nouvelle notification');
        $this->assertSame(1, $result['purged']);
        $this->assertSame([], $result['errors']);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($this->dir . '5/10.zip') === true);
        $this->assertSame(2, $zip->numFiles);
        $zip->close();
        $this->assertDirectoryDoesNotExist($this->dir . '6', 'purge : photos, archive et dossier du magasin vide supprimés');
        $this->assertContains(['id', 'mail_sent_at', 'updated_at'], $saved);
    }

    public function testNoOwnerMeansNoNotificationAndNoMarker(): void
    {
        $settings = $this->createStub(AppSettingsRepositoryInterface::class);
        $settings->method('get')->willReturnArgument(1);
        $photos = $this->createStub(StorePhotoRepositoryInterface::class);
        $photos->method('getPendingCompression')->willReturn([['id' => 10, 'store_id' => 5, 'mail_sent_at' => null]]);
        $photos->method('getExpiredSubmissions')->willReturn([]);
        $photos->method('saveSubmission')->willReturnArgument(0);
        $permissions = $this->permissions([]);
        $notifications = $this->createMock(NotificationService::class);
        $notifications->expects($this->never())->method('notifyMany');

        $result = (new PhotoRetentionService($photos, $settings, $this->createStub(StoreRepositoryInterface::class), $permissions, $notifications, $this->dir))->run();

        $this->assertSame(['compressed' => 1, 'notified' => 0], ['compressed' => $result['compressed'], 'notified' => $result['notified']]);
    }
}
