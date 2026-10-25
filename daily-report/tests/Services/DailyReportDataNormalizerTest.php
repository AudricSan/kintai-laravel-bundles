<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\DailyReport\Services;

use PHPUnit\Framework\TestCase;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportDataNormalizer;

final class DailyReportDataNormalizerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // cumulativeModeOf
    // -------------------------------------------------------------------------

    public function testCumulativeModeOfDefaultsToPerDayWhenNoSettings(): void
    {
        $this->assertSame('per_day', DailyReportDataNormalizer::cumulativeModeOf(['id' => 1]));
    }

    public function testCumulativeModeOfDefaultsToPerDayWhenNullSettings(): void
    {
        $this->assertSame('per_day', DailyReportDataNormalizer::cumulativeModeOf(['id' => 1, 'daily_report_settings' => null]));
    }

    public function testCumulativeModeOfDefaultsToPerDayWhenInvalidJson(): void
    {
        $this->assertSame('per_day', DailyReportDataNormalizer::cumulativeModeOf(['daily_report_settings' => 'not json']));
    }

    public function testCumulativeModeOfReadsCumulativeInput(): void
    {
        $store = ['daily_report_settings' => json_encode(['cumulative_mode' => 'cumulative_input'])];
        $this->assertSame('cumulative_input', DailyReportDataNormalizer::cumulativeModeOf($store));
    }

    // -------------------------------------------------------------------------
    // toDailyDeltas
    // -------------------------------------------------------------------------

    public function testPerDayModeReturnsReportsUnchanged(): void
    {
        $reports = [
            ['report_date' => '2026-07-01', 'sales_total' => 1000, 'customer_count' => 10, 'labor_cost' => 200, 'waste_total' => 5],
            ['report_date' => '2026-07-02', 'sales_total' => 1100, 'customer_count' => 12, 'labor_cost' => 210, 'waste_total' => 6],
        ];

        $this->assertSame($reports, DailyReportDataNormalizer::toDailyDeltas($reports, 'per_day'));
    }

    public function testCumulativeSystemModeReturnsReportsUnchanged(): void
    {
        $reports = [
            ['report_date' => '2026-07-01', 'sales_total' => 1000, 'customer_count' => 10, 'labor_cost' => 200, 'waste_total' => 5],
        ];

        $this->assertSame($reports, DailyReportDataNormalizer::toDailyDeltas($reports, 'cumulative_system'));
    }

    public function testCumulativeInputModeConvertsToDeltas(): void
    {
        // Saisies cumulées depuis le début du mois : jour 1 = 1000, jour 2 = 2100 (soit +1100), jour 3 = 3300 (soit +1200)
        $reports = [
            ['report_date' => '2026-07-01', 'sales_total' => 1000, 'customer_count' => 10, 'labor_cost' => 200, 'waste_total' => 5],
            ['report_date' => '2026-07-02', 'sales_total' => 2100, 'customer_count' => 22, 'labor_cost' => 410, 'waste_total' => 11],
            ['report_date' => '2026-07-03', 'sales_total' => 3300, 'customer_count' => 33, 'labor_cost' => 610, 'waste_total' => 15],
        ];

        $deltas = DailyReportDataNormalizer::toDailyDeltas($reports, 'cumulative_input');

        $this->assertSame(1000.0, $deltas[0]['sales_total']);
        $this->assertSame(1100.0, $deltas[1]['sales_total']);
        $this->assertSame(1200.0, $deltas[2]['sales_total']);

        $this->assertSame(10.0, $deltas[0]['customer_count']);
        $this->assertSame(12.0, $deltas[1]['customer_count']);
        $this->assertSame(11.0, $deltas[2]['customer_count']);

        $this->assertSame(200.0, $deltas[0]['labor_cost']);
        $this->assertSame(210.0, $deltas[1]['labor_cost']);
        $this->assertSame(200.0, $deltas[2]['labor_cost']);

        $this->assertSame(5.0, $deltas[0]['waste_total']);
        $this->assertSame(6.0, $deltas[1]['waste_total']);
        $this->assertSame(4.0, $deltas[2]['waste_total']);
    }

    public function testCumulativeInputModeClampsNegativeDeltasToZero(): void
    {
        // Erreur de saisie : le jour 2 est inférieur au jour 1 — le delta ne doit jamais être négatif.
        $reports = [
            ['report_date' => '2026-07-01', 'sales_total' => 1000, 'customer_count' => 10, 'labor_cost' => 200, 'waste_total' => 5],
            ['report_date' => '2026-07-02', 'sales_total' => 800,  'customer_count' => 8,  'labor_cost' => 150, 'waste_total' => 3],
        ];

        $deltas = DailyReportDataNormalizer::toDailyDeltas($reports, 'cumulative_input');

        $this->assertSame(0.0, $deltas[1]['sales_total']);
        $this->assertSame(0.0, $deltas[1]['customer_count']);
        $this->assertSame(0.0, $deltas[1]['labor_cost']);
        $this->assertSame(0.0, $deltas[1]['waste_total']);
    }

    public function testCumulativeInputModePreservesOtherFields(): void
    {
        $reports = [
            ['id' => 5, 'report_date' => '2026-07-01', 'status' => 'validated', 'sales_total' => 1000, 'customer_count' => 10, 'labor_cost' => 200, 'waste_total' => 5],
        ];

        $deltas = DailyReportDataNormalizer::toDailyDeltas($reports, 'cumulative_input');

        $this->assertSame(5, $deltas[0]['id']);
        $this->assertSame('validated', $deltas[0]['status']);
    }
}
