<?php
use kintai\Contract\View\Components\Badge;

/**
 * @var array  $store
 * @var array  $reports
 * @var string $status
 * @var string $fromDate
 * @var string $toDate
 * @var array  $settings
 * @var array  $authUser
 * @var array|null $membership
 * @var \kintai\Bundles\Installed\DailyReport\Services\DailyReportPermissionService $permissions
 * @var string $BASE_URL
 */
$storeId  = (int) $store['id'];
$isAdmin  = !empty($authUser['is_admin']);
$canCreate = $permissions->canCreate($authUser, $store, $membership);
$currency  = currency_symbol($store['currency'] ?? 'JPY', store_currency_style($store));

$statusLabels = [
    'draft'     => __('daily_report_status_draft'),
    'submitted' => __('daily_report_status_submitted'),
    'validated' => __('daily_report_status_validated'),
];

$cumulativeMode = $settings['cumulative_mode'] ?? 'per_day';

// Pré-calcul des cumuls si le mode cumulatif système est activé
$cumulativeTotals = [];
if ($cumulativeMode === 'cumulative_system') {
    $realReports = array_filter($reports, fn($r) => empty($r['is_placeholder']));
    usort($realReports, fn($a, $b) => strcmp($a['report_date'], $b['report_date']));
    $running = ['sales_total' => 0.0, 'customer_count' => 0, 'labor_cost' => 0.0, 'waste_total' => 0.0];
    foreach ($realReports as $r) {
        $id = (int) ($r['id'] ?? 0);
        if ($id > 0) {
            $running['sales_total']    += (float) ($r['sales_total']    ?? 0);
            $running['customer_count'] += (int)   ($r['customer_count'] ?? 0);
            $running['labor_cost']     += (float) ($r['labor_cost']     ?? 0);
            $running['waste_total']    += (float) ($r['waste_total']    ?? 0);
            $cumulativeTotals[$id] = $running;
        }
    }
    unset($realReports, $running);
}

function drFormatDate(string $date): string {
    $dt = \DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}
function drFormatNum(mixed $n, int $dec = 0): string {
    return number_format((float) $n, $dec, ',', ' ');
}
?>

<?= \kintai\Contract\View\Components\Flash::fromQuery('success', ['deleted' => __('daily_report_deleted')])->render() ?>

