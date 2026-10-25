<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Timeclock\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\Timeclock\Repositories\DatabaseTimeclockRepository;
use kintai\Core\Database\BundleMigrationRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Migrations du bundle, jouées par le vrai BundleMigrationRunner, sur les trois états possibles d'une base :
 * installation neuve, table de l'ancien schéma SQL (sans shift_id, clock_in_ip, clock_out_ip) et table de la migration
 * du Core (déjà complète). Dans les trois cas, la table a toutes les colonnes et les pointages existants restent.
 */
final class TimeclocksMigrationTest extends TestCase
{
    private const MIGRATIONS = ['2026_10_14_000001_create_timeclocks_table', '2026_10_14_000002_align_timeclocks_table'];

    /** @return array{0: BundleMigrationRunner, 1: Capsule} */
    private function freshRunner(): array
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $runner = (new \ReflectionClass(BundleMigrationRunner::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(BundleMigrationRunner::class, 'capsule'))->setValue($runner, $capsule);

        return [$runner, $capsule];
    }

    /** Table telle que l'a laissée l'ancien schéma SQL (relevé sur une instance réelle). */
    private static function legacySqlSchema(Capsule $capsule): void
    {
        $capsule->getConnection()->unprepared(<<<'SQL'
            CREATE TABLE "timeclocks" (
                "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, "store_id" INTEGER NOT NULL, "user_id" INTEGER NOT NULL,
                "shift_date" TEXT NOT NULL, "clock_in_time" TEXT NOT NULL, "clock_out_time" TEXT NULL,
                "duration_minutes" INTEGER NULL, "notes" TEXT NULL,
                "created_at" TEXT NOT NULL DEFAULT (datetime('now')), "updated_at" TEXT NOT NULL DEFAULT (datetime('now')));
            CREATE INDEX "idx_timeclocks_store_date" ON "timeclocks" ("store_id", "shift_date");
            INSERT INTO timeclocks (id, store_id, user_id, shift_date, clock_in_time, clock_out_time, duration_minutes)
                VALUES (7, 1, 2, '2026-09-01', '2026-09-01 09:00:00', '2026-09-01 17:00:00', 480);
            SQL);
    }

    /** Table telle que la créait la migration du Core (déjà complète). */
    private static function coreMigrationSchema(Capsule $capsule): void
    {
        $capsule->getConnection()->getSchemaBuilder()->create('timeclocks', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('store_id');
            $table->integer('shift_id')->nullable();
            $table->string('shift_date');
            $table->string('clock_in_time');
            $table->string('clock_out_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('clock_in_ip')->nullable();
            $table->string('clock_out_ip')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /** @return array<string, array{0: ?string}> */
    public static function startingPoints(): array
    {
        return ['installation neuve' => [null], 'ancien schéma SQL' => ['legacySqlSchema'], 'migration du Core' => ['coreMigrationSchema']];
    }

    #[DataProvider('startingPoints')]
    public function testEveryStartingPointEndsWithTheFullSchema(?string $existing): void
    {
        [$runner, $capsule] = $this->freshRunner();
        if ($existing !== null) {
            self::$existing($capsule);
        }

        $this->assertSame(self::MIGRATIONS, $runner->runPendingFor('timeclock', dirname(__DIR__, 2) . '/database/migrations'));

        $schema = $capsule->getConnection()->getSchemaBuilder();
        foreach (['id', 'user_id', 'store_id', 'shift_id', 'shift_date', 'clock_in_time', 'clock_out_time', 'duration_minutes',
            'clock_in_ip', 'clock_out_ip', 'notes', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue($schema->hasColumn('timeclocks', $column), "timeclocks.{$column}");
        }

        $repo = new DatabaseTimeclockRepository();
        $saved = $repo->save(['store_id' => 1, 'user_id' => 3, 'shift_date' => '2026-10-14', 'clock_in_time' => '2026-10-14 09:00:00',
            'created_at' => '2026-10-14 09:00:00', 'updated_at' => '2026-10-14 09:00:00']);
        $this->assertSame(3, (int) $repo->findActiveByUser(3)['user_id']);
        $this->assertSame((int) $saved['id'], (int) $repo->findActiveByUser(3)['id']);

        if ($existing === 'legacySqlSchema') {
            $old = $repo->findById(7);
            $this->assertSame([2, '2026-09-01 09:00:00', '2026-09-01 17:00:00', 480, null],
                [(int) $old['user_id'], $old['clock_in_time'], $old['clock_out_time'], (int) $old['duration_minutes'], $old['shift_id']]);
        }
        $this->assertSame([], $runner->runPendingFor('timeclock', dirname(__DIR__, 2) . '/database/migrations'), 'rejouées : rien');
    }
}
