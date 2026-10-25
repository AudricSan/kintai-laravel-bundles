<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\SalaryReport\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\SalaryReport\Repositories\DatabaseSalaryReportRepository;
use kintai\Core\Database\BundleMigrationRunner;
use PHPUnit\Framework\TestCase;

/**
 * Migration du bundle (reprise du Core à l'extraction), jouée par le vrai BundleMigrationRunner : crée la table avec le
 * schéma qu'utilise le code sur une installation neuve, ne touche pas à celle d'une instance existante.
 */
final class SalaryReportsMigrationTest extends TestCase
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

        $this->assertSame(['2026_10_14_000001_create_salary_reports_table'], $runner->runPendingFor('salary-report', $this->migrationsPath()));

        $schema = $capsule->getConnection()->getSchemaBuilder();
        foreach (['id', 'store_id', 'user_id', 'employee_name', 'target_month', 'store_name', 'person_in_charge', 'total_payment',
            'total_deductions', 'income_tax_base', 'withholding_tax', 'residence_tax', 'other_deductions', 'net_payment',
            'active_employees', 'hand_delivered_salary', 'bank_transfer_salary', 'staff_man_hours', 'staff_total_payment',
            'staff_avg_hourly_wage', 'employee_work_hours', 'new_hires', 'resigned_staff', 'hire_registrations', 'remarks',
            'created_by', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue($schema->hasColumn('salary_reports', $column), "salary_reports.{$column}");
        }

        // Rapport global du magasin et rapport d'un employé pour le même mois : permis.
        $repo = new DatabaseSalaryReportRepository();
        $repo->save(['store_id' => 1, 'target_month' => '2026-10', 'total_payment' => 1000]);
        $mine = $repo->save(['store_id' => 1, 'target_month' => '2026-10', 'user_id' => 7, 'employee_name' => 'Sato', 'bank_transfer_salary' => 250]);
        $this->assertEquals(250, $repo->findById((int) $mine['id'])['bank_transfer_salary']);

        // Deux rapports du même employé pour le même mois : refusés.
        $this->expectException(\Throwable::class);
        $repo->save(['store_id' => 1, 'target_month' => '2026-10', 'user_id' => 7]);
    }

    /** Instance existante : la table créée par le Core et ses données restent telles quelles. */
    public function testExistingTableAndDataAreLeftUntouched(): void
    {
        [$runner, $capsule] = $this->freshRunner();
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('salary_reports', function ($table) {
            $table->increments('id');
            $table->integer('store_id');
            $table->string('target_month', 7);
        });
        $capsule->table('salary_reports')->insert(['store_id' => 3, 'target_month' => '2026-09']);

        $this->assertSame(['2026_10_14_000001_create_salary_reports_table'], $runner->runPendingFor('salary-report', $this->migrationsPath()));

        $this->assertSame(1, $capsule->table('salary_reports')->count());
        $this->assertFalse($schema->hasColumn('salary_reports', 'remarks'), 'table existante non recréée');
        $this->assertSame([], $runner->runPendingFor('salary-report', $this->migrationsPath()), 'rejouée : rien');
    }
}
