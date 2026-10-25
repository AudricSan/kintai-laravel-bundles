<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ResignationReport\Repositories;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\ResignationReport\Repositories\DatabaseResignationReportRepository;
use kintai\Core\Database\BundleMigrationRunner;
use PHPUnit\Framework\TestCase;

/**
 * DatabaseResignationReportRepository::findAll() filtre par année/mois (sur resignation_date, une vraie colonne DATE)
 * et par responsable, pour la barre de filtres de /admin/reports/resignation. Vérifié contre une vraie base (table
 * créée par la migration du bundle), car whereYear()/whereMonth() dépendent du pilote, ce qu'un dépôt simulé ne peut
 * pas vérifier. Repris du Core à l'extraction (étape 4 du plan du contrat).
 */
final class ResignationReportFilterTest extends TestCase
{
    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $runner = (new \ReflectionClass(BundleMigrationRunner::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(BundleMigrationRunner::class, 'capsule'))->setValue($runner, $capsule);
        $runner->runPendingFor('resignation-report', dirname(__DIR__, 2) . '/database/migrations');

        $now = date('Y-m-d H:i:s');
        $capsule->table('resignation_reports')->insert([
            'store_id' => 1, 'employee_name' => 'Jean Dupont', 'resignation_date' => '2026-07-10',
            'person_in_charge' => 'Alice Dupont', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $capsule->table('resignation_reports')->insert([
            'store_id' => 1, 'employee_name' => 'Paul Martin', 'resignation_date' => '2026-08-15',
            'person_in_charge' => 'Bob Sato', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function testFiltersByYearAndMonth(): void
    {
        $repo = new DatabaseResignationReportRepository();

        $this->assertCount(1, $repo->findAll(null, ['year' => '2026', 'month' => '07']));
        $this->assertCount(1, $repo->findAll(null, ['year' => '2026', 'month' => '08']));
        $this->assertCount(0, $repo->findAll(null, ['year' => '2025']));
        $this->assertCount(2, $repo->findAll(null, ['year' => '2026']));
    }

    public function testFiltersByPersonInChargeSubstring(): void
    {
        $repo = new DatabaseResignationReportRepository();

        $this->assertCount(1, $repo->findAll(null, ['person_in_charge' => 'Dupont']));
        $this->assertCount(1, $repo->findAll(null, ['person_in_charge' => 'Sato']));
        $this->assertCount(0, $repo->findAll(null, ['person_in_charge' => 'Nobody']));
    }

    public function testNoFiltersReturnsEverything(): void
    {
        $repo = new DatabaseResignationReportRepository();

        $this->assertCount(2, $repo->findAll());
    }
}
