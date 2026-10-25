<?php

declare(strict_types=1);

use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Card;
use kintai\Contract\View\Components\Modal;

/**
 * @var array  $store
 * @var string $mode    'create' | 'edit'
 * @var array  $report
 * @var string $BASE_URL
 * @var array  $managers
 */

$report ??= [];
$storeId = (int) $store['id'];
$reportId = (int) ($report['id'] ?? 0);
$currency = $store['currency'] ?? 'JPY';
$currencySymbol = currency_symbol($currency, store_currency_style($store));

$action = $mode === 'edit'
    ? $BASE_URL . '/admin/stores/' . $storeId . '/reports/salary/' . $reportId . '/edit'
    : $BASE_URL . '/admin/stores/' . $storeId . '/reports/salary/create';

$val = fn(string $key, string $default = '') => htmlspecialchars((string) ($report[$key] ?? $default));
$employeeId = (int) ($report['user_id'] ?? 0);
$isEmployeeScoped = $employeeId > 0;

/** Libellé de champ suffixé par son unité (devise du store, ou heures) — les montants nus sans unité sont une des choses qui rendaient ce formulaire dense et peu lisible. */
$moneyLabel = fn(string $key) => __($key) . ' (' . $currencySymbol . ')';
$hoursLabel = fn(string $key) => __($key) . ' (h)';
?>
<?php if ($salaryReportCss = bundle_asset('salary-report', 'css/salary-report.css')): ?>
<link rel="stylesheet" href="<?= $salaryReportCss ?>">
<?php endif; ?>
<div class="page-header">
    <h2 class="page-header__title">
        <?= $mode === 'edit' ? __('salary_report_sr_edit') : __('salary_report_sr_new') ?> — <?= htmlspecialchars($store['name'] ?? '') ?>
        <?php if ($isEmployeeScoped): ?> — <?= $val('employee_name') ?><?php endif; ?>
    </h2>
    <div class="page-header__actions">
        <?= Button::make('← ' . __('back'))->ghost()->sm()->link(back_url($BASE_URL . '/admin/stores/' . $storeId . '/reports/salary'))->render() ?>
    </div>
</div>

