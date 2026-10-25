@php
use kintai\Contract\View\Components\Badge;

/**
 * @var array  $store
 * @var array  $report
 * @var array|null $author
 * @var array|null $validator
 * @var array  $settings
 * @var array  $authUser
 * @var array|null $membership
 * @var \kintai\Bundles\Installed\DailyReport\Services\DailyReportPermissionService $permissions
 * @var bool   $canEdit
 * @var bool   $canSubmit
 * @var bool   $canValidate
 * @var bool   $canSendMail
 * @var bool   $canDelete
 * @var string $BASE_URL
 */
$storeId  = (int) $store['id'];
$reportId = (int) $report['id'];
$currency = currency_symbol($store['currency'] ?? 'JPY', store_currency_style($store));

$statusLabels = [
    'draft'     => __('daily_report_status_draft'),
    'submitted' => __('daily_report_status_submitted'),
    'validated' => __('daily_report_status_validated'),
];
$st        = $report['status'] ?? 'draft';
$badgeHtml = Badge::make($statusLabels[$st] ?? $st)->status($st)->sm()->render();

$authorName    = trim(($author['last_name'] ?? '') . ' ' . ($author['first_name'] ?? '')) ?: ($author['email'] ?? '—');
$validatorName = $validator
    ? (trim(($validator['last_name'] ?? '') . ' ' . ($validator['first_name'] ?? '')) ?: ($validator['email'] ?? '—'))
    : null;

function drShowDate(string $date): string {
    $dt = \DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('d/m/Y') : $date;
}
function drShowDt(string $dt): string {
    $d = \DateTime::createFromFormat('Y-m-d H:i:s', $dt);
    return $d ? $d->format('d/m/Y H:i') : $dt;
}
function drShowNum(mixed $n, int $dec = 0): string {
    return number_format((float) $n, $dec, ',', ' ');
}
@endphp
@if ($dailyReportCss = bundle_asset('daily-report', 'css/daily-report.css'))
<link rel="stylesheet" href="{!! $dailyReportCss !!}">
@endif

@if (isset($_GET['mail']))
    @if ($_GET['mail'] === '1')
        <div class="alert alert--success mb-sm">{!! __('daily_report_mail_sent_success') !!}</div>
    @else
        <div class="alert alert--danger mb-sm">
            {!! __('daily_report_mail_sent_error') !!}            <a href="{!! $BASE_URL !!}/admin/mail-test" class="alert__link">→ Diagnostic mail</a>
        </div>
    @endif
@endif

@if ($canSendMail && empty($settings['mail_recipient_ids']) && empty($settings['mail_recipients']))
    <div class="alert alert--warning mb-sm">
        {!! __('daily_report_no_recipients_warning') !!}        <a href="{!! $BASE_URL !!}/admin/stores/{!! $storeId !!}/daily-reports/settings" class="alert__link">→ {!! __('settings') !!}</a>
    </div>
@endif

<div class="page-header">
    <h2 class="page-header__title">
        {!! __('daily_report_title') !!} — {!! drShowDate($report['report_date']) !!}        {!! $badgeHtml !!}    </h2>
    <div class="page-header__actions">
        <a href="{!! back_url($BASE_URL . '/admin/stores/' . $storeId . '/daily-reports') !!}"
           class="btn btn--ghost">← {!! __('back') !!}</a>
        @if (($report['status'] ?? '') === 'validated')
            <a href="{!! $BASE_URL !!}/admin/stores/{!! $storeId !!}/daily-reports/{!! $reportId !!}/pdf"
               class="btn btn--ghost" target="_blank">PDF</a>
        @endif
    </div>
</div>

<!-- Détails du rapport -->
<div class="card mb-sm">
    <div class="card-body">
        <div class="form-row form-row--lg dr-show-grid">

            <div>
                <table class="data-table dr-kpi-table">
                    <tbody>
                        <tr>
                            <th>{!! __('daily_report_sales') !!}</th>
                            <td class="text-right">
                                <strong>{!! drShowNum($report['sales_total']) !!} {!! $currency !!}</strong>
                            </td>
                        </tr>
                        <tr>
                            <th>{!! __('daily_report_customers') !!}</th>
                            <td class="text-right">
                                <strong>{!! drShowNum($report['customer_count']) !!}</strong>
                            </td>
                        </tr>
                        <tr>
                            <th>{!! __('daily_report_labor') !!}</th>
                            <td class="text-right">
                                <strong>{!! drShowNum($report['labor_cost']) !!} {!! $currency !!}</strong>
                            </td>
                        </tr>
                        <tr>
                            <th>{!! __('daily_report_waste') !!}</th>
                            <td class="text-right">
                                <strong>{!! drShowNum($report['waste_total']) !!} {!! $currency !!}</strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div>
                <table class="data-table dr-meta-table">
                    <tbody>
                        <tr>
                            <th>{!! __('daily_report_author') !!}</th>
                            <td>{{ $authorName }}</td>
                        </tr>
                        <tr>
                            <th>{!! __('date') !!}</th>
                            <td>{!! drShowDate($report['report_date']) !!}</td>
                        </tr>
                        <tr>
                            <th>{!! __('created_at') !!}</th>
                            <td>{!! drShowDt($report['created_at']) !!}</td>
                        </tr>
                        @if ($report['submitted_at'])
                        <tr>
                            <th>{!! __('daily_report_submitted_at') !!}</th>
                            <td>{!! drShowDt($report['submitted_at']) !!}</td>
                        </tr>
                        @endif
                        @if ($validatorName)
                        <tr>
                            <th>{!! __('daily_report_validated_by') !!}</th>
                            <td>{{ $validatorName }}</td>
                        </tr>
                        <tr>
                            <th>{!! __('daily_report_validated_at') !!}</th>
                            <td>{!! drShowDt($report['validated_at'] ?? '') !!}</td>
                        </tr>
                        @endif
                        @if ($report['mail_sent_at'])
                        <tr>
                            <th>{!! __('daily_report_mail_sent_at') !!}</th>
                            <td>{!! drShowDt($report['mail_sent_at']) !!}</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        @if (!empty($report['notes']))
            <div class="mt-sm">
                <p class="form-label">({!! __('daily_report_notes') !!})</p>
                <div class="card card--shaded">
                    {!! nl2br(htmlspecialchars($report['notes'])) !!}                </div>
            </div>
        @endif
    </div>
