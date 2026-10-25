<?php

declare(strict_types=1);

use kintai\Contract\View\Components\Badge;
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Card;

/**
 * @var array  $store
 * @var array  $report
 * @var string $BASE_URL
 */

$storeId = (int) $store['id'];
$reportId = (int) $report['id'];
$currency = $store['currency'] ?? 'JPY';
$currencyStyle = store_currency_style($store);
$base = $BASE_URL . '/admin/stores/' . $storeId . '/reports/salary/' . $reportId;

$fmt = fn(mixed $v, string $def = '—') => $v !== null && $v !== '' ? htmlspecialchars((string) $v) : $def;
$cur = fn(mixed $v) => $v !== null && $v !== '' ? format_currency((float) $v, $currency, $currencyStyle) : '—';
$num = fn(mixed $v) => $v !== null && $v !== '' ? htmlspecialchars((string) $v) : '—';
// Un rapport lié à un employé n'a pas de sens pour ces deux champs, propres au magasin entier.
$isEmployeeScoped = !empty($report['user_id']);
?>
<div class="page-header">
    <h2 class="page-header__title">
        <?= __('salary_report_sr_title') ?> — <?= htmlspecialchars($report['target_month'] ?? '') ?>
        <span class="page-count"><?= htmlspecialchars($store['name'] ?? '') ?></span>
    </h2>
    <div class="page-header__actions">
        <?= Button::make('← ' . __('back'))->ghost()->sm()->link(back_url($BASE_URL . '/admin/stores/' . $storeId . '/reports/salary'))->render() ?>
        <?= Button::make(__('edit'))->primary()->sm()->link($base . '/edit')->render() ?>
        <?= Button::make(__('salary_report_sr_pdf'))->ghost()->sm()->link($base . '/pdf')->attrs(['target' => '_blank'])->render() ?>
        <form method="POST" action="<?= htmlspecialchars($base . '/delete') ?>" class="form-inline" data-confirm="<?= htmlspecialchars(__('salary_report_sr_confirm_delete'), ENT_QUOTES) ?>">
            <?= csrf_field() ?>
            <?= Button::make(__('delete'))->danger()->sm()->submit()->render() ?>
        </form>
    </div>
</div>

<?php
$sections = [];

// Basic Info
ob_start();
?>
<table class="detail-table">
    <tr><th><?= __('salary_report_sr_target_month') ?></th><td><?= $fmt($report['target_month'] ?? '') ?></td></tr>
    <tr><th><?= __('store') ?></th><td><?= $fmt($report['store_name'] ?? ($store['name'] ?? '')) ?></td></tr>
    <?php if (!empty($report['user_id'])): ?>
    <tr><th><?= __('employee_name') ?></th><td><?= $fmt($report['employee_name'] ?? '') ?></td></tr>
    <?php endif; ?>
    <tr><th><?= __('salary_report_sr_person_in_charge') ?></th><td><?= $fmt($report['person_in_charge'] ?? '') ?></td></tr>
</table>
<?php $sections[__('salary_report_sr_section_basic')] = ob_get_clean();

// Financial Summary
ob_start();
?>
<table class="detail-table">
    <?php if (!$isEmployeeScoped): ?>
    <tr><th><?= __('salary_report_sr_total_payment') ?></th><td class="td-mono"><?= $cur($report['total_payment'] ?? null) ?></td></tr>
    <?php endif; ?>
    <tr><th><?= __('salary_report_sr_total_deductions') ?></th><td class="td-mono"><?= $cur($report['total_deductions'] ?? null) ?></td></tr>
    <tr><th><?= __('salary_report_sr_income_tax_base') ?></th><td class="td-mono"><?= $cur($report['income_tax_base'] ?? null) ?></td></tr>
    <tr><th><?= __('salary_report_sr_withholding_tax') ?></th><td class="td-mono"><?= $cur($report['withholding_tax'] ?? null) ?></td></tr>
    <tr><th><?= __('salary_report_sr_residence_tax') ?></th><td class="td-mono"><?= $cur($report['residence_tax'] ?? null) ?></td></tr>
    <tr><th><?= __('salary_report_sr_other_deductions') ?></th><td class="td-mono"><?= $cur($report['other_deductions'] ?? null) ?></td></tr>
    <tr><th><?= __('salary_report_sr_net_payment') ?></th><td class="td-mono td-highlight"><?= $cur($report['net_payment'] ?? null) ?></td></tr>
    <tr><th><?= __('salary_report_sr_hand_delivered_salary') ?></th><td class="td-mono"><?= $cur($report['hand_delivered_salary'] ?? null) ?></td></tr>
    <tr><th><?= __('salary_report_sr_bank_transfer_salary') ?></th><td class="td-mono"><?= $cur($report['bank_transfer_salary'] ?? null) ?></td></tr>
    <?php if (!$isEmployeeScoped): ?>
    <tr><th><?= __('salary_report_sr_active_employees') ?></th><td class="td-mono"><?= $num($report['active_employees'] ?? null) ?></td></tr>
    <?php endif; ?>
