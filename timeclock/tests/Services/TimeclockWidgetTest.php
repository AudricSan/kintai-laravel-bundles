<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Timeclock\Services;

use kintai\Bundles\Installed\Timeclock\Repositories\TimeclockRepositoryInterface;
use kintai\Bundles\Installed\Timeclock\Services\TimeclockWidget;
use kintai\Core\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Widgets de pointage des tableaux de bord (contrat 1.4) : mêmes pointages que ceux que le Core affichait.
 */
final class TimeclockWidgetTest extends TestCase
{
    public function testManagerSeesTodaysOpenClockInsOfItsStores(): void
    {
        $timeclocks = $this->createStub(TimeclockRepositoryInterface::class);
        $timeclocks->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 1, 'user_id' => 5, 'shift_date' => '2026-10-14', 'clock_in_time' => '2026-10-14 09:00:00', 'clock_out_time' => null],
            ['id' => 2, 'store_id' => 1, 'user_id' => 6, 'shift_date' => '2026-10-14', 'clock_in_time' => '2026-10-14 08:00:00', 'clock_out_time' => '2026-10-14 12:00:00'],
            ['id' => 3, 'store_id' => 2, 'user_id' => 6, 'shift_date' => '2026-10-14', 'clock_in_time' => '2026-10-14 10:00:00', 'clock_out_time' => null],
            ['id' => 4, 'store_id' => 1, 'user_id' => 7, 'shift_date' => '2026-10-13', 'clock_in_time' => '2026-10-13 22:00:00', 'clock_out_time' => null],
            ['id' => 5, 'store_id' => 2, 'user_id' => 8, 'shift_date' => '2026-10-14', 'clock_in_time' => '2026-10-14 07:00:00', 'clock_out_time' => null],
        ]);
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findAll')->willReturn([['id' => 5, 'display_name' => 'Sato']]);

        $active = (new TimeclockWidget($timeclocks, $users))->activeNow([1], [1 => 'Tokorozawa'], '2026-10-14');

        $this->assertSame([1], array_column($active, 'id'));
        $this->assertSame([5, 1, 3], array_column((new TimeclockWidget($timeclocks, $users))->activeNow([1, 2], [], '2026-10-14'), 'id'), 'plus ancienne entrée d\'abord');
        $this->assertSame(['Sato', 'Tokorozawa'], [$active[0]['user_name'], $active[0]['store_name']]);
    }

    public function testEmployeeSeesItsOpenClockIn(): void
    {
        $timeclocks = $this->createStub(TimeclockRepositoryInterface::class);
        $timeclocks->method('findActiveByUser')->willReturnMap([[5, ['id' => 1, 'user_id' => 5]], [6, null]]);
        $widget = new TimeclockWidget($timeclocks, $this->createStub(UserRepositoryInterface::class));

        $this->assertSame(1, $widget->activeForUser(5)['id']);
        $this->assertNull($widget->activeForUser(6));
    }
}
