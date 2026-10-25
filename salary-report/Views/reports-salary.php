<?php

declare(strict_types=1);

use kintai\Contract\View\Components\Badge;
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Flash;
use kintai\Contract\View\Components\Modal;
use kintai\Contract\View\Components\Table;

/**
 * @var array       $store
 * @var array       $reports
 * @var string      $BASE_URL
 * @var array|null  $stores
 * @var array|null  $store_members
 * @var array|null  $store_members_by_store
 * @var int         $filter_store_id
 * @var string      $filter_year
 * @var string      $filter_month
 * @var string      $filter_person
 */

$store_members ??= [];
$store_members_by_store ??= [];

$stores ??= [];
$filter_store_id ??= 0;
$filter_year ??= '';
$filter_month ??= '';
$filter_person ??= '';

$storeNames = [];
foreach ($stores as $s) {
    $storeNames[(int) $s['id']] = $s['name'] ?? '#' . $s['id'];
}

$allMode = !empty($stores); // mode "tous les magasins"

if ($allMode) {
    $baseList = $BASE_URL . '/admin/reports/salary';
    $storeId = 0;
    // En vue "tous les magasins", la création n'est possible qu'une fois un
    // magasin précis choisi via le filtre — sans quoi on ne sait pas pour
    // quel magasin créer le rapport.
    $createBase = $filter_store_id > 0 ? $BASE_URL . '/admin/stores/' . $filter_store_id . '/reports/salary' : null;
} else {
    $storeId = (int) $store['id'];
    $storeNames[$storeId] = $store['name'] ?? '';
    $baseList = $BASE_URL . '/admin/stores/' . $storeId . '/reports/salary';
    $createBase = $baseList;
}

// devise/style par store (mode "tous les magasins" : les stores peuvent avoir des devises différentes)
$storeCurrencyMap = [];
if ($allMode) {
    foreach ($stores as $s) {
        $storeCurrencyMap[(int) $s['id']] = ['currency' => $s['currency'] ?? 'EUR', 'style' => store_currency_style($s)];
    }
} else {
    $storeCurrencyMap[$storeId] = ['currency' => $store['currency'] ?? 'EUR', 'style' => store_currency_style($store)];
}
$rowCurrency = function (array $r) use ($storeCurrencyMap): array {
    return $storeCurrencyMap[(int) ($r['store_id'] ?? 0)] ?? ['currency' => 'EUR', 'style' => 'kanji'];
};

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
    'created' => __('salary_report_sr_created'),
    'updated' => __('salary_report_sr_updated'),
    'deleted' => __('salary_report_sr_deleted'),
])->render();
?>

<div class="page-header">
    <h2 class="page-header__title">
        <?= __('salary_report_sr_title') ?><?php if (!$allMode): ?> — <?= htmlspecialchars($store['name'] ?? '') ?><?php endif; ?>
    </h2>
    <div class="page-header__actions">
        <?php if ($allMode):
            $exportQuery = array_filter([
                'store_id' => $filter_store_id ?: null,
                'year'     => $filter_year ?: null,
                'month'    => $filter_month ?: null,
                'person'   => $filter_person ?: null,
            ]);
            $exportQueryString = $exportQuery ? '?' . http_build_query($exportQuery) : '';
        ?>
        <?= Button::make('PDF')->ghost()->sm()->link($BASE_URL . '/admin/reports/salary/export/pdf' . $exportQueryString)->attrs(['target' => '_blank'])->render() ?>
        <?= Button::make('JSON')->ghost()->sm()->link($BASE_URL . '/admin/reports/salary/export/json' . $exportQueryString)->render() ?>
        <?php endif; ?>
        <?php if (!$allMode): ?>
        <details class="store-picker">
            <summary class="btn btn--primary">+ <?= __('salary_report_sr_new') ?></summary>
            <div class="store-picker__panel">
                <a class="store-picker__item" href="<?= htmlspecialchars($createBase . '/create') ?>">🏬 <?= __('salary_report_sr_new_store_wide') ?></a>
                <?php if ($store_members !== []): ?>
                    <div class="store-picker__title"><?= __('salary_report_sr_new_for_employee') ?></div>
                    <?php foreach ($store_members as $m): ?>
                        <?php $mName = trim(($m['last_name'] ?? '') . ' ' . ($m['first_name'] ?? '')) ?: ($m['display_name'] ?? $m['email'] ?? '#' . $m['id']); ?>
                        <a class="store-picker__item" href="<?= htmlspecialchars($createBase . '/create?user_id=' . (int) $m['id']) ?>"><?= htmlspecialchars($mName) ?></a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </details>
        <?php else: ?>
        <button type="button" class="btn btn--primary" data-on-click="srOpenCreateModal">+ <?= __('salary_report_sr_new') ?></button>
        <?php endif; ?>
        <?php if (!$allMode): ?>
        <?= Button::make('← ' . __('back'))->ghost()->sm()->link(back_url($BASE_URL . '/admin/stores/' . $storeId . '/edit'))->render() ?>
        <?php endif; ?>
    </div>
