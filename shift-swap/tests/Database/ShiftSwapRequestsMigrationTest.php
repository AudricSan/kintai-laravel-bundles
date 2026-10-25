<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftSwap\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\ShiftSwap\Repositories\DatabaseShiftSwapRequestRepository;
use kintai\Core\Database\BundleMigrationRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Migrations du bundle, jouées par le vrai BundleMigrationRunner, sur les trois états possibles d'une base :
 * installation neuve, table de l'ancien schéma SQL (target_id obligatoire, reviewed_*, peer_accepted_at ; créer un
 * échange y échouait) et table de la migration du Core (déjà au bon schéma). Dans les trois cas, le code peut créer et
 * lire une demande ; les lignes de l'ancien schéma sont recopiées.
 */
final class ShiftSwapRequestsMigrationTest extends TestCase
{
    private const MIGRATIONS = ['2026_10_14_000001_create_shift_swap_requests_table', '2026_10_14_000002_align_shift_swap_requests_table'];

    private function migrationsPath(): string
    {
        return dirname(__DIR__, 2) . '/database/migrations';
    }

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
            CREATE TABLE "shift_swap_requests" (
                "id" INTEGER NOT NULL PRIMARY KEY, "store_id" INTEGER NOT NULL, "requester_id" INTEGER NOT NULL,
                "target_id" INTEGER NOT NULL, "requester_shift_id" INTEGER NOT NULL, "target_shift_id" INTEGER NULL DEFAULT NULL,
                "status" TEXT NOT NULL DEFAULT 'pending' CHECK ("status" IN ('pending','accepted','refused','cancelled')),
                "reason" TEXT NULL DEFAULT NULL, "reviewed_by" INTEGER NULL DEFAULT NULL, "reviewed_at" TEXT NULL DEFAULT NULL,
                "created_at" TEXT NOT NULL DEFAULT (datetime('now')), "updated_at" TEXT NULL DEFAULT NULL,
                "peer_accepted_at" TEXT NULL DEFAULT NULL);
            INSERT INTO shift_swap_requests (id, store_id, requester_id, target_id, requester_shift_id, status, reviewed_by,
                reviewed_at, created_at, peer_accepted_at)
                VALUES (7, 1, 2, 3, 40, 'accepted', 9, '2026-09-02 10:00:00', '2026-09-01 08:00:00', '2026-09-01 12:00:00');
            SQL);
    }

    /** Table telle que la créait la migration du Core (déjà au bon schéma). */
    private static function coreMigrationSchema(Capsule $capsule): void
    {
        $capsule->getConnection()->getSchemaBuilder()->create('shift_swap_requests', function ($table) {
            $table->increments('id');
            $table->integer('store_id');
            $table->integer('requester_id');
            $table->integer('target_user_id')->nullable();
            $table->integer('requester_shift_id');
            $table->integer('target_shift_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->integer('accepted_by_id')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->integer('approved_by_id')->nullable();
            $table->timestamp('approved_at')->nullable();
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

        $this->assertSame(self::MIGRATIONS, $runner->runPendingFor('shift-swap', $this->migrationsPath()));

        $schema = $capsule->getConnection()->getSchemaBuilder();
        foreach (['id', 'store_id', 'requester_id', 'target_user_id', 'requester_shift_id', 'target_shift_id', 'status', 'reason',
            'accepted_by_id', 'accepted_at', 'approved_by_id', 'approved_at', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue($schema->hasColumn('shift_swap_requests', $column), "shift_swap_requests.{$column}");
        }
        $this->assertFalse($schema->hasColumn('shift_swap_requests', 'target_id'));
        $this->assertFalse($schema->hasTable('shift_swap_requests_legacy_schema'));

        // Écriture du code : ce qui échouait sur l'ancien schéma.
        $repo = new DatabaseShiftSwapRequestRepository();
        $saved = $repo->save(['store_id' => 1, 'requester_id' => 2, 'target_user_id' => 4, 'requester_shift_id' => 41,
            'status' => 'pending', 'accepted_at' => null, 'created_at' => '2026-10-14 09:00:00']);
        $this->assertSame(4, (int) $repo->findById((int) $saved['id'])['target_user_id']);

        if ($existing === 'legacySqlSchema') {
            $old = $repo->findById(7);
            $this->assertSame([3, 'accepted', 9, '2026-09-02 10:00:00', '2026-09-01 12:00:00', '2026-09-01 08:00:00'],
                [(int) $old['target_user_id'], $old['status'], (int) $old['approved_by_id'], $old['approved_at'], $old['accepted_at'], $old['created_at']]);
        }
        $this->assertSame([], $runner->runPendingFor('shift-swap', $this->migrationsPath()), 'rejouées : rien');
    }
}