<form method="POST" action="<?= htmlspecialchars($action) ?>" class="form-stack">
    <?= csrf_field() ?>
    <input type="hidden" name="user_id" value="<?= $employeeId ?>">
    <input type="hidden" name="employee_name" value="<?= $val('employee_name') ?>">
    <input type="hidden" name="store_name" value="<?= htmlspecialchars($store['name'] ?? '') ?>">
    <?php if ($isEmployeeScoped): ?>
    <!-- Champs sans objet pour un rapport lié à un seul employé (voir sr_employee_scope_hint) : conservés tels quels, non affichés. -->
    <input type="hidden" name="active_employees" value="<?= $val('active_employees', '0') ?>">
    <input type="hidden" name="new_hires" value="<?= $val('new_hires', '0') ?>">
    <input type="hidden" name="resigned_staff" value="<?= $val('resigned_staff', '0') ?>">
    <input type="hidden" name="total_payment" value="<?= $val('total_payment', '0') ?>">
    <input type="hidden" name="hire_registrations" value="<?= $val('hire_registrations') ?>">
    <?php endif; ?>

    <?php
    ob_start();
    if ($isEmployeeScoped): ?>
    <p class="form-hint"><?= __('salary_report_sr_employee_scope_hint') ?></p>
    <?php endif; ?>
    <div class="form-row">
        <div class="form-group sr-period" id="sr-period"
             data-calculate-url="<?= htmlspecialchars($BASE_URL . '/admin/stores/' . $storeId . '/reports/salary/calculate') ?>"
             data-mode="<?= htmlspecialchars($mode) ?>"
             data-user-id="<?= $employeeId ?>"
             data-currency="<?= htmlspecialchars($currency) ?>"
             data-currency-style="<?= htmlspecialchars(store_currency_style($store)) ?>">
            <label class="form-label"><?= __('salary_report_sr_target_month') ?></label>

            <div class="btn-group btn-group--switcher btn-group--switcher-2 mb-xs">
                <span class="btn-group__thumb btn-group__thumb--pos-0" aria-hidden="true"></span>
                <button type="button" class="btn btn--ghost btn--sm btn--active" data-period-mode="month"><?= __('salary_report_sr_period_month') ?></button>
                <button type="button" class="btn btn--ghost btn--sm" data-period-mode="custom"><?= __('salary_report_sr_period_custom') ?></button>
            </div>

            <div data-period-panel="month">
                <input type="month" id="sr-period-month" class="form-control" value="<?= $val('target_month') ?>">
            </div>
            <div data-period-panel="custom" class="form-row" hidden>
                <div class="form-group">
                    <label class="form-label" for="sr-period-from"><?= __('salary_report_sr_period_from') ?></label>
                    <input type="date" id="sr-period-from" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" for="sr-period-to"><?= __('salary_report_sr_period_to') ?></label>
                    <input type="date" id="sr-period-to" class="form-control">
                </div>
            </div>

            <input type="hidden" name="target_month" id="sr-target-month" value="<?= $val('target_month') ?>">

            <div class="sr-period__actions">
                <?= Button::make(__('salary_report_sr_recalculate'))->ghost()->sm()->attrs(['type' => 'button', 'id' => 'sr-recalculate'])->render() ?>
                <?= Button::make(__('salary_report_sr_calc_detail'))->ghost()->sm()->attrs(['type' => 'button', 'id' => 'sr-calc-detail'])->render() ?>
                <span id="sr-period-status" class="sr-period__status" aria-live="polite"></span>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label"><?= __('salary_report_sr_person_in_charge') ?></label>
            <select name="person_in_charge" class="form-control" required>
                <option value="">— <?= __('select') ?> —</option>
                <?php $selectedPic = $report['person_in_charge'] ?? ''; ?>
                <?php foreach ($managers ?? [] as $m): ?>
                    <?php $mName = trim(($m['last_name'] ?? '') . ' ' . ($m['first_name'] ?? '')) ?: ($m['display_name'] ?? $m['email'] ?? ''); ?>
                    <option value="<?= htmlspecialchars($mName) ?>" <?= $mName === $selectedPic ? 'selected' : '' ?>><?= htmlspecialchars($mName) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php
    $basicBody = ob_get_clean();
    echo Card::make()->header(__('salary_report_sr_section_basic'))->body($basicBody)->render();
    ?>

    <?php ob_start(); ?>
    <div class="form-row">
        <?php if (!$isEmployeeScoped): ?>
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('salary_report_sr_total_payment') ?></label>
            <input type="text" inputmode="decimal" name="total_payment" class="form-control td-mono js-money-input"
                   value="<?= $val('total_payment', '0') ?>">
        </div>
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('salary_report_sr_net_payment') ?></label>
            <input type="text" inputmode="decimal" name="net_payment" class="form-control td-mono js-money-input"
                   value="<?= $val('net_payment', '0') ?>">
        </div>
    </div>

    <h4 class="section-subtitle"><?= __('salary_report_sr_section_deductions') ?></h4>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('salary_report_sr_income_tax_base') ?></label>
            <input type="text" inputmode="decimal" name="income_tax_base" class="form-control td-mono js-money-input"
                   value="<?= $val('income_tax_base', '0') ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('salary_report_sr_total_deductions') ?></label>
            <input type="text" inputmode="decimal" name="total_deductions" class="form-control td-mono js-money-input"
                   value="<?= $val('total_deductions', '0') ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('salary_report_sr_withholding_tax') ?></label>
            <input type="text" inputmode="decimal" name="withholding_tax" class="form-control td-mono js-money-input"
                   value="<?= $val('withholding_tax', '0') ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('salary_report_sr_residence_tax') ?></label>
            <input type="text" inputmode="decimal" name="residence_tax" class="form-control td-mono js-money-input"
                   value="<?= $val('residence_tax', '0') ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('salary_report_sr_other_deductions') ?></label>
            <input type="text" inputmode="decimal" name="other_deductions" class="form-control td-mono js-money-input"
                   value="<?= $val('other_deductions', '0') ?>">
        </div>
    </div>

    <h4 class="section-subtitle"><?= __('salary_report_sr_section_payout') ?></h4>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('salary_report_sr_bank_transfer_salary') ?></label>
            <input type="text" inputmode="decimal" name="bank_transfer_salary" class="form-control td-mono js-money-input"
                   value="<?= $val('bank_transfer_salary', '0') ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('salary_report_sr_hand_delivered_salary') ?></label>
            <input type="text" inputmode="decimal" name="hand_delivered_salary" class="form-control td-mono js-money-input"
                   value="<?= $val('hand_delivered_salary', '0') ?>">
        </div>
    </div>
    <?php
    $finBody = ob_get_clean();
    echo Card::make()->header(__('salary_report_sr_section_financial'))->body($finBody)->render();
    ?>

    <?php ob_start(); ?>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= $hoursLabel('salary_report_sr_staff_man_hours') ?></label>
            <input type="number" name="staff_man_hours" class="form-control"
                   step="0.01" min="0" value="<?= $val('staff_man_hours', '0') ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= $moneyLabel('salary_report_sr_staff_total_payment') ?></label>
            <input type="text" inputmode="decimal" name="staff_total_payment" class="form-control td-mono js-money-input"
                   value="<?= $val('staff_total_payment', '0') ?>">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= __('salary_report_sr_staff_avg_hourly_wage') ?> (<?= $currencySymbol ?><?= __('per_hour_unit') ?>)</label>
            <input type="text" inputmode="decimal" name="staff_avg_hourly_wage" class="form-control td-mono js-money-input"
                   value="<?= $val('staff_avg_hourly_wage', '0') ?>">
        </div>
        <?php if (!$isEmployeeScoped): ?>
        <div class="form-group">
            <label class="form-label"><?= __('salary_report_sr_active_employees') ?></label>
            <input type="number" name="active_employees" class="form-control"
                   step="1" min="0" value="<?= $val('active_employees', '0') ?>">
        </div>
        <?php endif; ?>
    </div>
    <div class="form-group">
        <label class="form-label"><?= __('salary_report_sr_employee_work_hours') ?></label>
        <textarea name="employee_work_hours" class="form-control" rows="3"><?= $val('employee_work_hours') ?></textarea>
    </div>

    <?php if (!$isEmployeeScoped): ?>
    <h4 class="section-subtitle"><?= __('salary_report_sr_section_hr_movements') ?></h4>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label"><?= __('salary_report_sr_new_hires') ?></label>
            <input type="number" name="new_hires" class="form-control"
                   step="1" min="0" value="<?= $val('new_hires', '0') ?>">
        </div>
        <div class="form-group">
            <label class="form-label"><?= __('salary_report_sr_resigned_staff') ?></label>
            <input type="number" name="resigned_staff" class="form-control"
                   step="1" min="0" value="<?= $val('resigned_staff', '0') ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label"><?= __('salary_report_sr_hire_registrations') ?></label>
        <textarea name="hire_registrations" class="form-control" rows="3"><?= $val('hire_registrations') ?></textarea>
    </div>
    <?php endif; ?>
    <?php
    $staffBody = ob_get_clean();
    echo Card::make()->header(__('salary_report_sr_section_staff'))->body($staffBody)->render();
    ?>

    <?php ob_start(); ?>
    <div class="form-group">
        <label class="form-label"><?= __('salary_report_sr_remarks') ?></label>
        <textarea name="remarks" class="form-control" rows="4"><?= $val('remarks') ?></textarea>
    </div>
    <?php
    $notesBody = ob_get_clean();
    echo Card::make()->header(__('salary_report_sr_section_notes'))->body($notesBody)->render();
    ?>

    <div class="form-actions">
        <?= Button::make($mode === 'edit' ? __('save') : __('salary_report_sr_create'))->primary()->submit()->render() ?>
        <a href="<?= htmlspecialchars(back_url($BASE_URL . '/admin/stores/' . $storeId . '/reports/salary')) ?>" class="btn btn--ghost"><?= __('cancel') ?></a>
    </div>
