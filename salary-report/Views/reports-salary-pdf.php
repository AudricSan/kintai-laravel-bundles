<?php

declare(strict_types=1);

/**
 * Template HTML optimisé mPDF — 給与報告書 (Salary Report)
 * Rendu sans layout : soit pour la génération PDF serveur, soit directement
 * comme aperçu navigateur (avec barre d'outils) quand $downloadUrl est
 * fourni — voir HasStaffReportCrud::reportPdf() vs reportPdfDownload().
 *
 * @var array       $report
 * @var array       $store
 * @var string|null $downloadUrl
 */

$currency = $store['currency'] ?? 'JPY';
$currencyStyle = store_currency_style($store);

$fmt = fn(mixed $v, string $def = '—') => $v !== null && $v !== '' ? htmlspecialchars((string) $v) : $def;
$cur = fn(mixed $v) => $v !== null && $v !== '' ? format_currency((float) $v, $currency, $currencyStyle) : '—';
$isEmployeeScoped = !empty($report['user_id']);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style nonce="<?= csp_nonce() ?>">
<?php
echo file_get_contents(BASE_PATH . '/public/assets/css/pdf/pdf-base.css');
echo file_get_contents(BASE_PATH . '/public/assets/css/pdf/pdf-brand.css');
echo file_get_contents(BASE_PATH . '/public/assets/css/pdf/pdf-preview.css');
echo file_get_contents(bundle_asset_path('salary-report', 'css/pdf-salary-report.css') ?? '');
?>
</style>
</head>
<body>

<?php include BASE_PATH . '/src/UI/View/_partials/_pdf-preview-toolbar.php'; ?>
<div class="pdf-preview-page">

<h1><?= __('salary_report_sr_pdf_title') ?></h1>
<p class="subtitle">
    <?= htmlspecialchars($store['name'] ?? '') ?> —
    <?= $fmt($report['target_month'] ?? '') ?>
</p>

<!-.- Informations de base -->
<h2><?= __('salary_report_sr_section_basic') ?></h2>
<table>
    <tr>
        <th class="pdf-w-30"><?= __('salary_report_sr_target_month') ?></th>
        <td><?= $fmt($report['target_month'] ?? '') ?></td>
    </tr>
    <tr>
        <th><?= __('store') ?></th>
        <td><?= $fmt($report['store_name'] ?? ($store['name'] ?? '')) ?></td>
    </tr>
    <?php if (!empty($report['user_id'])): ?>
    <tr>
        <th><?= __('employee_name') ?></th>
        <td><?= $fmt($report['employee_name'] ?? '') ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <th><?= __('salary_report_sr_person_in_charge') ?></th>
        <td><?= $fmt($report['person_in_charge'] ?? '') ?></td>
    </tr>
</table>

<!-.- Résumé financier -->
<h2><?= __('salary_report_sr_section_financial') ?></h2>
<table>
    <?php if (!$isEmployeeScoped): ?>
    <tr>
        <th class="pdf-w-50"><?= __('salary_report_sr_total_payment') ?></th>
        <td class="tr td-mono"><?= $cur($report['total_payment'] ?? null) ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <th><?= __('salary_report_sr_total_deductions') ?></th>
        <td class="tr td-mono"><?= $cur($report['total_deductions'] ?? null) ?></td>
    </tr>
    <tr>
        <th class="pdf-pl-16"><?= __('salary_report_sr_income_tax_base') ?></th>
        <td class="tr td-mono"><?= $cur($report['income_tax_base'] ?? null) ?></td>
    </tr>
    <tr>
        <th class="pdf-pl-16"><?= __('salary_report_sr_withholding_tax') ?></th>
        <td class="tr td-mono"><?= $cur($report['withholding_tax'] ?? null) ?></td>
    </tr>
    <tr>
        <th class="pdf-pl-16"><?= __('salary_report_sr_residence_tax') ?></th>
        <td class="tr td-mono"><?= $cur($report['residence_tax'] ?? null) ?></td>
    </tr>
    <tr>
        <th class="pdf-pl-16"><?= __('salary_report_sr_other_deductions') ?></th>
        <td class="tr td-mono"><?= $cur($report['other_deductions'] ?? null) ?></td>
    </tr>
    <tr class="pdf-row-highlight">
        <th><?= __('salary_report_sr_net_payment') ?></th>
        <td class="tr td-mono"><?= $cur($report['net_payment'] ?? null) ?></td>
    </tr>
    <tr>
        <th><?= __('salary_report_sr_hand_delivered_salary') ?></th>
        <td class="tr td-mono"><?= $cur($report['hand_delivered_salary'] ?? null) ?></td>
    </tr>
    <tr>
        <th><?= __('salary_report_sr_bank_transfer_salary') ?></th>
        <td class="tr td-mono"><?= $cur($report['bank_transfer_salary'] ?? null) ?></td>
    </tr>
    <?php if (!$isEmployeeScoped): ?>
    <tr>
        <th><?= __('salary_report_sr_active_employees') ?></th>
        <td class="tr td-mono"><?= $fmt($report['active_employees'] ?? null) ?></td>
    </tr>
    <?php endif; ?>
