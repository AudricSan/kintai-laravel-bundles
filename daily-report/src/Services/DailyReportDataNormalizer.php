<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\DailyReport\Services;

/**
 * Normalise les rapports journaliers en valeurs quotidiennes (deltas) avant
 * tout calcul agrégé (rentabilité, présets de paie), quel que soit le mode
 * de saisie du store (`daily_report_settings.cumulative_mode`) :
 *
 * - `per_day` / `cumulative_system` : `sales_total`/`customer_count`/
 *   `labor_cost`/`waste_total` sont déjà des valeurs du jour — inchangés.
 * - `cumulative_input` : ces champs contiennent la valeur cumulée depuis le
 *   début de la période (saisie ainsi par le store) — sommer les rapports
 *   bruts sur une plage les compterait plusieurs fois. On reconstitue la
 *   valeur du jour par différence avec le rapport précédent (le premier
 *   rapport de la plage est pris tel quel, faute de référence antérieure).
 *
 * Ne fait AUCUNE hypothèse sur le bundle DailyReport (colonne Core sur
 * `stores`, lisible même si le bundle est désactivé).
 */
final class DailyReportDataNormalizer
{
    private const CUMULATIVE_FIELDS = ['sales_total', 'customer_count', 'labor_cost', 'waste_total'];

    public static function cumulativeModeOf(array $store): string
    {
        $decoded = json_decode((string) ($store['daily_report_settings'] ?? ''), true);
        return is_array($decoded) ? (string) ($decoded['cumulative_mode'] ?? 'per_day') : 'per_day';
    }

    /**
     * @param array[] $reportsAsc Rapports triés par `report_date` croissant.
     * @return array[] Même liste, avec les champs cumulatifs ramenés à des deltas journaliers si nécessaire.
     */
    public static function toDailyDeltas(array $reportsAsc, string $cumulativeMode): array
    {
        if ($cumulativeMode !== 'cumulative_input') {
            return $reportsAsc;
        }

        $previous = array_fill_keys(self::CUMULATIVE_FIELDS, 0.0);
        $result   = [];
        foreach ($reportsAsc as $report) {
            $delta = $report;
            foreach (self::CUMULATIVE_FIELDS as $field) {
                $raw               = (float) ($report[$field] ?? 0);
                $delta[$field]     = max(0.0, $raw - $previous[$field]);
                $previous[$field]  = $raw;
            }
            $result[] = $delta;
        }
        return $result;
    }
}