<?php
$_currentTime    = date('H:i');
$_reminderTime   = $settings['reminder_time']  ?? '15:00';
$_noReportTime   = $settings['no_report_time'] ?? '18:00';
$_todayStr       = date('Y-m-d');
$_hasTodayReport = !empty(array_filter($reports, fn($r) => empty($r['is_placeholder']) && $r['report_date'] === $_todayStr));
if ($canCreate && ($settings['enabled'] ?? true) && $_currentTime >= $_reminderTime && $_currentTime < $_noReportTime && !$_hasTodayReport):
?>
    <div class="alert alert--warning alert--flex">
        <span>⏰ <?= __('daily_report_reminder_alert') ?></span>
        <a href="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports/create"
           class="btn btn--primary btn--sm"><?= __('daily_report_new') ?></a>
    </div>
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title">
        <?= __('daily_report_title') ?> — <?= htmlspecialchars($store['name']) ?>
        <?php if ($cumulativeMode === 'cumulative_system'): ?><span class="badge badge--warning badge--sm"><?= __('daily_report_badge_cumulative_system') ?></span><?php endif; ?>
        <?php if ($cumulativeMode === 'cumulative_input'): ?><span class="badge badge--info badge--sm"><?= __('daily_report_badge_cumulative_input') ?></span><?php endif; ?>
    </h2>
    <div class="page-header__actions">
        <?php if ($permissions->canManageSettings($authUser, $store)): ?>
            <a href="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports/settings"
               class="btn btn--ghost">⚙ <?= __('daily_report_settings') ?></a>
        <?php endif; ?>
        <?php if ($canCreate && ($settings['enabled'] ?? true)): ?>
            <a href="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports/create"
               class="btn btn--primary">+ <?= __('daily_report_new') ?></a>
        <?php endif; ?>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-sm">
    <div class="card-body">
        <form method="GET" action="" class="form-flex">
            <div class="form-group">
                <label class="form-label"><?= __('status') ?></label>
                <select name="status" class="form-control form-control-sm" data-submit-on-change>
                    <option value=""><?= __('all') ?></option>
                    <?php foreach (['draft','submitted','validated'] as $s): ?>
                        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusLabels[$s]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('from') ?></label>
                <input type="date" name="from" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($fromDate) ?>"
                       data-submit-on-change>
            </div>
            <div class="form-group">
                <label class="form-label"><?= __('to') ?></label>
                <input type="date" name="to" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($toDate) ?>"
                       data-submit-on-change>
            </div>
            <div class="form-group">
                <a href="?" class="btn btn--ghost btn--sm"><?= __('reset') ?></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
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
                        <th><?= __('daily_report_pdf') ?></th>
                        <th><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <?php if (!empty($report['is_placeholder'])): ?>
                        <tr class="text-dim">
                            <td data-label="<?= htmlspecialchars(__('date')) ?>"><?= drFormatDate($report['report_date']) ?></td>
                            <td colspan="7" class="text-muted text-italic"><?= __('daily_report_no_report') ?></td>
                        </tr>
                        <?php else: ?>
                        <?php
                        $rid     = (int) $report['id'];
                        $st      = $report['status'] ?? 'draft';
                        $badgeHtml = Badge::make($statusLabels[$st] ?? $st)->status($st)->render();
                        $author  = $report['author'] ?? [];
                        $authorName = trim(($author['last_name'] ?? '') . ' ' . ($author['first_name'] ?? ''))
                            ?: ($author['email'] ?? '—');
                        $drSalesLabel  = htmlspecialchars(__('daily_report_sales')    . ' (' . $currency . ')');
                        $drLaborLabel  = htmlspecialchars(__('daily_report_labor')    . ' (' . $currency . ')');
                        $drWasteLabel  = htmlspecialchars(__('daily_report_waste')    . ' (' . $currency . ')');
                        ?>
                        <tr data-href="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports/<?= $rid ?>" class="tr--clickable">
                            <td data-label="<?= htmlspecialchars(__('date')) ?>"><?= drFormatDate($report['report_date']) ?></td>
                            <td data-label="<?= htmlspecialchars(__('daily_report_author')) ?>"><?= htmlspecialchars($authorName) ?></td>
                            <td data-label="<?= $drSalesLabel ?>" class="text-right"><?= drFormatNum($cumulativeMode === 'cumulative_system' ? ($cumulativeTotals[$rid]['sales_total'] ?? $report['sales_total']) : $report['sales_total']) ?></td>
                            <td data-label="<?= htmlspecialchars(__('daily_report_customers')) ?>" class="text-right"><?= drFormatNum($cumulativeMode === 'cumulative_system' ? ($cumulativeTotals[$rid]['customer_count'] ?? $report['customer_count']) : $report['customer_count']) ?></td>
                            <td data-label="<?= $drLaborLabel ?>" class="text-right"><?= drFormatNum($cumulativeMode === 'cumulative_system' ? ($cumulativeTotals[$rid]['labor_cost'] ?? $report['labor_cost']) : $report['labor_cost']) ?></td>
                            <td data-label="<?= $drWasteLabel ?>" class="text-right"><?= drFormatNum($cumulativeMode === 'cumulative_system' ? ($cumulativeTotals[$rid]['waste_total'] ?? $report['waste_total']) : $report['waste_total']) ?></td>
                            <td data-label="<?= htmlspecialchars(__('status')) ?>">
                                <?= $badgeHtml ?>
                            </td>
                            <td data-label="PDF">
                                <?php if (($report['status'] ?? '') === 'validated'): ?>
                                    <a href="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports/<?= $rid ?>/pdf"
                                       class="btn btn--ghost btn--xs" target="_blank" data-stop-propagation>PDF</a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isAdmin): ?>
                                    <form method="POST" action="<?= $BASE_URL ?>/admin/stores/<?= $storeId ?>/daily-reports/<?= $rid ?>/delete" class="form-inline" data-confirm="<?= htmlspecialchars(__('daily_report_confirm_delete'), ENT_QUOTES) ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn--danger btn--xs" data-stop-propagation><?= __('delete') ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
