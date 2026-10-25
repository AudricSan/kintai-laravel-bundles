<?php
use kintai\Contract\View\Components\Badge;

/**
 * @var array  $grouped        [ storeId => ['store' => [...], 'reports' => [...]] ]  (V1 – filtré complet)
 * @var array  $reportsV2      Rapports V2 – filtrés uniquement par année, tous stores
 * @var array  $stores         Liste des stores accessibles
 * @var int[]  $years          Années disponibles
 * @var int    $filterStoreId
 * @var string $filterStatus
 * @var int    $filterMonth
 * @var int    $filterYear
 * @var array  $authUser
 * @var string $BASE_URL
 */

$statusLabels = [
    'draft'     => __('daily_report_status_draft'),
    'submitted' => __('daily_report_status_submitted'),
    'validated' => __('daily_report_status_validated'),
];

$monthNames = [
    1 => __('January'), 2 => __('February'), 3 => __('March'), 4 => __('April'),
    5 => __('May'), 6 => __('June'), 7 => __('July'), 8 => __('August'),
    9 => __('September'), 10 => __('October'), 11 => __('November'), 12 => __('December'),
];

function drAllFormatDate(string $date): string {
    $dt = \DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}
function drAllFormatNum(mixed $n, int $dec = 0): string {
    return number_format((float) $n, $dec, ',', ' ');
}

// --- Construction V2 : regroupement par année → mois → jour ---
$byYearMonth = [];
foreach ($reportsV2 as $report) {
    if (!empty($report['is_placeholder'])) continue;
    $date  = $report['report_date'];
    $year  = (int) substr($date, 0, 4);
    $month = (int) substr($date, 5, 2);

    $store    = $report['store_obj'] ?? [];
    $currency = $store['currency'] ?? 'JPY';
    $currencyStyle = store_currency_style($store);

    if (!isset($byYearMonth[$year])) {
        $byYearMonth[$year] = [];
    }
    if (!isset($byYearMonth[$year][$month])) {
        $byYearMonth[$year][$month] = [];
    }
    if (!isset($byYearMonth[$year][$month][$date])) {
        $byYearMonth[$year][$month][$date] = [
            'date'     => $date,
            'stores'   => [],
            'totals'   => ['sales_total' => 0.0, 'customer_count' => 0, 'labor_cost' => 0.0, 'waste_total' => 0.0],
            'currency' => $currency,
            'currency_style' => $currencyStyle,
        ];
    }

    $byYearMonth[$year][$month][$date]['stores'][] = ['store' => $store, 'report' => $report];
    $byYearMonth[$year][$month][$date]['totals']['sales_total']    += (float) ($report['sales_total']    ?? 0);
    $byYearMonth[$year][$month][$date]['totals']['customer_count'] += (int)   ($report['customer_count'] ?? 0);
    $byYearMonth[$year][$month][$date]['totals']['labor_cost']     += (float) ($report['labor_cost']     ?? 0);
    $byYearMonth[$year][$month][$date]['totals']['waste_total']    += (float) ($report['waste_total']    ?? 0);
}
krsort($byYearMonth);
foreach ($byYearMonth as &$months) {
    krsort($months);
    foreach ($months as &$days) {
        krsort($days);
    }
}
unset($months, $days);
?>
<?php if ($dailyReportCss = bundle_asset('daily-report', 'css/daily-report.css')): ?>
<link rel="stylesheet" href="<?= $dailyReportCss ?>">
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('daily_report_title') ?></h2>
    <div class="btn-group btn-group--switcher" id="dr-view-switcher">
        <span class="btn-group__thumb" aria-hidden="true"></span>
        <button type="button" class="btn btn--ghost btn--sm" id="dr-btn-v1" data-on-click="drSwitchView" data-args='["v1"]'>
            ☰ <?= __('daily_report_view_by_store') ?>
        </button>
        <button type="button" class="btn btn--ghost btn--sm" id="dr-btn-v2" data-on-click="drSwitchView" data-args='["v2"]'>
            📅 <?= __('daily_report_view_by_day') ?>
        </button>
    </div>