</table>

<!-.- Personnel -->
<h2><?= __('salary_report_sr_section_staff') ?></h2>
<table>
    <tr>
        <th class="pdf-w-50"><?= __('salary_report_sr_staff_man_hours') ?></th>
        <td class="tr td-mono"><?= $fmt($report['staff_man_hours'] ?? null) ?> <?= __('hours_unit') ?></td>
    </tr>
    <tr>
        <th><?= __('salary_report_sr_staff_total_payment') ?></th>
        <td class="tr td-mono"><?= $cur($report['staff_total_payment'] ?? null) ?></td>
    </tr>
    <tr>
        <th><?= __('salary_report_sr_staff_avg_hourly_wage') ?></th>
        <td class="tr td-mono"><?= $cur($report['staff_avg_hourly_wage'] ?? null) ?><?= ($report['staff_avg_hourly_wage'] ?? null) !== null && ($report['staff_avg_hourly_wage'] ?? '') !== '' ? __('per_hour_unit') : '' ?></td>
    </tr>
    <tr>
        <th><?= __('salary_report_sr_employee_work_hours') ?></th>
        <td><?= nl2br($fmt($report['employee_work_hours'] ?? null, '')) ?></td>
    </tr>
    <?php if (!$isEmployeeScoped): ?>
    <tr>
        <th><?= __('salary_report_sr_new_hires') ?></th>
        <td class="tr td-mono"><?= $fmt($report['new_hires'] ?? null) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!$isEmployeeScoped): ?>
    <tr>
        <th><?= __('salary_report_sr_resigned_staff') ?></th>
        <td class="tr td-mono"><?= $fmt($report['resigned_staff'] ?? null) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!empty($report['hire_registrations'])): ?>
    <tr>
        <th><?= __('salary_report_sr_hire_registrations') ?></th>
        <td><?= nl2br($fmt($report['hire_registrations'], '')) ?></td>
    </tr>
    <?php endif; ?>
</table>

<?php if (isset($shiftRows)): ?>
<!-.- Détail des shifts (employé) -->
<h2><?= __('salary_report_sr_shift_detail') ?></h2>
<?php if (empty($shiftRows)): ?>
<p><?= __('salary_report_payslip_no_shift') ?></p>
<?php else: ?>
<?php include __DIR__ . '/_payslip-helpers.php'; ?>
<table>
    <tr>
        <th><?= __('salary_report_col_day') ?></th>
        <th><?= __('date') ?></th>
        <th><?= __('shift_type') ?></th>
        <th><?= __('schedule') ?></th>
        <th class="tr"><?= __('gross_h_col') ?></th>
        <th class="tr"><?= __('pause') ?></th>
        <th class="tr"><?= __('net_h_col') ?></th>
        <?php if ($anyRate): ?>
        <th class="tr"><?= __('salary_report_col_rate_h') ?></th>
        <th class="tr"><?= __('amount') ?></th>
        <?php endif; ?>
    </tr>
    <?php foreach ($shiftRows as $row): ?>
    <tr>
        <td><?= payslip_dow($row['date']) ?></td>
        <td><?= payslip_date($row['date']) ?></td>
        <td><?= htmlspecialchars($row['type']) ?></td>
        <td><?= htmlspecialchars($row['start']) ?>–<?= htmlspecialchars($row['end']) ?></td>
        <td class="tr td-mono"><?= payslip_hours($row['gross_min']) ?></td>
        <td class="tr td-mono"><?= $row['pause_min'] > 0 ? $row['pause_min'] . ' min' : '—' ?></td>
        <td class="tr td-mono"><?= payslip_hours($row['net_min']) ?></td>
        <?php if ($anyRate): ?>
        <td class="tr td-mono"><?= $row['has_rate'] ? number_format($row['rate'], 2) . ' ' . currency_symbol($currency, $currencyStyle) : '—' ?></td>
        <td class="tr td-mono"><?= $row['has_rate'] ? format_currency($row['cost'], $currency, $currencyStyle) : '—' ?></td>
        <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <tr class="pdf-row-highlight">
        <td colspan="4"><?= __('total_row') ?></td>
        <td class="tr td-mono"><?= payslip_hours($totalGrossMin) ?></td>
        <td></td>
        <td class="tr td-mono"><?= payslip_hours($totalNetMin) ?></td>
        <?php if ($anyRate): ?>
        <td></td>
        <td class="tr td-mono"><?= format_currency($totalCost, $currency, $currencyStyle) ?></td>
        <?php endif; ?>
    </tr>
