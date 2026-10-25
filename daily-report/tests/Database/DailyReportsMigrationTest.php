<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\DailyReport\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\DailyReport\Repositories\DatabaseDailyReportRepository;
use kintai\Core\Database\BundleMigrationRunner;
use PHPUnit\Framework\TestCase;

/**
 * Migration du bundle (reprise du Core à l'extraction), jouée par le vrai BundleMigrationRunner : crée la table avec le
 * schéma qu'utilise le code sur une installation neuve, ne touche pas à celle d'une instance existante.
 */
final class DailyReportsMigrationTest extends TestCase
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

        $this->assertSame(['2026_10_14_000001_create_daily_reports_table'], $runner->runPendingFor('daily-report', $this->migrationsPath()));

        $schema = $capsule->getConnection()->getSchemaBuilder();
        foreach (['id', 'store_id', 'author_id', 'report_date', 'sales_total', 'customer_count', 'labor_cost', 'waste_total',
            'notes', 'data', 'status', 'submitted_at', 'validated_by', 'validated_at', 'mail_sent_at', 'created_at',
            'updated_at', 'deleted_at'] as $column) {
            $this->assertTrue($schema->hasColumn('daily_reports', $column), "daily_reports.{$column}");
        }
        foreach (['is_finalized', 'finalized_by', 'finalized_at', 'pdf_path', 'pdf_generated_at'] as $column) {
            $this->assertFalse($schema->hasColumn('daily_reports', $column), "colonne retirée par le Core : {$column}");
        }

        $repo = new DatabaseDailyReportRepository();
        $saved = $repo->save(['store_id' => 1, 'author_id' => 2, 'report_date' => '2026-10-13', 'sales_total' => 1200, 'status' => 'submitted']);
        $this->assertSame('submitted', $repo->findById((int) $saved['id'])['status']);
        $this->assertSame([], $runner->runPendingFor('daily-report', $this->migrationsPath()), 'rejouée : rien');
    }

    /** Instance existante : la table créée par le Core et ses données restent telles quelles. */
    public function testExistingTableAndDataAreLeftUntouched(): void
    {
        [$runner, $capsule] = $this->freshRunner();
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('daily_reports', function ($table) {
            $table->increments('id');
            $table->integer('store_id');
            $table->string('report_date');
        });
        $capsule->table('daily_reports')->insert(['store_id' => 3, 'report_date' => '2026-10-01']);

        $runner->runPendingFor('daily-report', $this->migrationsPath());

        $this->assertSame(1, $capsule->table('daily_reports')->count());
        $this->assertFalse($schema->hasColumn('daily_reports', 'notes'), 'table existante non recréée');
    }
}
