@php
/**
 * @var array       $store
 * @var array|null  $report      null = création
 * @var array|null  $existing    rapport déjà existant pour cette date
 * @var string      $date        date par défaut
 * @var array       $errors
 * @var array       $old         anciennes valeurs en cas d'erreur de validation
 * @var array       $shiftRows
 * @var array       $authUser
 * @var array|null  $membership
 * @var array       $settings
 * @var string      $BASE_URL
 */

$storeId  = (int) $store['id'];
$isEdit   = $report !== null;
$reportId = $isEdit ? (int) $report['id'] : null;
$old      = $old ?? $report ?? [];
$currency = currency_symbol($store['currency'] ?? 'JPY', store_currency_style($store));

$action = $isEdit
    ? $BASE_URL . '/admin/stores/' . $storeId . '/daily-reports/' . $reportId . '/edit'
    : $BASE_URL . '/admin/stores/' . $storeId . '/daily-reports/create';

function drFormVal(array $old, string $key, mixed $default = ''): string
{
    return htmlspecialchars((string) ($old[$key] ?? $default));
}

if (!function_exists('drFormH')) {
    function drFormH(int $minutes): string
    {
        return intdiv($minutes, 60) . 'h' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}

$shiftRows = $shiftRows ?? [];
$backUrl   = back_url($isEdit
    ? $BASE_URL . '/admin/stores/' . $storeId . '/daily-reports/' . $reportId
    : $BASE_URL . '/admin/stores/' . $storeId . '/daily-reports');
@endphp

<div class="page-header">
    <h2 class="page-header__title">
        {!! $isEdit ? __('daily_report_edit') : __('daily_report_new') !!} — {{ $store['name'] }}    </h2>
    <div class="page-header__actions">
        <a href="{!! $backUrl !!}" class="btn btn--ghost btn--sm">← {!! __('back') !!}</a>
    </div>
</div>

@if ($existing)
    <div class="alert alert--warning mb-sm">
        {!! __('daily_report_already_exists', ['date' => htmlspecialchars($existing['report_date'])]) !!}        <a href="{!! $BASE_URL !!}/admin/stores/{!! $storeId !!}/daily-reports/{!! (int) $existing['id'] !!}"
           class="btn btn--ghost btn--sm ml-xs">{!! __('view') !!}</a>
    </div>
@endif

@if (!empty($errors))
    <div class="alert alert--danger mb-sm">
        <ul class="form-error-list">
            @foreach ($errors as $msg)
                <li>{{ $msg }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}">
    {!! csrf_field() !!}
    <!-- ── Date ── -->
    <div class="card card--mb">
        <div class="card-body">
            <div class="form-group form-group--date">
                <label class="form-label form-label--required">{!! __('date') !!}</label>
                <input type="date"
                       name="report_date"
                       class="form-control{!! isset($errors['report_date']) ? ' is-invalid' : '' !!}"
                       value="{!! drFormVal($old, 'report_date', $date) !!}"
                       {!! $isEdit ? 'readonly' : '' !!}                       required>
                @if (isset($errors['report_date']))
                    <span class="form-error">{{ $errors['report_date'] }}</span>
                @endif
            </div>
        </div>
    </div>

    <!-- ── Chiffres du jour ── -->
    <div class="card card--mb">
        <div class="card-header">{!! __('daily_report_form_hint') !!}</div>
        <div class="card-body form-stack">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">{!! __('daily_report_sales') !!} ({!! $currency !!})</label>
                    <input type="text" inputmode="numeric" name="sales_total" data-numeric-input
                           class="form-control{!! isset($errors['sales_total']) ? ' is-invalid' : '' !!}"
                           value="{!! drFormVal($old, 'sales_total', 0) !!}">
                    @if (isset($errors['sales_total']))
                        <span class="form-error">{{ $errors['sales_total'] }}</span>
                    @endif
                </div>

                <div class="form-group">
                    <label class="form-label">{!! __('daily_report_customers') !!}</label>
                    <input type="text" inputmode="numeric" name="customer_count" data-numeric-input
                           class="form-control{!! isset($errors['customer_count']) ? ' is-invalid' : '' !!}"
                           value="{!! drFormVal($old, 'customer_count', 0) !!}">
                    @if (isset($errors['customer_count']))
                        <span class="form-error">{{ $errors['customer_count'] }}</span>
                    @endif
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">{!! __('daily_report_labor') !!} ({!! $currency !!})</label>
                    <input type="text" inputmode="numeric" name="labor_cost" data-numeric-input
                           class="form-control{!! isset($errors['labor_cost']) ? ' is-invalid' : '' !!}"
                           value="{!! drFormVal($old, 'labor_cost', 0) !!}">
                    @if (isset($errors['labor_cost']))
                        <span class="form-error">{{ $errors['labor_cost'] }}</span>
                    @endif
                </div>

                <div class="form-group">
                    <label class="form-label">{!! __('daily_report_waste') !!} ({!! $currency !!})</label>
                    <input type="text" inputmode="numeric" name="waste_total" data-numeric-input
                           class="form-control{!! isset($errors['waste_total']) ? ' is-invalid' : '' !!}"
                           value="{!! drFormVal($old, 'waste_total', 0) !!}">
                    @if (isset($errors['waste_total']))
                        <span class="form-error">{{ $errors['waste_total'] }}</span>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <!-- ── Planning du lendemain ── -->
    <div class="card card--mb">
        <div class="card-header">{!! __('daily_report_shifts_of_day') !!}</div>
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
                                @if (in_array('gross_hours', $showCols, true))<td data-label="{{ __('gross_h_col') }}" class="td-right td-mono">{!! drFormH($row['gross_min']) !!}</td>@endif
                                @if (in_array('pause', $showCols, true))<td data-label="{{ __('pause') }}" class="td-right td-muted td-mono">{!! $row['pause_min'] > 0 ? $row['pause_min'] . ' min' : '—' !!}</td>@endif
                                @if (in_array('net_hours', $showCols, true))<td data-label="{{ __('net_h_col') }}" class="td-right td-mono">{!! drFormH($row['net_min']) !!}</td>@endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- ── Notes ── -->
    <div class="card card--mb">
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">{!! __('daily_report_notes') !!}</label>
                <textarea name="notes" class="form-control textarea--notes" rows="4"
                          placeholder="{!! __('daily_report_notes_placeholder') !!}">{!! drFormVal($old, 'notes') !!}</textarea>
            </div>
        </div>
    </div>

    <!-- ── Actions ── -->
    <div class="form-actions">
        <button type="submit" class="btn btn--primary">
            {!! $isEdit ? __('save') : __('daily_report_create') !!}        </button>
        <a href="{!! $backUrl !!}" class="btn btn--ghost">{!! __('cancel') !!}</a>
    </div>

</form>

@if ($numericInputJs = bundle_asset('daily-report', 'js/numeric-input.js'))
<script src="{!! $numericInputJs !!}"></script>
@endif
