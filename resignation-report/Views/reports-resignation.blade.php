@php
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Flash;
use kintai\Contract\View\Components\Table;

/**
 * @var array       $store
 * @var array       $reports
 * @var array|null  $stores
 * @var int         $filter_store_id
 * @var string      $filter_year
 * @var string      $filter_month
 * @var string      $filter_person
 */

$stores ??= [];
$filter_store_id ??= 0;
$filter_year ??= '';
$filter_month ??= '';
$filter_person ??= '';

// Construire une map store_id → nom
$storeNames = [];
foreach ($stores as $s) {
    $storeNames[(int) $s['id']] = $s['name'] ?? '#' . $s['id'];
}

$allMode = !empty($stores); // mode "tous les magasins"

if ($allMode) {
    $baseList = $BASE_URL . '/admin/reports/resignation';
    $storeId = 0;
    // En vue "tous les magasins", la création n'est possible qu'une fois un
    // magasin précis choisi via le filtre — sans quoi on ne sait pas pour
    // quel magasin créer le rapport.
    $createBase = $filter_store_id > 0 ? $BASE_URL . '/admin/stores/' . $filter_store_id . '/reports/resignation' : null;
} else {
    $storeId = (int) $store['id'];
    $storeNames[$storeId] = $store['name'] ?? '';
    $baseList = $BASE_URL . '/admin/stores/' . $storeId . '/reports/resignation';
    $createBase = $baseList;
}

// Générer la liste des années disponibles (2020 à année courante +1)
$currentYear = (int) date('Y');
$years = range(2020, $currentYear + 1);
$months = [
    '01' => __('January'), '02' => __('February'), '03' => __('March'),
    '04' => __('April'), '05' => __('May'), '06' => __('June'),
    '07' => __('July'), '08' => __('August'), '09' => __('September'),
    '10' => __('October'), '11' => __('November'), '12' => __('December'),
];

echo Flash::fromQuery('success', [
    'created'      => __('operation_success'),
    'updated'      => __('operation_success'),
    'deleted'      => __('operation_success'),
    'reactivated'  => __('operation_success'),
    'user_deleted' => __('resignation_report_user_deleted_permanently'),
])->render();
@endphp

<div class="page-header">
    <h2 class="page-header__title">
        {!! __('resignation_report_resignation_reports') !!} <span class="page-count">({!! count($reports) !!})</span>
    </h2>
    <div class="page-header__actions">
        @php if ($allMode):
            $exportQuery = array_filter([
                'store_id' => $filter_store_id ?: null,
                'year'     => $filter_year ?: null,
                'month'    => $filter_month ?: null,
                'person'   => $filter_person ?: null,
            ]);
            $exportQueryString = $exportQuery ? '?' . http_build_query($exportQuery) : '';
        @endphp
        {!! Button::make('PDF')->ghost()->sm()->link($BASE_URL . '/admin/reports/resignation/export/pdf' . $exportQueryString)->attrs(['target' => '_blank'])->render() !!}        {!! Button::make('JSON')->ghost()->sm()->link($BASE_URL . '/admin/reports/resignation/export/json' . $exportQueryString)->render() !!}        @endif
        @if ($createBase !== null)
        {!! Button::make('+ ' . __('resignation_report_new_resignation_report'))->primary()->link($createBase . '/create')->render() !!}        @elseif ($allMode)
        <details class="store-picker">
            <summary class="btn btn--primary">+ {!! __('resignation_report_new_resignation_report') !!}</summary>
            <div class="store-picker__panel">
                <div class="store-picker__title">{!! __('resignation_report_store_picker_choose') !!}</div>
                @foreach ($stores as $s)
                    <a class="store-picker__item" href="{{ $BASE_URL . '/admin/stores/' . (int) $s['id'] . '/reports/resignation/create' }}">{{ $s['name'] ?? '' }}</a>
                @endforeach
            </div>
        </details>
        @endif
        @if (!$allMode)
        {!! Button::make('← ' . __('back'))->ghost()->sm()->link(back_url($BASE_URL . '/admin/stores/' . $storeId . '/edit'))->render() !!}        @endif
    </div>
</div>

