<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\StorePhoto\Repositories;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\StorePhoto\Repositories\DatabaseStorePhotoRepository;
use kintai\Core\Database\BundleMigrationRunner;
use PHPUnit\Framework\TestCase;

/**
 * DatabaseStorePhotoRepository::findTodaySubmission() permet de fusionner plusieurs envois de photos du même magasin le
 * même jour en un seul rapport (voir StorePhotoController::store()). Le filtrage par plage de created_at (choisi plutôt
 * que whereDate(), dépendant du moteur) est vérifié contre une vraie base, créée par la migration du bundle. Repris du
 * Core avec le dépôt (étape 4 du plan du contrat, extraction).
 */
final class StorePhotoTodaySubmissionTest extends TestCase
{
    private Capsule $capsule;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $this->capsule = $capsule;

        // Tables du Core référencées par les clés étrangères du bundle.
        $schema = $capsule->getConnection()->getSchemaBuilder();
        $schema->create('stores', function ($table) {
            $table->increments('id');
            $table->string('name');
        });
        $schema->create('users', function ($table) {
            $table->increments('id');
        });

        $runner = (new \ReflectionClass(BundleMigrationRunner::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(BundleMigrationRunner::class, 'capsule'))->setValue($runner, $capsule);
        $runner->runPendingFor('store-photos', dirname(__DIR__, 2) . '/database/migrations');

        $capsule->table('stores')->insert(['name' => 'Store A']);
    }

    public function testReturnsSubmissionCreatedTodayForSameStore(): void
    {
        $today = date('Y-m-d');
        $this->capsule->table('store_photo_submissions')->insert([
            'store_id' => 1, 'week_label' => $today, 'image_count' => 2,
            'created_at' => $today . ' 09:00:00',
        ]);

        $found = (new DatabaseStorePhotoRepository())->findTodaySubmission(1, $today);

        $this->assertNotNull($found);
        $this->assertSame(2, $found['image_count']);
    }

    public function testIgnoresSubmissionsFromOtherDays(): void
    {
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $this->capsule->table('store_photo_submissions')->insert([
            'store_id' => 1, 'week_label' => $yesterday, 'image_count' => 1,
            'created_at' => $yesterday . ' 23:59:59',
        ]);

        $this->assertNull((new DatabaseStorePhotoRepository())->findTodaySubmission(1, $today));
    }

    public function testIgnoresSubmissionsFromOtherStores(): void
    {
        $today = date('Y-m-d');
        $this->capsule->table('stores')->insert(['name' => 'Store B']);
        $this->capsule->table('store_photo_submissions')->insert([
            'store_id' => 2, 'week_label' => $today, 'image_count' => 5,
            'created_at' => $today . ' 10:00:00',
        ]);

        $this->assertNull((new DatabaseStorePhotoRepository())->findTodaySubmission(1, $today));
    }

    public function testIgnoresSoftDeletedSubmissions(): void
    {
        $today = date('Y-m-d');
        $this->capsule->table('store_photo_submissions')->insert([
            'store_id' => 1, 'week_label' => $today, 'image_count' => 3,
            'created_at' => $today . ' 08:00:00', 'deleted_at' => $today . ' 08:30:00',
        ]);

        $this->assertNull((new DatabaseStorePhotoRepository())->findTodaySubmission(1, $today));
    }
}
