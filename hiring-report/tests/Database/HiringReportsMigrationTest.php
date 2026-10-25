<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\HiringReport\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\HiringReport\Controllers\Web\AdminHiringReportController;
use kintai\Bundles\Installed\HiringReport\Repositories\DatabaseHiringReportRepository;
use kintai\Core\Database\BundleMigrationRunner;
use PHPUnit\Framework\TestCase;

/**
 * Migration du bundle (reprise du Core à l'extraction), jouée par le vrai BundleMigrationRunner : crée la table avec le
 * schéma qu'utilise le code sur une installation neuve, ne touche pas à celle d'une instance existante.
 */
final class HiringReportsMigrationTest extends TestCase
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

        $this->assertSame(['2026_10_14_000001_create_hiring_reports_table'], $runner->runPendingFor('hiring-report', $this->migrationsPath()));

        $schema = $capsule->getConnection()->getSchemaBuilder();
        foreach (['id', 'store_id', 'user_id', 'employee_number', 'employee_name', 'furigana_last_name', 'furigana_first_name',
            'gender', 'tax_classification', 'birth_date', 'hire_date', 'education', 'postal_code', 'address', 'phone',
            'mobile_phone', 'email', 'guarantor_name', 'guarantor_phone', 'store_name', 'hired_by', 'notes', 'created_by',
            'created_at', 'updated_at'] as $column) {
            $this->assertTrue($schema->hasColumn('hiring_reports', $column), "hiring_reports.{$column}");
        }
        $this->assertFalse($schema->hasColumn('hiring_reports', 'furigana'), 'colonne combinée retirée par le Core');

        $repo = new DatabaseHiringReportRepository();
        $saved = $repo->save(['store_id' => 1, 'user_id' => 2, 'employee_name' => 'Sato', 'furigana_last_name' => 'サトウ', 'created_by' => 1]);
        $this->assertSame('サトウ', $repo->findById((int) $saved['id'])['furigana_last_name']);
        $this->assertSame([], $runner->runPendingFor('hiring-report', $this->migrationsPath()), 'rejouée : rien');
    }

    /**
     * Chaque champ que le formulaire écrit (AdminHiringReportController::REPORT_TYPE) doit exister dans la table : un
     * champ « furigana » resté après la suppression de la colonne combinée faisait échouer toute création et toute
     * modification manuelle d'un rapport (erreur SQL), sans qu'aucun test ne le voie (dépôt simulé).
     */
    public function testEveryFormFieldIsAColumnOfTheTable(): void
    {
        [$runner, $capsule] = $this->freshRunner();
        $runner->runPendingFor('hiring-report', $this->migrationsPath());
        $schema = $capsule->getConnection()->getSchemaBuilder();

        $type = (new \ReflectionClassConstant(AdminHiringReportController::class, 'REPORT_TYPE'))->getValue();
        $this->assertNotEmpty($type['fields']);
        foreach (array_keys($type['fields']) as $field) {
            $this->assertTrue($schema->hasColumn('hiring_reports', $field), "champ du formulaire sans colonne : {$field}");
        }

        $row = array_fill_keys(array_keys($type['fields']), null) + ['store_id' => 1, 'created_by' => 1];
        $saved = (new DatabaseHiringReportRepository())->save($row);
        $this->assertGreaterThan(0, (int) $saved['id'], 'un rapport avec tous les champs du formulaire s\'enregistre');
    }

    /** Instance existante : la table créée par le Core et ses données restent telles quelles. */
    public function testExistingTableAndDataAreLeftUntouched(): void
    {
        [$runner, $capsule] = $this->freshRunner();
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('hiring_reports', function ($table) {
            $table->increments('id');
            $table->integer('store_id');
        });
        $capsule->table('hiring_reports')->insert(['store_id' => 3]);

        $runner->runPendingFor('hiring-report', $this->migrationsPath());

        $this->assertSame(1, $capsule->table('hiring_reports')->count());
        $this->assertFalse($schema->hasColumn('hiring_reports', 'notes'), 'table existante non recréée');
    }
}
