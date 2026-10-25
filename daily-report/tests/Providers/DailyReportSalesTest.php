<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\DailyReport\Providers;

use kintai\Bundles\Installed\DailyReport\Providers\DailyReportSales;
use kintai\Bundles\Installed\DailyReport\Repositories\DailyReportRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Provider\DailySales;
use PHPUnit\Framework\TestCase;

/**
 * Ventes journalières fournies au Core et à salary-report (contrat 1.14) : rapports triés, supprimés exclus, statuts
 * filtrés avant la conversion des saisies cumulées en valeurs du jour.
 */
final class DailyReportSalesTest extends TestCase
{
    private function sales(array $reports, ?string $mode = null): DailyReportSales
    {
        $repo = $this->createStub(DailyReportRepositoryInterface::class);
        $repo->method('findByStoreAndDateRange')->willReturn($reports);
        $stores = $this->createStub(StoreRepositoryInterface::class);
        $stores->method('findById')->willReturn(['id' => 4, 'daily_report_settings' => $mode === null ? null : json_encode(['cumulative_mode' => $mode])]);

        return new DailyReportSales($repo, $stores);
    }

    public function testDaysAreSortedWithoutDeletedReports(): void
    {
        $days = $this->sales([
            ['report_date' => '2026-10-02', 'status' => 'validated', 'sales_total' => '200', 'labor_cost' => '50', 'customer_count' => '20', 'waste_total' => '3'],
            ['report_date' => '2026-10-01', 'status' => 'draft', 'sales_total' => '100'],
            ['report_date' => '2026-10-03', 'status' => 'validated', 'sales_total' => '999', 'deleted_at' => '2026-10-04 00:00:00'],
        ])->dailySales(4, '2026-10-01', '2026-10-31');

        $this->assertContainsOnlyInstancesOf(DailySales::class, $days);
        $this->assertSame(['2026-10-01', '2026-10-02'], array_map(fn(DailySales $d): string => $d->date, $days));
        $this->assertSame(['draft', 'validated'], array_map(fn(DailySales $d): string => $d->status, $days));
        $this->assertSame(200.0, $days[1]->sales);
        $this->assertSame(50.0, $days[1]->laborCost);
        $this->assertSame(20, $days[1]->customers);
        $this->assertSame(3.0, $days[1]->waste);
    }

    /** Saisie cumulée : la dernière valeur est le total ; la conversion se fait après le filtre de statut. */
    public function testCumulativeInputIsConvertedAfterTheStatusFilter(): void
    {
        $reports = [
            ['report_date' => '2026-10-01', 'status' => 'validated', 'sales_total' => 1000],
            ['report_date' => '2026-10-02', 'status' => 'draft', 'sales_total' => 2100],
            ['report_date' => '2026-10-03', 'status' => 'submitted', 'sales_total' => 3300],
        ];

        $all = $this->sales($reports, 'cumulative_input')->dailySales(4, '2026-10-01', '2026-10-31');
        $this->assertSame([1000.0, 1100.0, 1200.0], array_map(fn(DailySales $d): float => $d->sales, $all));

        $kept = $this->sales($reports, 'cumulative_input')->dailySales(4, '2026-10-01', '2026-10-31', ['validated', 'submitted']);
        $this->assertSame([1000.0, 2300.0], array_map(fn(DailySales $d): float => $d->sales, $kept));
    }

    public function testPerDayValuesAreKeptAsIs(): void
    {
        $days = $this->sales([
            ['report_date' => '2026-10-01', 'status' => 'validated', 'sales_total' => 1000],
            ['report_date' => '2026-10-02', 'status' => 'validated', 'sales_total' => 2100],
        ], 'per_day')->dailySales(4, '2026-10-01', '2026-10-31');

        $this->assertSame([1000.0, 2100.0], array_map(fn(DailySales $d): float => $d->sales, $days));
    }
}
