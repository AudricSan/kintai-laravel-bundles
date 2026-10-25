<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Timeclock;

use kintai\Bundles\Installed\Timeclock\Providers\TimeclockAttendanceProvider;
use kintai\Core\Container;
use kintai\Bundles\Installed\Timeclock\Repositories\TimeclockRepositoryInterface;
use PHPUnit\Framework\TestCase;

/** Fournisseur de pointages du bundle (contrat 1.11) : mêmes lignes que la lecture historique de la table par le Core. */
final class TimeclockAttendanceProviderTest extends TestCase
{
    private TimeclockRepositoryInterface $repo;

    private function provider(): TimeclockAttendanceProvider
    {
        $rows = [
            ['id' => 1, 'user_id' => 4, 'store_id' => 1, 'shift_date' => '2026-10-13', 'clock_in_time' => '2026-10-13 09:00:00', 'clock_out_time' => '2026-10-13 17:00:00'],
            ['id' => 2, 'user_id' => 4, 'store_id' => 1, 'shift_date' => '2026-10-14', 'clock_in_time' => '2026-10-14 08:58:00', 'clock_out_time' => null],
            ['id' => 3, 'user_id' => 5, 'store_id' => 2, 'shift_date' => '2026-10-14', 'clock_in_time' => '2026-10-14 10:00:00', 'clock_out_time' => ''],
            ['id' => 4, 'user_id' => 5, 'store_id' => 2, 'shift_date' => '2026-10-14', 'clock_in_time' => null, 'clock_out_time' => null],
        ];
        $this->repo = $this->createStub(TimeclockRepositoryInterface::class);
        $this->repo->method('findAll')->willReturn($rows);
        $this->repo->method('findByStoreBetween')->willReturnCallback(fn(int $s, string $from, string $to) => array_values(array_filter(
            $rows,
            fn($r) => $r['store_id'] === $s && $r['shift_date'] >= $from && $r['shift_date'] <= $to
        )));
        $container = new Container();
        $container->instance(TimeclockRepositoryInterface::class, $this->repo);
        return new TimeclockAttendanceProvider($container);
    }

    public function testFiltersAndShapesLikeTheLegacyRows(): void
    {
        $provider = $this->provider();

        $this->assertSame([1, 2, 3], array_map(fn($a) => $a->id, $provider->attendance(null, null)), 'pointage sans arrivée ignoré');
        $this->assertSame([2], array_map(fn($a) => $a->id, $provider->attendance(null, [1], '2026-10-14', '2026-10-14')));
        $this->assertSame([1, 2], array_map(fn($a) => $a->id, $provider->attendance([4], null)));
        $this->assertSame(
            ['id' => 3, 'user_id' => 5, 'store_id' => 2, 'shift_date' => '2026-10-14', 'clock_in_time' => '2026-10-14 10:00:00', 'clock_out_time' => null],
            $provider->attendance([5], null)[0]->toArray(),
            'départ vide = pointage en cours'
        );
    }
}