</div>

@php
if (!function_exists('drShowH')) {
    function drShowH(int $minutes): string {
        return intdiv($minutes, 60) . 'h' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}
$shiftRows = $shiftRows ?? [];
@endphp

<!-- Planning du jour -->
<div class="card mb-sm">
    <div class="card-header">
        <h3>{!! __('daily_report_shifts_of_day') !!}</h3>
    </div>
    @if (empty($shiftRows))
        <div class="card-body">
            <p class="text-muted text-italic m-0">{!! __('daily_report_no_shifts') !!}</p>
        </div>
    @php else:
        $showCols = $settings['show_columns'] ?? ['employee', 'type', 'schedule', 'gross_hours', 'pause', 'net_hours'];
    @endphp
        <div class="table-responsive">
            <table class="data-table" data-mob-stack>
                <thead>
                    <tr>
                        @if (in_array('employee', $showCols, true))<th>{!! __('employee') !!}</th>@endif
                        @if (in_array('type', $showCols, true))<th>{!! __('shift_type') !!}</th>@endif
                        @if (in_array('schedule', $showCols, true))<th>{!! __('schedule') !!}</th>@endif
                        @if (in_array('gross_hours', $showCols, true))<th class="td-right">{!! __('gross_h_col') !!}</th>@endif
                        @if (in_array('pause', $showCols, true))<th class="td-right">{!! __('pause') !!}</th>@endif
                        @if (in_array('net_hours', $showCols, true))<th class="td-right">{!! __('net_h_col') !!}</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($shiftRows as $row)
                    <tr>
                        @if (in_array('employee', $showCols, true))<td data-label="{{ __('employee') }}">{{ $row['employee'] }}</td>@endif
                        @if (in_array('type', $showCols, true))<td data-label="{{ __('shift_type') }}" class="td-muted">{{ $row['type'] }}</td>@endif
                        @if (in_array('schedule', $showCols, true))<td data-label="{{ __('schedule') }}" class="td-muted td-nowrap">{{ $row['start'] }}–{{ $row['end'] }}</td>@endif
                        @if (in_array('gross_hours', $showCols, true))<td data-label="{{ __('gross_h_col') }}" class="td-right td-mono">{!! drShowH($row['gross_min']) !!}</td>@endif
                        @if (in_array('pause', $showCols, true))<td data-label="{{ __('pause') }}" class="td-right td-muted td-mono">{!! $row['pause_min'] > 0 ? $row['pause_min'] . ' min' : '—' !!}</td>@endif
                        @if (in_array('net_hours', $showCols, true))<td data-label="{{ __('net_h_col') }}" class="td-right td-mono">{!! drShowH($row['net_min']) !!}</td>@endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<!-- Actions -->
@if ($canEdit || $canSubmit || $canValidate || $canSendMail || $canDelete)
<div class="card">
    <div class="card-body">
        <div class="btn-group">

            @if ($canEdit)
                <a href="{!! $BASE_URL !!}/admin/stores/{!! $storeId !!}/daily-reports/{!! $reportId !!}/edit"
                   class="btn btn--primary">{!! __('edit') !!}</a>
            @endif

            @if ($canSubmit)
                <form method="POST"
                      action="{!! $BASE_URL !!}/admin/stores/{!! $storeId !!}/daily-reports/{!! $reportId !!}/submit"
                      data-confirm="{{ __('daily_report_confirm_submit') }}">
                    {!! csrf_field() !!}                    <button type="submit" class="btn btn--warning">{!! __('daily_report_submit') !!}</button>
                </form>
            @endif

            @if ($canValidate)
                <form method="POST"
                      action="{!! $BASE_URL !!}/admin/stores/{!! $storeId !!}/daily-reports/{!! $reportId !!}/validate"
                      data-confirm="{{ __('daily_report_confirm_validate') }}">
                    {!! csrf_field() !!}                    <button type="submit" class="btn btn--success">{!! __('daily_report_validate') !!}</button>
                </form>
            @endif

            @if ($canSendMail && (!empty($settings['mail_recipient_ids']) || !empty($settings['mail_recipients'])))
                <form method="POST"
                      action="{!! $BASE_URL !!}/admin/stores/{!! $storeId !!}/daily-reports/{!! $reportId !!}/send-mail"
                      data-confirm="{{ __('daily_report_confirm_send_mail') }}">
                    {!! csrf_field() !!}                    <button type="submit" class="btn btn--ghost">✉ {!! __('daily_report_send_mail') !!}</button>
                </form>
            @endif

            @if ($canDelete)
                <form method="POST"
                      action="{!! $BASE_URL !!}/admin/stores/{!! $storeId !!}/daily-reports/{!! $reportId !!}/delete"
                      data-confirm="{{ __('daily_report_confirm_delete') }}"
                      class="ml-auto">
                    {!! csrf_field() !!}                    <button type="submit" class="btn btn--danger">{!! __('delete') !!}</button>
                </form>
            @endif

        </div>
    </div>
</div>
@endif