</form>

<?= Modal::make('sr-calc-detail-modal')->title(htmlspecialchars(__('salary_report_sr_calc_detail_title')))->wide()->body(
    '<div id="sr-calc-detail-body"></div>'
)->render() ?>

<script id="sr-i18n" type="application/json"><?= json_encode([
    'recalculating'        => __('salary_report_sr_recalculating'),
    'calc_error'            => __('salary_report_sr_calc_error'),
    'confirm_recalculate'   => __('salary_report_sr_confirm_recalculate'),
    'gross_pay'             => __('salary_report_gross_pay'),
    'net_pay'               => __('salary_report_net_pay'),
    'total_deductions'      => __('salary_report_total_deductions'),
    'employee'              => __('employee'),
    'hours'                 => __('hours'),
    'amount'                => __('amount'),
    'payslip_no_shift'      => __('salary_report_payslip_no_shift'),
    'monthly_fixed'         => __('monthly_fixed'),
    'ded_health_insurance'  => __('ded_health_insurance'),
    'ded_pension'           => __('ded_pension'),
    'ded_employment_insurance' => __('ded_employment_insurance'),
    'ded_income_tax'        => __('ded_income_tax'),
    'ded_resident_tax'      => __('ded_resident_tax'),
    'col_day'               => __('salary_report_col_day'),
    'date'                  => __('date'),
    'shift_type'            => __('shift_type'),
    'schedule'               => __('schedule'),
    'gross_h_col'           => __('gross_h_col'),
    'pause'                 => __('pause'),
    'net_h_col'             => __('net_h_col'),
    'col_rate_h'            => __('salary_report_col_rate_h'),
    'total_row'             => __('total_row'),
    'sr_type_breakdown'     => __('salary_report_sr_type_breakdown'),
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?></script>
<?php if ($salaryReportFormJs = bundle_asset('salary-report', 'js/salary-report-form.js')): ?>
<script src="<?= $salaryReportFormJs ?>"></script>
<?php endif; ?>
