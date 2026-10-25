<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftClaim\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\ShiftClaim\Repositories\DatabaseShiftClaimRepository;
use kintai\Core\Database\BundleMigrationRunner;
use PHPUnit\Framework\TestCase;

/**
 * Migration du bundle (reprise du Core à l'extraction), jouée par le vrai BundleMigrationRunner : crée la table sur
 * une installation neuve, ne touche pas à celle d'une instance existante.
 */
final class ShiftClaimsMigrationTest extends TestCase
{
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

    public function testFreshInstallCreatesTheTableTheCodeUses(): void
    {
        [$runner, $capsule] = $this->freshRunner();

        $this->assertSame(['2026_10_14_000001_create_shift_claims_table'], $runner->runPendingFor('shift-claim', $this->migrationsPath()));

        $schema = $capsule->getConnection()->getSchemaBuilder();
        foreach (['id', 'shift_id', 'user_id', 'store_id', 'status', 'note', 'claimed_at', 'resolved_at', 'resolved_by', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue($schema->hasColumn('shift_claims', $column), "shift_claims.{$column}");
        }
        $repo = new DatabaseShiftClaimRepository();
        $saved = $repo->save(['shift_id' => 8, 'user_id' => 2, 'store_id' => 5]);
        $this->assertSame('pending', $repo->findById((int) $saved['id'])['status']);
        $this->assertSame([], $runner->runPendingFor('shift-claim', $this->migrationsPath()), 'rejouée : rien');
    }

    /** Instance existante : la table créée par le Core et ses données restent telles quelles. */
    public function testExistingTableAndDataAreLeftUntouched(): void
    {
        [$runner, $capsule] = $this->freshRunner();
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('shift_claims', function ($table) {
            $table->increments('id');
            $table->integer('shift_id');
        });
        $capsule->table('shift_claims')->insert(['shift_id' => 3]);

        $runner->runPendingFor('shift-claim', $this->migrationsPath());

        $this->assertSame(1, $capsule->table('shift_claims')->count());
        $this->assertFalse($schema->hasColumn('shift_claims', 'note'), 'table existante non recréée');
    }
}
