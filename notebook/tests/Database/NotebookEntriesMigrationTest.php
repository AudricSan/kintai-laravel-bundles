<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Notebook\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Database\BundleMigrationRunner;
use PHPUnit\Framework\TestCase;

/**
 * Exécute la vraie migration du bundle (database/migrations/) via le vrai
 * BundleMigrationRunner du Core — exactement le mécanisme que
 * BundleInstallerService::activate() déclenche à l'installation, voir
 * docs/creating-a-bundle.md "Database migrations" du repo principal Kintai.
 */
final class NotebookEntriesMigrationTest extends TestCase
{
    private function migrationsPath(): string
    {
        return dirname(__DIR__, 2) . '/database/migrations';
    }

    private function freshRunner(): array
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $runner = (new \ReflectionClass(BundleMigrationRunner::class))->newInstanceWithoutConstructor();
        $capsuleProp = new \ReflectionProperty(BundleMigrationRunner::class, 'capsule');
        $capsuleProp->setAccessible(true);
        $capsuleProp->setValue($runner, $capsule);

        return [$runner, $capsule];
    }

    public function testMigrationCreatesTheNotebookEntriesTableWithExpectedColumns(): void
    {
        [$runner, $capsule] = $this->freshRunner();

        $applied = $runner->runPendingFor('notebook', $this->migrationsPath());

        $this->assertSame(['2026_09_25_000001_create_notebook_entries_table'], $applied);

        $schema = $capsule->getConnection()->getSchemaBuilder();
        $this->assertTrue($schema->hasTable('notebook_entries'));
        foreach (['id', 'author_id', 'store_id', 'content', 'pinned', 'expires_at', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue($schema->hasColumn('notebook_entries', $column), "Colonne manquante : {$column}");
        }
    }

    public function testStoreIdIsNullableSoAnOrgWideNoteCanBeInserted(): void
    {
        [$runner, $capsule] = $this->freshRunner();
        $runner->runPendingFor('notebook', $this->migrationsPath());

        // author_id doit référencer un user existant (contrainte FK) pour que l'insertion
        // passe sur un moteur qui l'applique réellement (MySQL — SQLite ne l'impose pas ici,
        // voir CLAUDE.md du repo principal, mais on insère quand même une ligne users cohérente).
        $capsule->getConnection()->getSchemaBuilder()->create('users', function ($table) {
            $table->increments('id');
        });
        $capsule->table('users')->insert(['id' => 1]);

        $id = $capsule->table('notebook_entries')->insertGetId([
            'author_id'  => 1,
            'store_id'   => null,
            'content'    => 'Pour toute l\'organisation',
            'pinned'     => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $row = $capsule->table('notebook_entries')->find($id);
        $this->assertNull($row->store_id);
    }

    public function testMigrationIsIdempotentWhenReplayed(): void
    {
        [$runner, ] = $this->freshRunner();

        $runner->runPendingFor('notebook', $this->migrationsPath());
        $second = $runner->runPendingFor('notebook', $this->migrationsPath());

        $this->assertSame([], $second);
    }
}