</table>
<?php $sections[__('salary_report_sr_section_financial')] = ob_get_clean();

// Staff Summary
ob_start();
?>
<table class="detail-table">
    <tr><th><?= __('salary_report_sr_staff_man_hours') ?></th><td class="td-mono"><?= $num($report['staff_man_hours'] ?? null) ?> <?= __('hours_unit') ?></td></tr>
    <tr><th><?= __('salary_report_sr_staff_total_payment') ?></th><td class="td-mono"><?= $cur($report['staff_total_payment'] ?? null) ?></td></tr>
    <tr><th><?= __('salary_report_sr_staff_avg_hourly_wage') ?></th><td class="td-mono"><?= $cur($report['staff_avg_hourly_wage'] ?? null) ?><?= ($report['staff_avg_hourly_wage'] ?? null) !== null && ($report['staff_avg_hourly_wage'] ?? '') !== '' ? __('per_hour_unit') : '' ?></td></tr>
    <?php if (!$isEmployeeScoped): ?>
    <tr><th><?= __('salary_report_sr_new_hires') ?></th><td class="td-mono"><?= $num($report['new_hires'] ?? null) ?></td></tr>
    <?php endif; ?>
    <?php if (!$isEmployeeScoped): ?>
    <tr><th><?= __('salary_report_sr_resigned_staff') ?></th><td class="td-mono"><?= $num($report['resigned_staff'] ?? null) ?></td></tr>
    <?php endif; ?>
</table>

<?php if (!empty($report['employee_work_hours'])): ?>
<h4 class="detail-subtitle"><?= __('salary_report_sr_employee_work_hours') ?></h4>
<div class="detail-text"><?= nl2br($fmt($report['employee_work_hours'], '')) ?></div>
<?php endif; ?>

<?php if (!empty($report['hire_registrations'])): ?>
<h4 class="detail-subtitle"><?= __('salary_report_sr_hire_registrations') ?></h4>
<div class="detail-text"><?= nl2br($fmt($report['hire_registrations'], '')) ?></div>
<?php endif; ?>
<?php $sections[__('salary_report_sr_section_staff')] = ob_get_clean();

