<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\PlanningCheck\Services;

use kintai\Bundles\Installed\PlanningCheck\Services\PlanningCheckPolicy;
use PHPUnit\Framework\TestCase;

/** Règles de temps : jour visé, ouverture de la fenêtre, retard, jours concernés, empreinte des shifts. */
final class PlanningCheckPolicyTest extends TestCase
{
    private const SETTINGS = ['window_start_time' => '06:00', 'deadline_time' => '12:00', 'weekdays' => [1, 2, 3, 4, 5, 6]];

    public function testTheTargetDayIsTomorrowInTheStoreTimezone(): void
    {
        // 16:30 UTC le 21 = 01:30 le 22 à Tokyo : à Tokyo, « demain » est le 23 ; à Paris (18:30) c'est le 22.
        $now = new \DateTimeImmutable('2026-10-21 16:30:00', new \DateTimeZone('UTC'));

        $this->assertSame('2026-10-23', PlanningCheckPolicy::targetDate('Asia/Tokyo', $now));
        $this->assertSame('2026-10-22', PlanningCheckPolicy::targetDate('Europe/Paris', $now));
        $this->assertSame('2026-10-22', PlanningCheckPolicy::targetDate('Not/AZone', $now), 'fuseau inconnu : UTC');
    }

    public function testTheWindowOpensAtTheStartTimeAndTheCheckIsLateAfterTheDeadline(): void
    {
        $at = static fn(string $time): \DateTimeImmutable => new \DateTimeImmutable('2026-10-21 ' . $time, new \DateTimeZone('Asia/Tokyo'));

        $this->assertFalse(PlanningCheckPolicy::windowOpen(self::SETTINGS, 'Asia/Tokyo', $at('05:59')));
        $this->assertTrue(PlanningCheckPolicy::windowOpen(self::SETTINGS, 'Asia/Tokyo', $at('06:00')));
        $this->assertFalse(PlanningCheckPolicy::isLate(self::SETTINGS, 'Asia/Tokyo', $at('11:59')));
        $this->assertTrue(PlanningCheckPolicy::isLate(self::SETTINGS, 'Asia/Tokyo', $at('12:00')));
    }

    public function testTimesAreEvaluatedInTheStoreTimezoneNotTheServerOne(): void
    {
        $utcNoon = new \DateTimeImmutable('2026-10-21 12:00:00', new \DateTimeZone('UTC')); // 21:00 à Tokyo, 14:00 à Paris

        $this->assertTrue(PlanningCheckPolicy::isLate(self::SETTINGS, 'Asia/Tokyo', $utcNoon));
        $this->assertTrue(PlanningCheckPolicy::isLate(self::SETTINGS, 'Europe/Paris', $utcNoon));
        $this->assertFalse(PlanningCheckPolicy::isLate(self::SETTINGS, 'America/New_York', $utcNoon), '08:00 à New York');
    }

    public function testWorkingDaysFollowTheCheckedWeekdays(): void
    {
        $this->assertTrue(PlanningCheckPolicy::isWorkingDay(self::SETTINGS, '2026-10-24'), 'samedi (6)');
        $this->assertFalse(PlanningCheckPolicy::isWorkingDay(self::SETTINGS, '2026-10-25'), 'dimanche (7) décoché');
    }

    public function testTheShiftsHashChangesWhenAShiftIsAddedChangedOrRemovedButNotWhenReordered(): void
    {
        $a = ['id' => 1, 'user_id' => 5, 'shift_type_id' => 2, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'pause_minutes' => 30];
        $b = ['id' => 2, 'user_id' => 6, 'shift_type_id' => 2, 'start_time' => '10:00:00', 'end_time' => '18:00:00', 'pause_minutes' => 30];

        $base = PlanningCheckPolicy::shiftsHash([$a, $b]);

        $this->assertSame($base, PlanningCheckPolicy::shiftsHash([$b, $a]), 'ordre sans effet');
        $this->assertNotSame($base, PlanningCheckPolicy::shiftsHash([$a]));
        $this->assertNotSame($base, PlanningCheckPolicy::shiftsHash([$a, [...$b, 'end_time' => '19:00:00']]));
        $this->assertSame(PlanningCheckPolicy::shiftsHash([$a]), PlanningCheckPolicy::shiftsHash([$a, [...$b, 'deleted_at' => '2026-10-20 10:00:00']]), 'un shift supprimé ne compte pas');
    }
}