</div>

<!-- ===== FILTRE V1 (complet) ===== -->
<div id="dr-filter-v1" class="card card--filters mb-sm">
    <form method="GET" action="" class="filter-bar">
        <div class="shifts-filters__row">
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="dr-store"><?= __('store') ?></label>
                <select id="dr-store" name="store_id" class="form-control form-control-sm" data-submit-on-change>
                    <option value="0"><?= __('daily_report_filter_all_stores') ?></option>
                    <?php foreach ($stores as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $filterStoreId === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="dr-status"><?= __('status') ?></label>
                <select id="dr-status" name="status" class="form-control form-control-sm" data-submit-on-change>
                    <option value=""><?= __('all') ?></option>
                    <?php foreach (['draft', 'submitted', 'validated'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusLabels[$s]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="dr-year"><?= __('daily_report_filter_year') ?></label>
                <select id="dr-year" name="year" class="form-control form-control-sm" data-submit-on-change>
                    <option value="0"><?= __('all') ?></option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $filterYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="dr-month"><?= __('daily_report_filter_month') ?></label>
                <select id="dr-month" name="month" class="form-control form-control-sm" data-submit-on-change>
                    <option value="0"><?= __('all') ?></option>
                    <?php foreach ($monthNames as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $filterMonth === $num ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="shifts-filters__actions">
                <a href="?" class="btn btn--ghost btn--sm"><?= __('reset') ?></a>
            </div>
        </div>
    </form>
</div>

<!-- ===== FILTRE V2 (année seulement) ===== -->
<div id="dr-filter-v2" class="card card--filters mb-sm">
    <form method="GET" action="" class="filter-bar">
        <div class="shifts-filters__row">
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="dr-year-v2"><?= __('daily_report_filter_year') ?></label>
                <select id="dr-year-v2" name="year" class="form-control form-control-sm" data-submit-on-change>
                    <option value="0"><?= __('all') ?></option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $filterYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="shifts-filters__actions">
                <a href="?" class="btn btn--ghost btn--sm"><?= __('reset') ?></a>
            </div>
        </div>
    </form>
</div>

<!-- =========================================================
     VUE V1 : par magasin
     ========================================================= -->
<div id="dr-view-v1">
<?php if (empty($grouped)): ?>
    <div class="card">
        <div class="empty-state"><?= __('daily_report_empty_global') ?></div>
    </div>
<?php else: ?>
    <?php foreach ($grouped as $sid => $group): ?>
        <?php
        $store    = $group['store'];
        $storeSettings = json_decode($store['daily_report_settings'] ?? '{}', true);
        $cumulativeMode = $storeSettings['cumulative_mode'] ?? 'per_day';
        $reports  = array_slice($group['reports'], 0, 5);
        $currency = currency_symbol($store['currency'] ?? 'JPY', store_currency_style($store));

        // Cumuls pour ce store (mode système)
        $cumulativeTotals = [];
        if ($cumulativeMode === 'cumulative_system') {
            $allReports = $group['reports'];
            usort($allReports, fn($a, $b) => strcmp($a['report_date'], $b['report_date']));
            $running = ['sales_total' => 0.0, 'customer_count' => 0, 'labor_cost' => 0.0, 'waste_total' => 0.0];
            foreach ($allReports as $r) {
                $id = (int) ($r['id'] ?? 0);
                if ($id > 0) {
                    $running['sales_total']    += (float) ($r['sales_total']    ?? 0);
                    $running['customer_count'] += (int)   ($r['customer_count'] ?? 0);
                    $running['labor_cost']     += (float) ($r['labor_cost']     ?? 0);
                    $running['waste_total']    += (float) ($r['waste_total']    ?? 0);
                    $cumulativeTotals[$id] = $running;
                }
            }
            unset($allReports, $running);
        }
        ?>
        <div class="card mb-md">
            <div class="card-header">
                <h3>
                    <?= htmlspecialchars($store['name']) ?>
                    <?php if ($cumulativeMode === 'cumulative_system'): ?><span class="badge badge--warning badge--sm"><?= __('daily_report_badge_cumulative_system') ?></span><?php endif; ?>
                    <?php if ($cumulativeMode === 'cumulative_input'): ?><span class="badge badge--info badge--sm"><?= __('daily_report_badge_cumulative_input') ?></span><?php endif; ?>
                    <span class="badge badge--secondary badge--sm ml-xs"><?= min(5, count($group['reports'])) ?>/<?= count($group['reports']) ?></span>
                </h3>
                <div class="btn-group">
                    <a href="<?= $BASE_URL ?>/admin/stores/<?= $sid ?>/daily-reports/settings"
                       class="btn btn--ghost btn--xs">⚙ <?= __('daily_report_settings') ?></a>
                    <a href="<?= $BASE_URL ?>/admin/stores/<?= $sid ?>/daily-reports/create"
                       class="btn btn--primary btn--xs">+ <?= __('daily_report_new') ?></a>
                    <a href="<?= $BASE_URL ?>/admin/stores/<?= $sid ?>/daily-reports"
                       class="btn btn--ghost btn--xs"><?= __('view_all') ?></a>
                </div>
            </div>
            <?php if (empty($reports)): ?>
                <div class="empty-state"><?= __('daily_report_empty') ?></div>
            <?php else: ?>
            <div class="table-wrap">
                <table class="data-table" data-mob-stack>
                    <thead>
                        <tr>
                            <th><?= __('date') ?></th>
                            <th><?= __('daily_report_author') ?></th>
                            <th class="text-right"><?= __('daily_report_sales') ?> (<?= $currency ?>)</th>
                            <th class="text-right"><?= __('daily_report_customers') ?></th>
                            <th class="text-right"><?= __('daily_report_labor') ?> (<?= $currency ?>)</th>
                            <th class="text-right"><?= __('daily_report_waste') ?> (<?= $currency ?>)</th>
                            <th><?= __('status') ?></th>
                            <th><?= __('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <?php
                            $rid       = (int) $report['id'];
                            $st        = $report['status'] ?? 'draft';
                            $badgeHtml = Badge::make($statusLabels[$st] ?? $st)->status($st)->render();
                            $author    = $report['author'] ?? [];
                            $authorName = trim(($author['last_name'] ?? '') . ' ' . ($author['first_name'] ?? ''))
                                ?: ($author['email'] ?? '—');
                            ?>
                            <tr data-href="<?= $BASE_URL ?>/admin/stores/<?= $sid ?>/daily-reports/<?= $rid ?>" class="tr--clickable">
                                <td data-label="<?= htmlspecialchars(__('date')) ?>"><?= drAllFormatDate($report['report_date']) ?></td>
                                <td data-label="<?= htmlspecialchars(__('daily_report_author')) ?>"><?= htmlspecialchars($authorName) ?></td>
                                <td data-label="<?= htmlspecialchars(__('daily_report_sales') . ' (' . $currency . ')') ?>" class="text-right"><?= drAllFormatNum($cumulativeMode === 'cumulative_system' ? ($cumulativeTotals[$rid]['sales_total'] ?? $report['sales_total']) : $report['sales_total']) ?></td>
                                <td data-label="<?= htmlspecialchars(__('daily_report_customers')) ?>" class="text-right"><?= drAllFormatNum($cumulativeMode === 'cumulative_system' ? ($cumulativeTotals[$rid]['customer_count'] ?? $report['customer_count']) : $report['customer_count']) ?></td>
                                <td data-label="<?= htmlspecialchars(__('daily_report_labor') . ' (' . $currency . ')') ?>" class="text-right"><?= drAllFormatNum($cumulativeMode === 'cumulative_system' ? ($cumulativeTotals[$rid]['labor_cost'] ?? $report['labor_cost']) : $report['labor_cost']) ?></td>
                                <td data-label="<?= htmlspecialchars(__('daily_report_waste') . ' (' . $currency . ')') ?>" class="text-right"><?= drAllFormatNum($cumulativeMode === 'cumulative_system' ? ($cumulativeTotals[$rid]['waste_total'] ?? $report['waste_total']) : $report['waste_total']) ?></td>
                                <td data-label="<?= htmlspecialchars(__('status')) ?>">
                                    <?= $badgeHtml ?>
                                    <?php if (!empty($report['mail_sent_at'])): ?>
                                        <span class="badge badge--active" title="<?= __('daily_report_mail_sent_at') ?> <?= htmlspecialchars($report['mail_sent_at']) ?>">✉</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($st === 'validated'): ?>
                                        <a href="<?= $BASE_URL ?>/admin/stores/<?= $sid ?>/daily-reports/<?= $rid ?>/pdf"
                                           class="btn btn--ghost btn--xs" target="_blank" data-stop-propagation>PDF</a>
                                    <?php endif; ?>
                                    <?php if (!empty($authUser['is_admin'])): ?>
                                        <form method="POST" action="<?= $BASE_URL ?>/admin/stores/<?= $sid ?>/daily-reports/<?= $rid ?>/delete" class="form-inline" data-confirm="<?= htmlspecialchars(__('daily_report_confirm_delete'), ENT_QUOTES) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn--danger btn--xs" data-stop-propagation><?= __('delete') ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</div><!-- /#dr-view-v1 -->

<!-- =========================================================
     VUE V2 : par année → mois → jour
     ========================================================= -->
<div id="dr-view-v2">
<?php if (empty($byYearMonth)): ?>
    <div class="card">
        <div class="empty-state"><?= __('daily_report_empty_global') ?></div>
    </div>
<?php else: ?>
    <?php foreach ($byYearMonth as $year => $months): ?>
        <!-- Bloc Année -->
        <div class="card mb-md">
            <div class="card-header">
                <h3><?= $year ?></h3>
                <?php
                // Totaux annuels
                $yearTotals = ['sales_total' => 0.0, 'customer_count' => 0, 'labor_cost' => 0.0, 'waste_total' => 0.0];
                foreach ($months as $days) {
                    foreach ($days as $dayData) {
                        $yearTotals['sales_total']    += $dayData['totals']['sales_total'];
                        $yearTotals['customer_count'] += $dayData['totals']['customer_count'];
                        $yearTotals['labor_cost']     += $dayData['totals']['labor_cost'];
                        $yearTotals['waste_total']    += $dayData['totals']['waste_total'];
                    }
                }
                $firstDayData  = reset($months)[array_key_first(reset($months))];
                $firstCurrency = currency_symbol($firstDayData['currency'] ?? 'JPY', $firstDayData['currency_style'] ?? 'kanji');
                ?>
                <div class="dr-v2-year-totals text-muted">
                    <?= __('daily_report_sales') ?> <strong><?= drAllFormatNum($yearTotals['sales_total']) ?></strong>
                    &nbsp;|&nbsp;
                    <?= __('daily_report_customers') ?> <strong><?= drAllFormatNum($yearTotals['customer_count']) ?></strong>
                </div>
            </div>

            <?php foreach ($months as $month => $days): ?>
                <!-- Bloc Mois -->
                <?php
                $monthTotals = ['sales_total' => 0.0, 'customer_count' => 0, 'labor_cost' => 0.0, 'waste_total' => 0.0];
                foreach ($days as $dayData) {
                    $monthTotals['sales_total']    += $dayData['totals']['sales_total'];
                    $monthTotals['customer_count'] += $dayData['totals']['customer_count'];
                    $monthTotals['labor_cost']     += $dayData['totals']['labor_cost'];
                    $monthTotals['waste_total']    += $dayData['totals']['waste_total'];
                }
                $monthId = 'dr-month-' . $year . '-' . $month;
                ?>
                <div class="dr-v2-month">
                    <button type="button" class="dr-v2-month-toggle" data-on-click="drV2ToggleMonth" data-args="<?= htmlspecialchars(json_encode([$monthId]), ENT_QUOTES) ?>">
                        <span class="dr-v2-month-name"><?= $monthNames[$month] ?? $month ?></span>
                        <span class="dr-v2-month-stats text-muted">
                            <?= __('daily_report_sales') ?> <strong><?= drAllFormatNum($monthTotals['sales_total']) ?></strong>
                            &nbsp;·&nbsp;
                            <?= __('daily_report_customers') ?> <strong><?= drAllFormatNum($monthTotals['customer_count']) ?></strong>
                            &nbsp;·&nbsp;
                            <?= count($days) ?> <?= __('daily_report_days') ?>
                        </span>
                        <span class="dr-v2-month-arrow" id="<?= $monthId ?>-arrow">▼</span>
                    </button>

                    <div class="dr-v2-month-body" id="<?= $monthId ?>">
                        <div class="table-wrap">
                            <table class="data-table" data-mob-stack>
                                <thead>
                                    <tr>
                                        <th><?= __('date') ?></th>
                                        <th class="text-right"><?= __('daily_report_v2_stores_count') ?></th>
                                        <th class="text-right"><?= __('daily_report_sales') ?></th>
                                        <th class="text-right"><?= __('daily_report_customers') ?></th>
                                        <th class="text-right"><?= __('daily_report_labor') ?></th>
                                        <th class="text-right"><?= __('daily_report_waste') ?></th>
                                        <th><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($days as $date => $dayData): ?>
                                        <?php
                                        $totals     = $dayData['totals'];
                                        $storeCount = count($dayData['stores']);
                                        $sym        = currency_symbol($dayData['currency'], $dayData['currency_style'] ?? 'kanji');

                                        // Données modal (JSON)
                                        $modalStores = [];
                                        foreach ($dayData['stores'] as $entry) {
                                            $r = $entry['report'];
                                            $s = $entry['store'];
                                            $author = $r['author'] ?? [];
                                            $authorName = trim(($author['last_name'] ?? '') . ' ' . ($author['first_name'] ?? ''))
                                                ?: ($author['email'] ?? '—');
                                            $st = $r['status'] ?? 'draft';
                                            $modalStores[] = [
                                                'store_name'     => $s['name'] ?? '—',
                                                'store_id'       => (int) ($s['id'] ?? 0),
                                                'report_id'      => (int) ($r['id'] ?? 0),
                                                'author'         => $authorName,
                                                'sales_total'    => (float) ($r['sales_total']    ?? 0),
                                                'customer_count' => (int)   ($r['customer_count'] ?? 0),
                                                'labor_cost'     => (float) ($r['labor_cost']     ?? 0),
                                                'waste_total'    => (float) ($r['waste_total']    ?? 0),
                                                'badge_html'     => Badge::make($statusLabels[$st] ?? $st)->status($st)->render(),
                                                'currency_sym'   => currency_symbol($s['currency'] ?? 'JPY', store_currency_style($s)),
                                                'notes'          => $r['notes'] ?? '',
                                            ];
                                        }
                                        $modalJson = htmlspecialchars(json_encode([
                                            'date'     => drAllFormatDate($date),
                                            'totals'   => $totals,
                                            'currency' => $sym,
                                            'stores'   => $modalStores,
                                            'base_url' => $BASE_URL,
                                        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                                        ?>
                                        <tr>
                                            <td data-label="<?= htmlspecialchars(__('date')) ?>">
                                                <strong><?= drAllFormatDate($date) ?></strong>
                                            </td>
                                            <td data-label="<?= htmlspecialchars(__('daily_report_v2_stores_count')) ?>" class="text-right">
                                                <?= $storeCount ?>
                                            </td>
                                            <td data-label="<?= htmlspecialchars(__('daily_report_sales')) ?>" class="text-right">
                                                <strong><?= drAllFormatNum($totals['sales_total']) ?></strong>
                                                <small class="text-muted"><?= $sym ?></small>
                                            </td>
                                            <td data-label="<?= htmlspecialchars(__('daily_report_customers')) ?>" class="text-right">
                                                <?= drAllFormatNum($totals['customer_count']) ?>
                                            </td>
                                            <td data-label="<?= htmlspecialchars(__('daily_report_labor')) ?>" class="text-right">
                                                <?= drAllFormatNum($totals['labor_cost']) ?>
                                                <small class="text-muted"><?= $sym ?></small>
                                            </td>
                                            <td data-label="<?= htmlspecialchars(__('daily_report_waste')) ?>" class="text-right">
                                                <?= drAllFormatNum($totals['waste_total']) ?>
                                                <small class="text-muted"><?= $sym ?></small>
                                            </td>
                                            <td>
                                                <button type="button"
                                                        class="btn btn--ghost btn--xs"
                                                        data-on-click="drV2OpenDetail" data-args='["@this"]'
                                                        data-day="<?= $modalJson ?>">
                                                    ▶ <?= __('see_details') ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <!-- Total du mois -->
                                <tfoot>
                                    <tr>
                                        <td colspan="2" class="text-muted"><strong><?= __('total') ?></strong></td>
                                        <td class="text-right"><strong><?= drAllFormatNum($monthTotals['sales_total']) ?></strong></td>
                                        <td class="text-right"><strong><?= drAllFormatNum($monthTotals['customer_count']) ?></strong></td>
                                        <td class="text-right"><strong><?= drAllFormatNum($monthTotals['labor_cost']) ?></strong></td>
                                        <td class="text-right"><strong><?= drAllFormatNum($monthTotals['waste_total']) ?></strong></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div><!-- /.dr-v2-month-body -->
                </div><!-- /.dr-v2-month -->
            <?php endforeach; ?>
        </div><!-- /.card année -->
    <?php endforeach; ?>
<?php endif; ?>
</div><!-- /#dr-view-v2 -->

<!-- =========================================================
     Modal détail V2
     ========================================================= -->
<div id="dr-v2-overlay" class="dr-v2-overlay" data-on-click="drV2CloseDetail">
    <div class="dr-v2-modal" data-stop-propagation>
        <div class="dr-v2-modal-header">
            <strong id="dr-v2-modal-title"></strong>
            <button type="button" data-on-click="drV2CloseDetail" class="sb-modal-close">×</button>
        </div>
        <div class="dr-v2-modal-body">
            <div class="table-wrap">
                <table class="data-table" id="dr-v2-detail-table">
                    <thead>
                        <tr>
                            <th><?= __('store') ?></th>
                            <th><?= __('daily_report_author') ?></th>
                            <th class="text-right"><?= __('daily_report_sales') ?></th>
                            <th class="text-right"><?= __('daily_report_customers') ?></th>
                            <th class="text-right"><?= __('daily_report_labor') ?></th>
                            <th class="text-right"><?= __('daily_report_waste') ?></th>
                            <th><?= __('status') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="dr-v2-detail-body"></tbody>
                    <tfoot id="dr-v2-detail-foot"></tfoot>
                </table>
            </div>
        </div>
        <div class="dr-v2-modal-footer">
            <button type="button" data-on-click="drV2CloseDetail" class="btn btn--ghost btn--sm"><?= __('close') ?></button>
        </div>
    </div>
</div>

<script nonce="<?= function_exists('csp_nonce') ? csp_nonce() : '' ?>">
(function () {
    var LS_KEY = 'kintai_dr_view';

    // ---- Toggle V1 / V2 ----
    function applyView(v) {
        var show = function (id) { var el = document.getElementById(id); if (el) el.style.display = ''; };
        var hide = function (id) { var el = document.getElementById(id); if (el) el.style.display = 'none'; };
        var active   = function (id) { var el = document.getElementById(id); if (el) el.classList.add('btn--active'); };
        var inactive = function (id) { var el = document.getElementById(id); if (el) el.classList.remove('btn--active'); };

        var thumb = document.querySelector('#dr-view-switcher .btn-group__thumb');

        if (v === 'v2') {
            hide('dr-view-v1');  show('dr-view-v2');
            hide('dr-filter-v1'); show('dr-filter-v2');
            inactive('dr-btn-v1'); active('dr-btn-v2');
            if (thumb) thumb.style.setProperty('--pos', 1);
        } else {
            show('dr-view-v1');  hide('dr-view-v2');
            show('dr-filter-v1'); hide('dr-filter-v2');
            active('dr-btn-v1'); inactive('dr-btn-v2');
            if (thumb) thumb.style.setProperty('--pos', 0);
        }
    }

    window.drSwitchView = function (v) {
        try { localStorage.setItem(LS_KEY, v); } catch (e) {}
        applyView(v);
    };

    // Vue par défaut : par jour (v2). Le choix fait avec le bouton est retenu dans le navigateur. Lecture protégée :
    // localStorage peut lever une erreur (navigation privée, stockage bloqué), ce qui laissait les deux vues affichées.
    var savedView = null;
    try { savedView = localStorage.getItem(LS_KEY); } catch (e) {}
    applyView(savedView === 'v1' || savedView === 'v2' ? savedView : 'v2');

    // ---- Collapse mois ----
    window.drV2ToggleMonth = function (id) {
        var body  = document.getElementById(id);
        var arrow = document.getElementById(id + '-arrow');
        if (!body) return;
        var collapsed = body.style.display === 'none';
        body.style.display  = collapsed ? '' : 'none';
        if (arrow) arrow.textContent = collapsed ? '▼' : '▶';
        try { localStorage.setItem('kintai_dr_month_' + id, collapsed ? 'open' : 'closed'); } catch(e) {}
    };

    // Restaurer l'état collapse des mois depuis localStorage
    document.querySelectorAll('.dr-v2-month-body').forEach(function (body) {
        var id    = body.id;
        var arrow = document.getElementById(id + '-arrow');
        var state = null;
        try { state = localStorage.getItem('kintai_dr_month_' + id); } catch (e) {}
        if (state === 'closed') {
            body.style.display = 'none';
            if (arrow) arrow.textContent = '▶';
        }
    });

    // ---- Modal détail ----
    function fmt(n) { return Number(n).toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }); }
    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    window.drV2OpenDetail = function (btn) {
        var data   = JSON.parse(btn.getAttribute('data-day'));
        var title  = document.getElementById('dr-v2-modal-title');
        var tbody  = document.getElementById('dr-v2-detail-body');
        var tfoot  = document.getElementById('dr-v2-detail-foot');
        var overlay = document.getElementById('dr-v2-overlay');

        title.textContent = data.date;
        tbody.innerHTML   = '';
        tfoot.innerHTML   = '';

        data.stores.forEach(function (s) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td><strong>' + esc(s.store_name) + '</strong></td>' +
                '<td>' + esc(s.author) + '</td>' +
                '<td class="text-right">' + fmt(s.sales_total) + ' <small>' + esc(s.currency_sym) + '</small></td>' +
                '<td class="text-right">' + fmt(s.customer_count) + '</td>' +
                '<td class="text-right">' + fmt(s.labor_cost) + ' <small>' + esc(s.currency_sym) + '</small></td>' +
                '<td class="text-right">' + fmt(s.waste_total) + ' <small>' + esc(s.currency_sym) + '</small></td>' +
                '<td>' + s.badge_html + '</td>' +
                '<td><a href="' + esc(data.base_url) + '/admin/stores/' + s.store_id + '/daily-reports/' + s.report_id + '" class="btn btn--ghost btn--xs"><?= __('view') ?></a></td>';
            tbody.appendChild(tr);
        });

        var tf = document.createElement('tr');
        tf.innerHTML =
            '<td colspan="2"><strong><?= __('total') ?></strong></td>' +
            '<td class="text-right"><strong>' + fmt(data.totals.sales_total) + ' <small>' + esc(data.currency) + '</small></strong></td>' +
            '<td class="text-right"><strong>' + fmt(data.totals.customer_count) + '</strong></td>' +
            '<td class="text-right"><strong>' + fmt(data.totals.labor_cost) + ' <small>' + esc(data.currency) + '</small></strong></td>' +
            '<td class="text-right"><strong>' + fmt(data.totals.waste_total) + ' <small>' + esc(data.currency) + '</small></strong></td>' +
            '<td colspan="2"></td>';
        tfoot.appendChild(tf);

        overlay.style.display = 'flex';
    };

    window.drV2CloseDetail = function () {
        document.getElementById('dr-v2-overlay').style.display = 'none';
    };
}());
</script>
