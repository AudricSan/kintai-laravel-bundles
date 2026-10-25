@php
use kintai\Contract\View\Components\Badge;
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Flash;
use kintai\Contract\View\Components\Table;

/**
 * @var array      $store
 * @var array      $reports
 * @var array|null $stores
 * @var int        $filter_store_id
 * @var string     $filter_year
 * @var string     $filter_month
 */

$stores ??= [];
$filter_store_id ??= 0;
$filter_year ??= '';
$filter_month ??= '';

$storeNames = [];
foreach ($stores as $s) {
    $storeNames[(int) $s['id']] = $s['name'] ?? '#' . $s['id'];
}

$allMode = !empty($stores); // mode "tous les magasins" — voir AdminHiringReportController::allHiringReports()

if ($allMode) {
    $baseList = $BASE_URL . '/admin/reports/hiring';
    $storeId = 0;
    // En vue "tous les magasins", la création n'est possible qu'une fois un
    // magasin précis choisi via le filtre — sans quoi on ne sait pas pour
    // quel magasin créer le rapport.
    $createBase = $filter_store_id > 0 ? $BASE_URL . '/admin/stores/' . $filter_store_id . '/reports/hiring' : null;
} else {
    $storeId = (int) $store['id'];
    $storeNames[$storeId] = $store['name'] ?? '';
    $baseList = $BASE_URL . '/admin/stores/' . $storeId . '/reports/hiring';
    $createBase = $baseList;
}

$currentYear = (int) date('Y');
$years = range(2020, $currentYear + 1);
$months = [
    '01' => __('January'), '02' => __('February'), '03' => __('March'),
    '04' => __('April'), '05' => __('May'), '06' => __('June'),
    '07' => __('July'), '08' => __('August'), '09' => __('September'),
    '10' => __('October'), '11' => __('November'), '12' => __('December'),
];

echo Flash::fromQuery('success', [
    'created' => __('operation_success'),
    'updated' => __('operation_success'),
    'deleted' => __('operation_success'),
])->render();
@endphp
<div class="page-header">
    <h2 class="page-header__title">{!! __('hiring_report_hiring_reports') !!} <span class="page-count">({!! count($reports) !!})</span></h2>
    <div class="page-header__actions">
        @if ($createBase !== null)
        {!! Button::make('+ ' . __('hiring_report_new_hiring_report'))->primary()->link($createBase . '/create')->render() !!}        @elseif ($allMode)
        <details class="store-picker">
            <summary class="btn btn--primary">+ {!! __('hiring_report_new_hiring_report') !!}</summary>
            <div class="store-picker__panel">
                <div class="store-picker__title">{!! __('hiring_report_store_picker_choose') !!}</div>
                @foreach ($stores as $s)
                    <a class="store-picker__item" href="{{ $BASE_URL . '/admin/stores/' . (int) $s['id'] . '/reports/hiring/create' }}">{{ $s['name'] ?? '' }}</a>
                @endforeach
            </div>
        </details>
        @endif
        @if (!$allMode)
        {!! Button::make(__('back'))->ghost()->link(back_url($BASE_URL . '/admin/stores/' . $storeId . '/edit'))->render() !!}        @endif
    </div>
</div>

<!-- Barre de filtres -->
<div class="card card--filters mb-sm">
    <form method="GET" action="{!! $allMode ? $BASE_URL . '/admin/reports/hiring' : $baseList !!}" class="filter-bar">
        <div class="shifts-filters__row">

            @if ($allMode)
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="hf-store">{!! __('store') !!}</label>
                <select id="hf-store" name="store_id" class="form-control form-control--sm" data-submit-on-change>
                    <option value="0">{!! __('all_stores') !!}</option>
                    @foreach ($stores as $s)
                        <option value="{!! (int) $s['id'] !!}" {!! (int) $s['id'] === $filter_store_id ? 'selected' : '' !!}>{{ $s['name'] ?? '' }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="hf-year">{!! __('year') !!}</label>
                <select id="hf-year" name="year" class="form-control form-control--sm" data-submit-on-change>
                    <option value="">{!! __('hiring_report_all_years') !!}</option>
                    @foreach ($years as $y)
                        <option value="{!! $y !!}" {!! (string) $y === $filter_year ? 'selected' : '' !!}>{!! $y !!}</option>
                    @endforeach
                </select>
            </div>

            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="hf-month">{!! __('month') !!}</label>
                <select id="hf-month" name="month" class="form-control form-control--sm" data-submit-on-change>
                    <option value="">{!! __('hiring_report_all_months') !!}</option>
                    @foreach ($months as $val => $label)
                        <option value="{!! $val !!}" {!! $val === $filter_month ? 'selected' : '' !!}>{!! $label !!}</option>
                    @endforeach
                </select>
            </div>

            <div class="shifts-filters__actions">
                @if ($filter_year !== '' || $filter_month !== '' || $filter_store_id > 0)
                    <a href="{!! $allMode ? $BASE_URL . '/admin/reports/hiring' : $baseList !!}" class="btn btn--ghost btn--sm">{!! __('reset') !!}</a>
                @endif
            </div>
        </div>
    </form>
</div>

<div class="card">
@if (empty($reports))
    <div class="card-body">
        <p class="text-muted">{!! __('hiring_report_no_hiring_reports') !!}</p>
    </div>
@php else:
    $tbl = Table::make()
        ->data($reports)
        ->emptyMessage(__('none'))
        ->rowUrl(fn($r) => $BASE_URL . '/admin/stores/' . (int) ($r['store_id'] ?? 0) . '/reports/hiring/' . (int) $r['id']);
    if ($allMode):
        $tbl->column(__('store'), fn($r) => htmlspecialchars($storeNames[(int) ($r['store_id'] ?? 0)] ?? '—'));
    endif;
    echo $tbl
        ->column(__('employee_number'), fn($r) => '<code>' . htmlspecialchars($r['employee_number'] ?? '') . '</code>')
        ->column(__('employee_name'), fn($r) => '<strong>' . htmlspecialchars($r['employee_name'] ?? '') . '</strong>')
        ->column(__('hiring_report_hire_date'), fn($r) => htmlspecialchars($r['hire_date'] ?? '—'))
        ->column(__('hiring_report_hired_by'), fn($r) => htmlspecialchars($r['hired_by'] ?? '—'))
        ->column(__('created_at'), fn($r) => '<span class="td-date-muted">' . htmlspecialchars(substr($r['created_at'] ?? '', 0, 10)) . '</span>')
        ->column(__('actions'), function($r) use ($BASE_URL, $auth_user) {
            $rid = (int) $r['id'];
            $sid = (int) ($r['store_id'] ?? 0);
            $base = $BASE_URL . '/admin/stores/' . $sid . '/reports/hiring/' . $rid;
            $html = '<div class="btn-group">';
            $html .= Button::make(__('view'))->ghost()->sm()->link($base)->render();
            $html .= Button::make(__('edit'))->ghost()->sm()->link($base . '/edit')->render();
            if (!empty($auth_user['is_admin'])) {
                $html .= '<form method="POST" action="' . htmlspecialchars($base . '/delete') . '" class="form-inline" data-confirm="' . htmlspecialchars(__('hiring_report_confirm_delete_hiring_report'), ENT_QUOTES) . '">';
                $html .= csrf_field();
                $html .= Button::make(__('delete'))->danger()->sm()->submit()->render();
                $html .= '</form>';
            }
            $html .= Button::make('PDF')->ghost()->sm()->link($base . '/pdf')->attrs(['target' => '_blank'])->render();
            $html .= '</div>';
            return $html;
        })
        ->render();
endif; @endphp
</div>
