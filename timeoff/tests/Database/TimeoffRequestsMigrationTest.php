<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\TimeOff\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\TimeOff\Repositories\DatabaseTimeoffRequestRepository;
use kintai\Core\Database\BundleMigrationRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Migrations du bundle, jouées par le vrai BundleMigrationRunner, sur les trois états possibles d'une base :
 * installation neuve, table de l'ancien schéma SQL (reviewed_by / reviewed_at / review_notes) et table de la migration
 * du Core (processed_by / processed_at / admin_note). Dans les trois cas, le code peut enregistrer une demande traitée,
 * et les demandes de l'ancien schéma gardent qui les a traitées.
 */
final class TimeoffRequestsMigrationTest extends TestCase
{
    private const MIGRATIONS = ['2026_10_14_000001_create_timeoff_requests_table', '2026_10_14_000002_align_timeoff_requests_table'];

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
            CREATE TABLE "timeoff_requests" (
                "id" INTEGER NOT NULL PRIMARY KEY, "store_id" INTEGER NOT NULL, "user_id" INTEGER NOT NULL,
                "type" TEXT NOT NULL DEFAULT 'vacation' CHECK ("type" IN ('vacation','sick','personal','unpaid','other')),
                "start_date" TEXT NOT NULL, "end_date" TEXT NOT NULL, "reason" TEXT NULL DEFAULT NULL,
                "status" TEXT NOT NULL DEFAULT 'pending' CHECK ("status" IN ('pending','approved','refused','cancelled')),
                "reviewed_by" INTEGER NULL DEFAULT NULL, "reviewed_at" TEXT NULL DEFAULT NULL, "review_notes" TEXT NULL DEFAULT NULL,
                "created_at" TEXT NOT NULL DEFAULT (datetime('now')), "updated_at" TEXT NULL DEFAULT NULL);
            CREATE INDEX "idx_timeoff_store_status" ON "timeoff_requests" ("store_id", "status", "start_date");
            INSERT INTO timeoff_requests (id, store_id, user_id, type, start_date, end_date, status, reviewed_by, reviewed_at, review_notes)
                VALUES (7, 1, 2, 'sick', '2026-09-01', '2026-09-02', 'approved', 9, '2026-08-30 10:00:00', 'ok');
            SQL);
    }

    /** Table telle que la créait la migration du Core (déjà au bon schéma). */
    private static function coreMigrationSchema(Capsule $capsule): void
    {
        $capsule->getConnection()->getSchemaBuilder()->create('timeoff_requests', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('store_id');
            $table->string('start_date');
            $table->string('end_date');
            $table->string('type')->default('vacation');
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->text('admin_note')->nullable();
            $table->integer('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
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
    public function testEveryStartingPointEndsWithTheSchemaTheCodeUses(?string $existing): void
    {
        [$runner, $capsule] = $this->freshRunner();
        if ($existing !== null) {
            self::$existing($capsule);
        }

        $this->assertSame(self::MIGRATIONS, $runner->runPendingFor('timeoff', dirname(__DIR__, 2) . '/database/migrations'));

        $schema = $capsule->getConnection()->getSchemaBuilder();
        foreach (['id', 'user_id', 'store_id', 'start_date', 'end_date', 'type', 'status', 'reason', 'admin_note', 'processed_by',
            'processed_at', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue($schema->hasColumn('timeoff_requests', $column), "timeoff_requests.{$column}");
        }
        foreach (['reviewed_by', 'reviewed_at', 'review_notes'] as $column) {
            $this->assertFalse($schema->hasColumn('timeoff_requests', $column), "timeoff_requests.{$column}");
        }

        // Écriture du code (« Ajouter un congé » du gérant, traitement par l'API) : ce qui échouait selon le schéma.
        $repo = new DatabaseTimeoffRequestRepository();
        $saved = $repo->save(['store_id' => 1, 'user_id' => 3, 'type' => 'vacation', 'start_date' => '2026-10-20', 'end_date' => '2026-10-21',
            'status' => 'approved', 'processed_by' => 4, 'processed_at' => '2026-10-14 09:00:00', 'admin_note' => 'vu',
            'created_at' => '2026-10-14 09:00:00']);
        $this->assertSame([4, 'vu'], [(int) $repo->findById((int) $saved['id'])['processed_by'], $repo->findById((int) $saved['id'])['admin_note']]);

        if ($existing === 'legacySqlSchema') {
            $old = $repo->findById(7);
            $this->assertSame([9, '2026-08-30 10:00:00', 'ok', 'sick'], [(int) $old['processed_by'], $old['processed_at'], $old['admin_note'], $old['type']]);
        }
        $this->assertSame([], $runner->runPendingFor('timeoff', dirname(__DIR__, 2) . '/database/migrations'), 'rejouées : rien');
    }
}
