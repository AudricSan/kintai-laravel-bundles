<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\PlanningCheck\Repositories;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\PlanningCheck\Repositories\DatabasePlanningCheckRepository;
use PHPUnit\Framework\TestCase;

/** Réglages, validations et historique contre le vrai schéma de la migration du bundle. */
final class DatabasePlanningCheckRepositoryTest extends TestCase
{
    private DatabasePlanningCheckRepository $repo;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // Les migrations lisent $this->capsule : on les charge depuis un objet qui le porte, comme le MigrationRunner du Core.
        $loader = new class($capsule) {
            public function __construct(public Capsule $capsule) {}

            public function load(string $file): object
            {
                return require $file;
            }
        };
        $loader->load(dirname(__DIR__, 2) . '/database/migrations/2026_10_23_000001_create_planning_check_tables.php')->up();
        $loader->load(dirname(__DIR__, 2) . '/database/migrations/2026_10_24_000001_add_mail_recipient_ids_to_planning_check_settings.php')->up();

        $this->repo = new DatabasePlanningCheckRepository();
    }

    public function testDefaultSettingsWhenNothingIsSaved(): void
    {
        $settings = $this->repo->settings(3);

        $this->assertFalse($settings['enabled']);
        $this->assertSame('12:00', $settings['deadline_time']);
        $this->assertSame('06:00', $settings['window_start_time']);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $settings['weekdays']);
        $this->assertSame([], $this->repo->enabledStoreIds());
    }

    public function testSettingsRoundTripPerStore(): void
    {
        $this->repo->saveSettings(3, ['enabled' => true, 'deadline_time' => '11:30', 'window_start_time' => '07:15', 'weekdays' => [1, 3, 5],
            'mail_recipients' => ['boss@example.com', 'other@example.com'], 'include_open_shifts' => true]);
        $this->repo->saveSettings(3, ['enabled' => true, 'deadline_time' => '11:45', 'window_start_time' => '07:15', 'weekdays' => [1, 3, 5],
            'mail_recipients' => ['boss@example.com'], 'include_open_shifts' => false]);
        $this->repo->saveSettings(4, ['enabled' => false, 'deadline_time' => '12:00', 'window_start_time' => '06:00', 'weekdays' => [1], 'mail_recipients' => [], 'include_open_shifts' => false]);

        $settings = $this->repo->settings(3);
        $this->assertSame(['enabled' => true, 'deadline_time' => '11:45', 'window_start_time' => '07:15', 'weekdays' => [1, 3, 5],
            'mail_recipient_ids' => [], 'mail_recipients' => ['boss@example.com'], 'include_open_shifts' => false], $settings, 'enregistrer remplace');
        $this->assertSame([3], $this->repo->enabledStoreIds());
        $this->assertSame([1], $this->repo->settings(4)['weekdays']);
    }

    public function testEnsureCreatesOnePendingCheckPerStoreAndDay(): void
    {
        $first = $this->repo->ensure(3, '2026-10-22');
        $again = $this->repo->ensure(3, '2026-10-22');
        $other = $this->repo->ensure(4, '2026-10-22');

        $this->assertSame('pending', $first['status']);
        $this->assertSame($first['id'], $again['id'], 'unique (magasin, jour)');
        $this->assertNotSame($first['id'], $other['id']);
        $this->assertNull($first['submitted_by']);
        $this->assertFalse($first['confirmed_empty']);
    }

    public function testUpdateFindAndHistoryOrder(): void
    {
        $a = $this->repo->ensure(3, '2026-10-20');
        $b = $this->repo->ensure(3, '2026-10-22');
        $updated = $this->repo->update($a['id'], ['status' => 'submitted', 'submitted_by' => 9, 'submitted_at' => '2026-10-19 10:00:00']);

        $this->assertSame('submitted', $updated['status']);
        $this->assertSame(9, $updated['submitted_by']);
        $this->assertSame($b['id'], $this->repo->recentForStore(3)[0]['id'], 'les plus récents d\'abord');
        $this->assertSame($a['id'], $this->repo->findFor(3, '2026-10-20')['id']);
        $this->assertNull($this->repo->find(999));
    }

    public function testSubmittedChecksAreListedForTheGivenStoresOnly(): void
    {
        $a = $this->repo->ensure(3, '2026-10-22');
        $b = $this->repo->ensure(4, '2026-10-22');
        $this->repo->ensure(3, '2026-10-23');
        $this->repo->update($a['id'], ['status' => 'submitted', 'submitted_at' => '2026-10-21 09:00:00']);
        $this->repo->update($b['id'], ['status' => 'submitted', 'submitted_at' => '2026-10-21 08:00:00']);

        $this->assertSame([$b['id'], $a['id']], array_column($this->repo->submittedForStores([3, 4]), 'id'), 'les plus anciennes d\'abord');
        $this->assertSame([$a['id']], array_column($this->repo->submittedForStores([3]), 'id'));
        $this->assertSame([], $this->repo->submittedForStores([]));
    }

    public function testSendsAreKeptInTheHistory(): void
    {
        $check = $this->repo->ensure(3, '2026-10-22');
        $this->repo->addSend(['check_id' => $check['id'], 'sent_by' => 9, 'status' => 'failed', 'shifts_hash' => 'h1', 'recipients' => ['boss@example.com'], 'error' => 'mail_failed']);
        $this->repo->addSend(['check_id' => $check['id'], 'sent_by' => 9, 'status' => 'sent', 'shifts_hash' => 'h2', 'recipients' => ['boss@example.com']]);

        $sends = $this->repo->sendsFor($check['id']);

        $this->assertSame(['sent', 'failed'], array_column($sends, 'status'));
        $this->assertSame('mail_failed', $sends[1]['error']);
    }
}
