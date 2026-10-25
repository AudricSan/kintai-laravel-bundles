@php
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Card;

/**
 * @var array $store
 * @var array $report
 */
$storeId = (int) $store['id'];
$reportId = (int) $report['id'];
$base = $BASE_URL . '/admin/stores/' . $storeId . '/reports/hiring';
@endphp
<div class="page-header">
    <h2 class="page-header__title">{!! __('hiring_report') !!} — {{ $report['employee_name'] ?? '' }}</h2>
    <div class="page-header__actions">
        {!! Button::make('← ' . __('back'))->ghost()->sm()->link(back_url($base))->render() !!}        {!! Button::make(__('edit'))->primary()->sm()->link($base . '/' . $reportId . '/edit')->render() !!}        {!! Button::make('PDF')->ghost()->sm()->link($base . '/' . $reportId . '/pdf')->attrs(['target' => '_blank'])->render() !!}        <form method="POST" action="{{ $base . '/' . $reportId . '/delete' }}" class="form-inline" data-confirm="{{ __('hiring_report_confirm_delete_hiring_report') }}">
            {!! csrf_field() !!}            {!! Button::make(__('delete'))->danger()->sm()->submit()->render() !!}        </form>
    </div>
</div>

@php
$field = function(string $label, string $key, string $default = '—') use ($report): string {
    $val = $report[$key] ?? null;
    return '<tr><td class="hr-label">' . htmlspecialchars($label) . '</td><td class="hr-value">' . ($val !== null && $val !== '' ? htmlspecialchars((string) $val) : $default) . '</td></tr>';
};

ob_start();
@endphp
<table class="data-table">
    <thead>
        <tr><th colspan="2">{!! __('hiring_report_section_identity') !!}</th></tr>
    </thead>
    <tbody>
        {!! $field(__('employee_number'), 'employee_number') !!}        {!! $field(__('employee_name'), 'employee_name') !!}        {!! $field(__('furigana_last_name'), 'furigana_last_name') !!}        {!! $field(__('furigana_first_name'), 'furigana_first_name') !!}        {!! $field(__('gender'), 'gender', '—') !!}        {!! $field(__('tax_classification'), 'tax_classification', '—') !!}        {!! $field(__('birth_date'), 'birth_date') !!}        {!! $field(__('education'), 'education') !!}    </tbody>
</table>
<table class="data-table">
    <thead>
        <tr><th colspan="2">{!! __('hiring_report_section_contact') !!}</th></tr>
    </thead>
    <tbody>
        {!! $field(__('postal_code'), 'postal_code') !!}        {!! $field(__('address'), 'address') !!}        {!! $field(__('phone'), 'phone') !!}        {!! $field(__('mobile_phone'), 'mobile_phone') !!}        {!! $field(__('email'), 'email') !!}    </tbody>
</table>
<table class="data-table">
    <thead>
        <tr><th colspan="2">{!! __('hiring_report_section_guarantor') !!}</th></tr>
    </thead>
    <tbody>
        {!! $field(__('guarantor_name'), 'guarantor_name') !!}        {!! $field(__('guarantor_phone'), 'guarantor_phone') !!}    </tbody>
</table>
<table class="data-table">
    <thead>
        <tr><th colspan="2">{!! __('hiring_report_section_employment') !!}</th></tr>
    </thead>
    <tbody>
        {!! $field(__('hiring_report_store_name'), 'store_name') !!}        {!! $field(__('hiring_report_hired_by'), 'hired_by') !!}        {!! $field(__('hiring_report_hire_date'), 'hire_date') !!}        {!! $field(__('notes'), 'notes') !!}    </tbody>
</table>
@php
echo Card::make()->body(ob_get_clean())->render();
@endphp
