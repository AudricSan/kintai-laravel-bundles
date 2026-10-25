@php
use kintai\Contract\View\Components\Button;

/**
 * @var array $store
 * @var array $report
 */

$storeId = (int) $store['id'];
$reportId = (int) $report['id'];
$base = $BASE_URL . '/admin/stores/' . $storeId . '/reports/resignation';
@endphp
<div class="page-header">
    <h2 class="page-header__title">{!! __('resignation_report') !!} — {{ $report['employee_name'] ?? '' }}</h2>
    <div class="page-header__actions">
        {!! Button::make('← ' . __('back'))->ghost()->sm()->link(back_url($base))->render() !!}        {!! Button::make(__('edit'))->primary()->sm()->link($base . '/' . $reportId . '/edit')->render() !!}        {!! Button::make('PDF')->ghost()->sm()->link($base . '/' . $reportId . '/pdf')->attrs(['target' => '_blank'])->render() !!}        @if (!empty($report['user_id']))
        <button type="button" class="btn btn--danger btn--sm js-rr-delete"
            data-reactivate-url="{{ $base . '/' . $reportId . '/delete' }}"
            data-delete-url="{{ $base . '/' . $reportId . '/delete-permanently' }}"
            data-employee-name="{{ $report['employee_name'] ?? '' }}">{!! __('delete') !!}</button>
        @else
        <form method="POST" action="{!! $base . '/' . $reportId . '/delete' !!}" class="form-inline" data-confirm="{{ __('resignation_report_confirm_delete_resignation_report') }}">
            {!! csrf_field() !!}            {!! Button::make(__('delete'))->danger()->sm()->submit()->render() !!}        </form>
        @endif
    </div>
</div>

<div id="rr-delete-modal" class="modal">
    <div class="modal__backdrop js-rr-delete-close"></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3 class="modal__title">{!! __('resignation_report_delete_title') !!}</h3>
            <button type="button" class="modal__close js-rr-delete-close">✕</button>
        </div>
        <div class="modal__body">
            <p>{!! __('resignation_report_delete_intro') !!} <strong id="rr-delete-employee-name"></strong></p>
            <form id="rr-reactivate-form" method="POST" action="">
                {!! csrf_field() !!}                <button type="submit" class="btn btn--warning btn--block">{!! __('resignation_report_reactivate_and_delete_report') !!}</button>
                <p class="form-hint">{!! __('resignation_report_reactivate_and_delete_report_hint') !!}</p>
            </form>
            <form id="rr-delete-permanently-form" method="POST" action="" class="mt-sm" data-confirm="{{ __('resignation_report_confirm_delete_employee_permanently') }}">
                {!! csrf_field() !!}                <button type="submit" class="btn btn--danger btn--block">{!! __('resignation_report_delete_employee_and_report_permanently') !!}</button>
                <p class="form-hint">{!! __('resignation_report_delete_employee_and_report_permanently_hint') !!}</p>
            </form>
        </div>
        <div class="modal__footer">
            <button type="button" class="btn btn--ghost js-rr-delete-close">{!! __('cancel') !!}</button>
        </div>
    </div>
</div>

@if ($resignationDeleteJs = bundle_asset('resignation-report', 'js/resignation-report-delete-modal.js'))
<script src="{!! $resignationDeleteJs !!}"></script>
@endif

<div class="card">
    <div class="card-body">
        <table class="detail-table">
            <tr>
                <th>{!! __('employee_number') !!}</th>
                <td>{{ $report['employee_number'] ?? '—' }}</td>
            </tr>
            <tr>
                <th>{!! __('employee_name') !!}</th>
                <td>{{ $report['employee_name'] ?? '—' }}</td>
            </tr>
            <tr>
                <th>{!! __('resignation_report_resignation_date') !!}</th>
                <td>{{ $report['resignation_date'] ?? '—' }}</td>
            </tr>
            <tr>
                <th>{!! __('reason') !!}</th>
                <td>{!! nl2br(htmlspecialchars($report['reason'] ?? '—')) !!}</td>
            </tr>
            <tr>
                <th>{!! __('resignation_report_resignation_notice') !!}</th>
                <td>{!! nl2br(htmlspecialchars($report['resignation_notice'] ?? '—')) !!}</td>
            </tr>
            <tr>
                <th>{!! __('notes') !!}</th>
                <td>{!! nl2br(htmlspecialchars($report['notes'] ?? '—')) !!}</td>
            </tr>
            <tr>
                <th>{!! __('resignation_report_person_in_charge') !!}</th>
                <td>{{ $report['person_in_charge'] ?? '—' }}</td>
            </tr>
        </table>
    </div>
</div>