</div>

<!-- Barre de filtres -->
<div class="card card--filters mb-sm">
    <form method="GET" action="<?= $allMode ? $BASE_URL . '/admin/reports/salary' : $baseList ?>" class="filter-bar">
        <div class="shifts-filters__row">

            <?php if ($allMode): ?>
            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="sf-store"><?= __('store') ?></label>
                <select id="sf-store" name="store_id" class="form-control form-control-sm" data-submit-on-change>
                    <option value="0"><?= __('all_stores') ?></option>
                    <?php foreach ($stores as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $filter_store_id ? 'selected' : '' ?>><?= htmlspecialchars($s['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="sf-year"><?= __('year') ?></label>
                <select id="sf-year" name="year" class="form-control form-control-sm" data-submit-on-change>
                    <option value=""><?= __('salary_report_all_years') ?></option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= (string) $y === $filter_year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="sf-month"><?= __('month') ?></label>
                <select id="sf-month" name="month" class="form-control form-control-sm" data-submit-on-change>
                    <option value=""><?= __('salary_report_all_months') ?></option>
                    <?php foreach ($months as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $val === $filter_month ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="shifts-filters__group">
                <label class="shifts-filters__label" for="sf-person"><?= __('salary_report_sr_person_in_charge') ?></label>
                <input type="text" id="sf-person" name="person" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_person) ?>">
            </div>

            <div class="shifts-filters__actions">
                <?php if ($filter_year !== '' || $filter_month !== '' || $filter_person !== '' || $filter_store_id > 0): ?>
                    <a href="<?= $allMode ? $BASE_URL . '/admin/reports/salary' : $baseList ?>" class="btn btn--ghost btn--sm"><?= __('reset') ?></a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<div class="card">
<?php if (empty($reports)): ?>
    <div class="empty-state empty-state--salary"><?= __('salary_report_sr_empty') ?></div>
<?php else:
    $tbl = Table::make()
        ->data($reports)
        ->emptyMessage(__('salary_report_sr_empty'))
        ->rowUrl(fn($r) => $BASE_URL . '/admin/stores/' . (int) ($r['store_id'] ?? 0) . '/reports/salary/' . (int) $r['id']);
    if ($allMode):
        $tbl->column(__('store'), fn($r) => htmlspecialchars($storeNames[(int) ($r['store_id'] ?? 0)] ?? '—'));
    endif;
    echo $tbl
        ->currentSort($sort ?? 'target_month_desc')
        ->sortable(__('salary_report_sr_target_month'), 'target_month', fn($r) => htmlspecialchars($r['target_month'] ?? ''))
        ->column(__('employee_name'), fn($r) => !empty($r['user_id']) ? htmlspecialchars($r['employee_name'] ?? '—') : '<span class="text-muted">—</span>')
        ->sortable(__('salary_report_sr_person_in_charge'), 'person_in_charge', fn($r) => htmlspecialchars($r['person_in_charge'] ?? '—'))
        ->sortable(__('salary_report_sr_total_payment'), 'total_payment', function ($r) use ($rowCurrency) {
            $c = $rowCurrency($r);
            return '<span class="td-mono">' . format_currency((float) ($r['total_payment'] ?? 0), $c['currency'], $c['style']) . '</span>';
        }, 'td-right')
        ->sortable(__('salary_report_sr_net_payment'), 'net_payment', function ($r) use ($rowCurrency) {
            $c = $rowCurrency($r);
            return '<span class="td-mono">' . format_currency((float) ($r['net_payment'] ?? 0), $c['currency'], $c['style']) . '</span>';
        }, 'td-right')
        ->column(__('salary_report_sr_active_employees'), fn($r) => '<span class="td-mono">' . ((int) ($r['active_employees'] ?? 0)) . '</span>', 'td-right')
        ->column(__('actions'), function($r) use ($BASE_URL) {
            $rid = (int) $r['id'];
            $sid = (int) ($r['store_id'] ?? 0);
            $base = $BASE_URL . '/admin/stores/' . $sid . '/reports/salary/' . $rid;
            $html = '<div class="btn-group">';
            $html .= Button::make(__('view'))->ghost()->sm()->link($base)->render();
            $html .= Button::make(__('edit'))->ghost()->sm()->link($base . '/edit')->render();
            $html .= '<form method="POST" action="' . htmlspecialchars($base . '/delete') . '" class="form-inline" data-confirm="' . htmlspecialchars(__('salary_report_sr_confirm_delete'), ENT_QUOTES) . '">';
            $html .= csrf_field();
            $html .= Button::make(__('delete'))->danger()->sm()->submit()->render();
            $html .= '</form>';
            $html .= Button::make(__('salary_report_sr_pdf'))->ghost()->sm()->link($base . '/pdf')->attrs(['target' => '_blank'])->render();
            $html .= '</div>';
            return $html;
        })
        ->render();
endif; ?>
</div>

<?php if ($allMode):
ob_start();
?>
<div id="sr-step-choice">
    <p class="text-muted mb-sm"><?= __('salary_report_sr_new_choice_intro') ?></p>
    <div class="sr-choice-row">
        <button type="button" class="btn btn--outline sr-choice-btn" data-on-click="srShowStep" data-args='["store"]'>🏬<br><?= __('salary_report_sr_new_store_wide') ?></button>
        <button type="button" class="btn btn--outline sr-choice-btn" data-on-click="srShowStep" data-args='["employee"]'>👤<br><?= __('salary_report_sr_new_for_employee') ?></button>
    </div>
</div>
<div id="sr-step-store" class="hidden">
    <button type="button" class="btn btn--ghost btn--sm mb-sm" data-on-click="srShowStep" data-args='["choice"]'>← <?= __('back') ?></button>
    <input type="text" class="form-control form-control-sm mb-sm" placeholder="<?= __('search') ?>…" data-on-input="srFilterList" data-args='["@this", "sr-store-list"]'>
    <div id="sr-store-list" class="store-picker__panel store-picker__panel--static">
        <?php foreach ($stores as $s): ?>
            <a class="store-picker__item" href="<?= htmlspecialchars($BASE_URL . '/admin/stores/' . (int) $s['id'] . '/reports/salary/create') ?>"><?= htmlspecialchars($s['name'] ?? '') ?></a>
        <?php endforeach; ?>
    </div>
</div>
<div id="sr-step-employee" class="hidden">
    <button type="button" class="btn btn--ghost btn--sm mb-sm" data-on-click="srShowStep" data-args='["choice"]'>← <?= __('back') ?></button>
    <?php if ($store_members_by_store !== []): ?>
    <input type="text" class="form-control form-control-sm mb-sm" placeholder="<?= __('search') ?>…" data-on-input="srFilterList" data-args='["@this", "sr-employee-list"]'>
    <?php endif; ?>
    <div id="sr-employee-list" class="store-picker__panel store-picker__panel--static">
        <?php if ($store_members_by_store === []): ?>
            <p class="text-muted"><?= __('salary_report_sr_no_employees') ?></p>
        <?php endif; ?>
        <?php foreach ($store_members_by_store as $sid => $members): ?>
            <div class="store-picker__title"><?= htmlspecialchars($storeNames[$sid] ?? ('#' . $sid)) ?></div>
            <?php foreach ($members as $m): ?>
                <?php $mName = trim(($m['last_name'] ?? '') . ' ' . ($m['first_name'] ?? '')) ?: ($m['display_name'] ?? $m['email'] ?? '#' . $m['id']); ?>
                <a class="store-picker__item" href="<?= htmlspecialchars($BASE_URL . '/admin/stores/' . $sid . '/reports/salary/create?user_id=' . (int) $m['id']) ?>"><?= htmlspecialchars($mName) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php
$srModalBody = ob_get_clean();
echo Modal::make('sr-create-modal')->title(__('salary_report_sr_new'))->body($srModalBody)->render();
?>
<script nonce="<?= function_exists('csp_nonce') ? csp_nonce() : '' ?>">
function srShowStep(step) {
    document.getElementById('sr-step-choice').classList.toggle('hidden', step !== 'choice');
    document.getElementById('sr-step-store').classList.toggle('hidden', step !== 'store');
    document.getElementById('sr-step-employee').classList.toggle('hidden', step !== 'employee');
}
function srFilterList(input, panelId) {
    var term = input.value.trim().toLowerCase();
    var panel = document.getElementById(panelId);
    var nodes = Array.prototype.slice.call(panel.children);
    nodes.forEach(function (el) {
        if (el.classList.contains('store-picker__item')) {
            el.style.display = el.textContent.toLowerCase().indexOf(term) !== -1 ? '' : 'none';
        }
    });
    nodes.forEach(function (el, i) {
        if (!el.classList.contains('store-picker__title')) return;
        var hasVisible = false;
        for (var j = i + 1; j < nodes.length && !nodes[j].classList.contains('store-picker__title'); j++) {
            if (nodes[j].classList.contains('store-picker__item') && nodes[j].style.display !== 'none') {
                hasVisible = true;
                break;
            }
        }
        el.style.display = hasVisible ? '' : 'none';
    });
}
function srResetCreateModal() {
    srShowStep('choice');
    document.querySelectorAll('#sr-create-modal input[type="text"]').forEach(function (input) {
        input.value = '';
    });
    document.querySelectorAll('#sr-create-modal .store-picker__item, #sr-create-modal .store-picker__title').forEach(function (el) {
        el.style.display = '';
    });
}
// Ouvre la modale de création (remplace l'ancien onclick inline « reset puis openModal »).
function srOpenCreateModal() {
    srResetCreateModal();
    openModal('sr-create-modal');
}
</script>
<?php endif; ?>