</table>
<?php if (!empty($type_breakdown)): ?>
<h2><?= __('salary_report_sr_type_breakdown') ?></h2>
<table>
    <tr>
        <th><?= __('shift_type') ?></th>
        <th><?= __('hours') ?></th>
        <th><?= __('amount') ?></th>
    </tr>
    <?php foreach ($type_breakdown as $tb): ?>
    <tr>
        <td><?= htmlspecialchars($tb['name']) ?></td>
        <td class="tr td-mono"><?= payslip_hours($tb['minutes']) ?></td>
        <td class="tr td-mono"><?= format_currency($tb['amount'], $currency, $currencyStyle) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
<?php if ($deductionsEnabled && !empty($deductions)): ?>
<h2><?= __('salary_report_payslip_summary') ?></h2>
<table>
    <tr>
        <th class="pdf-w-70"><?= __('salary_report_gross_pay') ?></th>
        <td class="tr td-mono"><?= format_currency($totalCost, $currency, $currencyStyle) ?></td>
    </tr>
    <?php foreach ($deductions as $ded): ?>
    <tr>
        <th class="pdf-w-70">
            <?= isset($ded['label_key']) ? __($ded['label_key']) : htmlspecialchars($ded['label'] ?? '') ?>
            <?php if (!empty($ded['is_flat'])): ?> (<?= __('monthly_fixed') ?>)<?php elseif (isset($ded['rate'])): ?> (<?= number_format((float) $ded['rate'], 2) ?>%)<?php endif; ?>
        </th>
        <td class="tr td-mono">−<?= format_currency($ded['amount'], $currency, $currencyStyle) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="pdf-bold">
        <th><?= __('salary_report_total_deductions') ?></th>
        <td class="tr td-mono">−<?= format_currency($totalDeductions, $currency, $currencyStyle) ?></td>
    </tr>
    <tr class="pdf-row-highlight">
        <th><?= __('salary_report_net_pay') ?></th>
        <td class="tr td-mono"><?= format_currency($netPay, $currency, $currencyStyle) ?></td>
    </tr>
</table>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<?php if (!empty($report['remarks'])): ?>
<!-.- Remarques -->
<h2><?= __('salary_report_sr_section_notes') ?></h2>
<p><?= nl2br($fmt($report['remarks'], '')) ?></p>
<?php endif; ?>

<!-.- Signature -->
<div class="sig-section">
    <table>
        <tr>
            <td class="pdf-center">
                <div><?= __('salary_report_sr_pdf_prepared_by') ?></div>
                <div class="sig-line"></div>
                <div><?= $fmt($report['person_in_charge'] ?? '') ?></div>
            </td>
            <td class="pdf-center">
                <div><?= __('salary_report_sr_pdf_approved_by') ?></div>
                <div class="sig-line"></div>
                <div><?= htmlspecialchars($store['name'] ?? '') ?></div>
            </td>
            <td class="pdf-center">
                <div><?= __('salary_report_sr_pdf_date') ?></div>
                <div class="sig-line"></div>
                <div><?= date('Y/m/d') ?></div>
            </td>
        </tr>
    </table>
</div>

<div class="footer">
    <?= __('salary_report_sr_pdf_footer', ['store' => htmlspecialchars($store['name'] ?? ''), 'date' => date('Y/m/d H:i')]) ?>
</div>

</div>
</body>
</html>
