<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\StorePhoto\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Core\Database\BundleMigrationRunner;
use PHPUnit\Framework\TestCase;

/**
 * Migration du bundle (reprise du Core à l'extraction), jouée par le vrai BundleMigrationRunner : crée les tables sur
 * une installation neuve, ne touche pas à celles d'une instance existante.
 */
final class StorePhotosMigrationTest extends TestCase
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

    public function testFreshInstallCreatesBothTables(): void
    {
        [$runner, $capsule] = $this->freshRunner();

        $this->assertSame(['2026_10_14_000001_create_store_photos_tables'], $runner->runPendingFor('store-photos', $this->migrationsPath()));

        $schema = $capsule->getConnection()->getSchemaBuilder();
        foreach (['id', 'store_id', 'week_label', 'notes', 'image_count', 'retention_days', 'compressed', 'compressed_at', 'mail_sent_at', 'deleted_at', 'created_by', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue($schema->hasColumn('store_photo_submissions', $column), "store_photo_submissions.{$column}");
        }
        foreach (['id', 'submission_id', 'filename', 'filepath', 'filesize', 'mime_type', 'compressed_filesize', 'width', 'height', 'sort_order', 'created_at'] as $column) {
            $this->assertTrue($schema->hasColumn('store_photo_images', $column), "store_photo_images.{$column}");
        }
        $this->assertSame([], $runner->runPendingFor('store-photos', $this->migrationsPath()), 'rejouée : rien');
    }

    /** Instance existante : les tables créées par le Core et leurs données restent telles quelles. */
    public function testExistingTablesAndDataAreLeftUntouched(): void
    {
        [$runner, $capsule] = $this->freshRunner();
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('store_photo_submissions', function ($table) {
            $table->increments('id');
            $table->integer('store_id');
            $table->string('week_label', 10);
        });
        $schema->create('store_photo_images', function ($table) {
            $table->increments('id');
            $table->integer('submission_id');
        });
        $capsule->table('store_photo_submissions')->insert(['store_id' => 3, 'week_label' => '2026-10-01']);

        $runner->runPendingFor('store-photos', $this->migrationsPath());

        $this->assertSame(1, $capsule->table('store_photo_submissions')->count());
        $this->assertFalse($schema->hasColumn('store_photo_submissions', 'notes'), 'table existante non recréée');
    }
}