// Détail des shifts et des retenues — uniquement pour un rapport lié à un employé
// (données fournies par reportShowExtras()/StoreStatsService::buildPayslipData(),
// recalculées à la volée, comme l'ancienne fiche de paie autonome).
if (isset($shiftRows)):
    include __DIR__ . '/_payslip-helpers.php';
    ob_start();
    ?>
    <?php if (empty($shiftRows)): ?>
    <p class="form-hint"><?= __('salary_report_payslip_no_shift') ?></p>
    <?php else: ?>
    <table class="detail-table">
        <thead>
            <tr>
                <th><?= __('salary_report_col_day') ?></th>
                <th><?= __('date') ?></th>
                <th><?= __('shift_type') ?></th>
                <th><?= __('schedule') ?></th>
                <th><?= __('gross_h_col') ?></th>
                <th><?= __('pause') ?></th>
                <th><?= __('net_h_col') ?></th>
                <?php if ($anyRate): ?>
                <th><?= __('salary_report_col_rate_h') ?></th>
                <th><?= __('amount') ?></th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($shiftRows as $row): ?>
            <tr>
                <td><?= payslip_dow($row['date']) ?></td>
                <td><?= payslip_date($row['date']) ?></td>
                <td><?= htmlspecialchars($row['type']) ?></td>
                <td><?= htmlspecialchars($row['start']) ?>–<?= htmlspecialchars($row['end']) ?><?php if (!empty($row['has_override'])): ?> <span class="td-override-badge" title="<?= htmlspecialchars(__('shift_manual_override')) ?>">✏️</span><?php endif; ?></td>
                <td class="td-mono"><?= payslip_hours($row['gross_min']) ?></td>
                <td class="td-mono"><?= $row['pause_min'] > 0 ? $row['pause_min'] . ' min' : '—' ?></td>
                <td class="td-mono"><?= payslip_hours($row['net_min']) ?></td>
                <?php if ($anyRate): ?>
                <td class="td-mono"><?= $row['has_rate'] ? number_format($row['rate'], 2) . ' ' . currency_symbol($currency, $currencyStyle) : '—' ?></td>
                <td class="td-mono"><?= $row['has_rate'] ? format_currency($row['cost'], $currency, $currencyStyle) : '—' ?></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4"><?= __('total_row') ?></th>
                <th class="td-mono"><?= payslip_hours($totalGrossMin) ?></th>
                <th></th>
                <th class="td-mono"><?= payslip_hours($totalNetMin) ?></th>
                <?php if ($anyRate): ?>
                <th></th>
                <th class="td-mono"><?= format_currency($totalCost, $currency, $currencyStyle) ?></th>
                <?php endif; ?>
            </tr>
        </tfoot>
    </table>
    <?php if (!empty($type_breakdown)): ?>
    <h4 class="detail-subtitle"><?= __('salary_report_sr_type_breakdown') ?></h4>
    <table class="detail-table">
        <thead>
            <tr><th><?= __('shift_type') ?></th><th><?= __('hours') ?></th><th><?= __('amount') ?></th></tr>
        </thead>
        <tbody>
            <?php foreach ($type_breakdown as $tb): ?>
            <tr>
                <td><?= htmlspecialchars($tb['name']) ?></td>
                <td class="td-mono"><?= payslip_hours($tb['minutes']) ?></td>
                <td class="td-mono"><?= format_currency($tb['amount'], $currency, $currencyStyle) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php if ($deductionsEnabled && !empty($deductions)): ?>
    <h4 class="detail-subtitle"><?= __('salary_report_payslip_summary') ?></h4>
    <table class="detail-table">
        <tr><th><?= __('salary_report_gross_pay') ?></th><td class="td-mono td-highlight"><?= format_currency($totalCost, $currency, $currencyStyle) ?></td></tr>
        <?php foreach ($deductions as $ded): ?>
        <tr>
            <th>
                <?= isset($ded['label_key']) ? __($ded['label_key']) : htmlspecialchars($ded['label'] ?? '') ?>
                <?php if (!empty($ded['is_flat'])): ?> (<?= __('monthly_fixed') ?>)<?php elseif (isset($ded['rate'])): ?> (<?= number_format((float) $ded['rate'], 2) ?>%)<?php endif; ?>
            </th>
            <td class="td-mono">−<?= format_currency($ded['amount'], $currency, $currencyStyle) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr><th><?= __('salary_report_total_deductions') ?></th><td class="td-mono">−<?= format_currency($totalDeductions, $currency, $currencyStyle) ?></td></tr>
        <tr><th><?= __('salary_report_net_pay') ?></th><td class="td-mono td-highlight"><?= format_currency($netPay, $currency, $currencyStyle) ?></td></tr>
    </table>
    <?php endif; ?>
    <?php endif; ?>
    <?php
    $sections[__('salary_report_sr_shift_detail')] = ob_get_clean();
endif;

// Notes
if (!empty($report['remarks'])):
ob_start();
?>
<div class="detail-text"><?= nl2br($fmt($report['remarks'], '')) ?></div>
<?php
$sections[__('salary_report_sr_section_notes')] = ob_get_clean();
endif;

// Render cards
foreach ($sections as $title => $body):
    echo Card::make()->header('<span>' . htmlspecialchars($title) . '</span>')->body($body)->render();
endforeach;
?>