<!-- Barre de filtres -->
<div class="card card--filters mb-sm">
    <form method="GET" action="{!! $allMode ? $BASE_URL . '/admin/reports/resignation' : $baseList !!}" class="filter-bar">
        <div class="shifts-filters__row">

            @if ($allMode)
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="rf-store">{!! __('store') !!}</label>
                <select id="rf-store" name="store_id" class="form-control form-control-sm" data-submit-on-change>
                    <option value="0">{!! __('all_stores') !!}</option>
                    @foreach ($stores as $s)
                        <option value="{!! (int) $s['id'] !!}" {!! (int) $s['id'] === $filter_store_id ? 'selected' : '' !!}>{{ $s['name'] ?? '' }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="rf-year">{!! __('year') !!}</label>
                <select id="rf-year" name="year" class="form-control form-control-sm" data-submit-on-change>
                    <option value="">{!! __('resignation_report_all_years') !!}</option>
                    @foreach ($years as $y)
                        <option value="{!! $y !!}" {!! (string) $y === $filter_year ? 'selected' : '' !!}>{!! $y !!}</option>
                    @endforeach
                </select>
            </div>

            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="rf-month">{!! __('month') !!}</label>
                <select id="rf-month" name="month" class="form-control form-control-sm" data-submit-on-change>
                    <option value="">{!! __('resignation_report_all_months') !!}</option>
                    @foreach ($months as $val => $label)
                        <option value="{!! $val !!}" {!! $val === $filter_month ? 'selected' : '' !!}>{!! $label !!}</option>
                    @endforeach
                </select>
            </div>

            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="rf-person">{!! __('resignation_report_person_in_charge') !!}</label>
                <input type="text" id="rf-person" name="person" class="form-control form-control-sm" value="{{ $filter_person }}">
            </div>

            <div class="shifts-filters__actions">
                @if ($filter_year !== '' || $filter_month !== '' || $filter_person !== '' || $filter_store_id > 0)
                    <a href="{!! $allMode ? $BASE_URL . '/admin/reports/resignation' : $baseList !!}" class="btn btn--ghost btn--sm">{!! __('reset') !!}</a>
                @endif
            </div>
        </div>
    </form>
</div>

<div class="card">
@php
$tbl = Table::make()
    ->data($reports)
    ->emptyMessage(__('none'))
    ->rowUrl(fn($r) => $BASE_URL . '/admin/stores/' . (int) ($r['store_id'] ?? 0) . '/reports/resignation/' . (int) $r['id']);
if ($allMode):
    $tbl->column(__('store'), fn($r) => htmlspecialchars($storeNames[(int) ($r['store_id'] ?? 0)] ?? '—'));
endif;
echo $tbl
    ->column(__('employee_number'), fn($r) => htmlspecialchars($r['employee_number'] ?? '—'))
    ->column(__('employee_name'), fn($r) => htmlspecialchars($r['employee_name'] ?? '—'))
    ->column(__('resignation_report_resignation_date'), fn($r) => htmlspecialchars($r['resignation_date'] ?? '—'))
    ->column(__('resignation_report_person_in_charge'), fn($r) => htmlspecialchars($r['person_in_charge'] ?? '—'))
    ->column(__('actions'), function($r) use ($BASE_URL) {
        $rid = (int) $r['id'];
        $sid = (int) ($r['store_id'] ?? 0);
        $baseStore = $BASE_URL . '/admin/stores/' . $sid . '/reports/resignation';
        $html = '<div class="btn-group">';
        $html .= '<a href="' . $baseStore . '/' . $rid . '" class="btn btn--ghost btn--sm">' . __('details') . '</a>';
        $html .= '<a href="' . $baseStore . '/' . $rid . '/edit" class="btn btn--ghost btn--sm">' . __('edit') . '</a>';
        if (!empty($r['user_id'])) {
            $html .= '<form method="POST" action="' . $baseStore . '/' . $rid . '/reactivate" class="form-inline">' . csrf_field()
                . '<button type="submit" class="btn btn--warning btn--sm" data-confirm="' . htmlspecialchars(__('resignation_report_confirm_reactivate'), ENT_QUOTES) . '">' . __('resignation_report_reactivate') . '</button></form>';
            $html .= '<button type="button" class="btn btn--danger btn--sm js-rr-delete"'
                . ' data-reactivate-url="' . htmlspecialchars($baseStore . '/' . $rid . '/delete') . '"'
                . ' data-delete-url="' . htmlspecialchars($baseStore . '/' . $rid . '/delete-permanently') . '"'
                . ' data-employee-name="' . htmlspecialchars($r['employee_name'] ?? '') . '">' . __('delete') . '</button>';
        } else {
            $html .= '<form method="POST" action="' . $baseStore . '/' . $rid . '/delete" class="form-inline" data-confirm="' . htmlspecialchars(__('resignation_report_confirm_delete_resignation_report'), ENT_QUOTES) . '">' . csrf_field()
                . '<button type="submit" class="btn btn--danger btn--sm">' . __('delete') . '</button></form>';
        }
        $html .= Button::make('PDF')->ghost()->sm()->link($baseStore . '/' . $rid . '/pdf')->attrs(['target' => '_blank'])->render();
        $html .= '</div>';
        return $html;
    })
    ->render();
@endphp</div>

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
