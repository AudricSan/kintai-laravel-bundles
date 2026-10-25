<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\DailyReport\Providers;

use kintai\Bundles\Installed\DailyReport\Repositories\DailyReportRepositoryInterface;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportDataNormalizer;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Provider\DailySales;
use kintai\Contract\Provider\SalesProvider;

/**
 * Ventes journalières tirées des rapports journaliers (fournisseur du contrat 1.14) : rentabilité du magasin (Core) et
 * pré-remplissage du rapport de salaire (salary-report). Rapports supprimés exclus ; filtre de statut appliqué avant la
 * conversion en valeurs du jour, pour un magasin qui saisit en cumulé (daily_report_settings.cumulative_mode).
 */
final class DailyReportSales implements SalesProvider
{
    public function __construct(
        private readonly DailyReportRepositoryInterface $reports,
        private readonly StoreRepositoryInterface $stores,
    ) {}

    public function dailySales(int $storeId, string $from, string $to, ?array $statuses = null): array
    {
        $reports = array_values(array_filter(
            $this->reports->findByStoreAndDateRange($storeId, $from, $to),
            static fn(array $r): bool => empty($r['deleted_at']) && ($statuses === null || in_array($r['status'] ?? '', $statuses, true))
        ));
        usort($reports, static fn(array $a, array $b): int => strcmp((string) $a['report_date'], (string) $b['report_date']));

        $store = $this->stores->findById($storeId);
        $reports = DailyReportDataNormalizer::toDailyDeltas($reports, $store !== null ? DailyReportDataNormalizer::cumulativeModeOf($store) : 'per_day');

        return array_map(static fn(array $r): DailySales => new DailySales(
            (string) $r['report_date'],
            (string) ($r['status'] ?? 'draft'),
            (float) ($r['sales_total'] ?? 0),
            (float) ($r['labor_cost'] ?? 0),
            (int) ($r['customer_count'] ?? 0),
            (float) ($r['waste_total'] ?? 0),
        ), $reports);
    }
}
