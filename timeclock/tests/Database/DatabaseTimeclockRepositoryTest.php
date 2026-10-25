<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Timeclock\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\Timeclock\Repositories\DatabaseTimeclockRepository;
use kintai\Core\Database\BundleMigrationRunner;
use PHPUnit\Framework\TestCase;

/**
 * Lecture des pointages d'un magasin sur une période (widget RH du Core, via le fournisseur de pointages) : bornée par
 * date au lieu de parcourir tout l'historique. Repris du Core (TimeclockStoreDateIndexTest) lors de l'extraction.
 */
final class DatabaseTimeclockRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        // Tables référencées par les clés étrangères de timeclocks.
        $capsule->getConnection()->unprepared('CREATE TABLE users (id INTEGER PRIMARY KEY); CREATE TABLE stores (id INTEGER PRIMARY KEY); CREATE TABLE shifts (id INTEGER PRIMARY KEY);');

        $runner = (new \ReflectionClass(BundleMigrationRunner::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(BundleMigrationRunner::class, 'capsule'))->setValue($runner, $capsule);
        $runner->runPendingFor('timeclock', dirname(__DIR__, 2) . '/database/migrations');
    }

    public function testFindByStoreBetweenReturnsOnlyTheStoreAndThePeriodBoundsIncluded(): void
    {
        $db = Capsule::connection();
        foreach ([[1, 1, '2026-06-01'], [2, 1, '2026-06-15'], [3, 1, '2026-06-30'], [4, 1, '2026-07-01'], [5, 2, '2026-06-15']] as [$id, $store, $date]) {
            $db->table('timeclocks')->insert(['id' => $id, 'user_id' => 10, 'store_id' => $store, 'shift_date' => $date, 'clock_in_time' => $date . ' 09:00:00']);
        }

        $ids = array_map('intval', array_column((new DatabaseTimeclockRepository())->findByStoreBetween(1, '2026-06-01', '2026-06-30'), 'id'));

        $this->assertSame([1, 2, 3], $ids);
    }

    public function testAFreshTableHasTheStoreDateIndex(): void
    {
        $columns = array_map(static fn(array $i): array => $i['columns'], Capsule::connection()->getSchemaBuilder()->getIndexes('timeclocks'));

        $this->assertContains(['store_id', 'shift_date'], $columns);
    }
}
