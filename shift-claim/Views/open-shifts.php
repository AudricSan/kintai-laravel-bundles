<?php
use kintai\Contract\View\Components\Badge;
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\FilterBar;
use kintai\Contract\View\Components\Table;
use kintai\Contract\View\Components\Flash;

/**
 * Bourse aux shifts — partagée admin/manager (can_manage = true) et employé
 * (can_manage = false, ne voit que sa propre candidature).
 *
 * @var int        $highlight_id        Shift désigné par une notification (0 = aucun)
 * @var array[]    $shifts               Shifts ouverts (enrichis avec _claims en admin, _my_claim en employé)
 * @var array[]    $claims               Candidatures agrégées, filtrables par statut (admin uniquement)
 * @var array      $types_map            id → type
 * @var array      $stores_map           id → nom du store
 * @var array|null $users_map            id → nom (admin uniquement)
 * @var bool|null  $can_manage           null = contexte admin (toujours vrai)
 * @var string     $type_filter          filtre "type de shift" du tableau des shifts (admin uniquement)
 * @var string     $claim_status_filter  filtre "statut" du tableau des candidatures (admin uniquement)
 * @var string     $sort                 tri du tableau des shifts (admin uniquement)
 * @var string     $claim_sort           tri du tableau des candidatures (admin uniquement)
 */

$_canManage          = $can_manage ?? true;
$users_map           ??= [];
$types_map           ??= [];
$stores_map          ??= [];
$claims              ??= [];
$type_filter         ??= '';
$claim_status_filter ??= '';
$sort                ??= 'date_asc';
$claim_sort          ??= 'date_asc';

$claimStatusLabels = [
    'pending'   => __('pending'),
    'approved'  => __('approved'),
    'rejected'  => __('rejected'),
    'withdrawn' => __('shift_claim_withdrawn'),
];

if ($_canManage) {
    Flash::fromQuery('success', [
        'published'      => __('shift_claim_open_shift_published'),
        'unpublished'    => __('shift_claim_open_shift_unpublished'),
        'claim_approved' => __('shift_claim_approved'),
        'claim_rejected' => __('shift_claim_rejected'),
    ])->render();
    echo Flash::fromQuery('error', ['default' => __('error_generic')])->render();
} else {
    echo Flash::fromQuery('success', [
        'claimed' => __('shift_claim_shift_claimed'), 'withdrawn' => __('shift_claim_withdrawn'),
    ])->render();
    echo Flash::fromQuery('error', [
        'already_claimed' => __('shift_claim_already_claimed'), 'forbidden' => __('shift_claim_action_impossible'),
        'not_found' => __('shift_claim_error_open_shift_unavailable'), 'own_shift' => __('shift_claim_error_open_shift_own'),
    ])->render();
}
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('shift_claim_open_shifts') ?> <span class="page-count">(<?= count($shifts) ?>)</span></h2>
    <?php if ($_canManage): ?>
    <div class="page-header__actions">
        <?= Button::make('+ ' . __('shift_claim_publish_shift'))->primary()->link($BASE_URL . '/admin/open-shifts/publish')->render() ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($_canManage):
    $typeOptions = [];
    foreach ($types_map as $tid => $t) {
        $typeOptions[(string) $tid] = $t['name'] ?? ('#' . $tid);
    }
    echo FilterBar::make()
        ->select('type', __('type'), $typeOptions, $type_filter, __('all'))
        ->render();
endif; ?>

<div class="card card--mb">
<?php
$shiftsTable = Table::make()
    ->data($shifts)
    ->rowAttrs(fn($s) => (int) ($highlight_id ?? 0) > 0 && (int) ($s['id'] ?? 0) === (int) $highlight_id ? ['class' => 'tr--highlight'] : [])
    ->emptyMessage(__('shift_claim_no_open_shift'))
    ->column(__('date'), fn($s) => htmlspecialchars($s['shift_date'] ?? ''))
    ->column(__('schedule'), fn($s) => '<span class="td-mono">' . htmlspecialchars(substr($s['start_time'] ?? '', 0, 5)) . '–' . htmlspecialchars(substr($s['end_time'] ?? '', 0, 5)) . '</span>');
if ($_canManage) {
    $shiftsTable->currentSort($sort)->filters(['type' => $type_filter])
        ->sortable(__('store'), 'store', fn($s) => htmlspecialchars($stores_map[(int)($s['store_id'] ?? 0)] ?? ('ID ' . (int)($s['store_id'] ?? 0))));
} else {
    $shiftsTable->column(__('store'), fn($s) => htmlspecialchars($stores_map[(int)($s['store_id'] ?? 0)] ?? ('ID ' . (int)($s['store_id'] ?? 0))));
}
echo $shiftsTable
    ->column(__('type'), function($s) use ($types_map) {
        $t = $types_map[(int)($s['shift_type_id'] ?? 0)] ?? null;
        if (!$t) return '—';
        return '<span class="type-badge" data-color="' . htmlspecialchars($t['color'] ?? '#888') . '">'
            . htmlspecialchars($t['name'] ?? '') . '</span>';
    })
    ->column(__('shift_claim_note'), fn($s) => htmlspecialchars($s['open_shift_note'] ?? ''))
    ->column($_canManage ? __('shift_claim_claims') : __('shift_claim_my_status'), function($s) use ($_canManage) {
        if ($_canManage) {
            $claims = $s['_claims'] ?? [];
            $nPending = count(array_filter($claims, fn($c) => ($c['status'] ?? '') === 'pending'));
            if ($nPending > 0) {
                return Badge::make($nPending . ' ' . __('pending'))->warning()->render();
            }
            return '<span class="text-sm-muted">' . count($claims) . '</span>';
        }
        $status = $s['_my_claim']['status'] ?? null;
        return match ($status) {
            'pending'   => Badge::make(__('pending'))->warning()->render(),
            'approved'  => Badge::make(__('approved'))->success()->render(),
            'rejected'  => Badge::make(__('rejected'))->danger()->render(),
            'withdrawn' => Badge::make(__('shift_claim_withdrawn'))->neutral()->render(),
            default     => '<span class="text-sm-muted">—</span>',
        };
    })
    ->column(__('actions'), function($s) use ($BASE_URL, $_canManage) {
        $id = (int) $s['id'];
        if ($_canManage) {
            return '<div class="btn-group">'
                . '<a href="' . htmlspecialchars($BASE_URL . '/admin/shifts/' . $id . '/edit') . '" class="btn btn--ghost btn--sm">' . __('edit') . '</a>'
                . '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/shifts/' . $id . '/unpublish') . '" class="form-inline">'
                . csrf_field()
                . '<button type="submit" class="btn btn--ghost btn--sm" data-confirm="' . htmlspecialchars(__('confirm'), ENT_QUOTES) . '">' . __('shift_claim_close_bourse') . '</button>'
                . '</form></div>';
        }
        if (!empty($s['_mine'])) {
            return '<span class="text-sm-muted">' . __('shift_claim_open_shift_yours') . '</span>';
        }
        $status = $s['_my_claim']['status'] ?? null;
        if ($status === null || $status === 'withdrawn' || $status === 'rejected') {
            return '<form method="POST" action="' . $BASE_URL . '/employee/open-shifts/' . $id . '/claim" class="form-inline">' . csrf_field() . Button::make(__('shift_claim_claim_shift'))->primary()->sm()->submit()->render() . '</form>';
        }
        if ($status === 'pending') {
            return '<form method="POST" action="' . $BASE_URL . '/employee/open-shifts/' . $id . '/withdraw" class="form-inline" data-confirm="' . htmlspecialchars(__('shift_claim_confirm_cancel_request'), ENT_QUOTES) . '">' . csrf_field() . Button::make(__('shift_claim_withdraw'))->ghost()->sm()->submit()->render() . '</form>';
        }
        return '<span class="text-sm-muted">—</span>';
    })
    ->render()
?></div>

<?php if ($_canManage): ?>
<?= FilterBar::make()
    ->select('claim_status', __('status'), $claimStatusLabels, $claim_status_filter, __('all'))
    ->render()
?>
<div class="card">
    <div class="card-header">
        <span><?= __('shift_claim_pending_claims') ?> <span class="page-count">(<?= count($claims) ?>)</span></span>
    </div>
<?= Table::make()
    ->data($claims)
    ->rowAttrs(fn($c) => (int) ($highlight_id ?? 0) > 0 && (int) ($c['_shift']['id'] ?? 0) === (int) $highlight_id ? ['class' => 'tr--highlight'] : [])
    ->emptyMessage(__('shift_claim_no_pending_claims'))
    ->currentSort($claim_sort)
    ->filters(['claim_status' => $claim_status_filter])
    ->sortParam('claim_sort')
    ->column(__('date'), fn($c) => htmlspecialchars($c['_shift']['shift_date'] ?? ''))
    ->column(__('schedule'), fn($c) => '<span class="td-mono">' . htmlspecialchars(substr($c['_shift']['start_time'] ?? '', 0, 5)) . '–' . htmlspecialchars(substr($c['_shift']['end_time'] ?? '', 0, 5)) . '</span>')
    ->sortable(__('store'), 'store', fn($c) => htmlspecialchars($stores_map[(int)($c['_shift']['store_id'] ?? 0)] ?? '—'))
    ->column(__('employee'), fn($c) => htmlspecialchars($c['_user_name'] ?? '—'))
    ->column(__('shift_claim_note'), fn($c) => htmlspecialchars($c['note'] ?? ''))
    ->column(__('status'), function($c) use ($claimStatusLabels) {
        $st = $c['status'] ?? 'pending';
        return Badge::make($claimStatusLabels[$st] ?? $st)->status($st)->render();
    })
    ->column(__('shift_claim_claimed_at'), fn($c) => '<span class="td-date-muted">' . htmlspecialchars(substr($c['claimed_at'] ?? '', 0, 16)) . '</span>')
    ->column(__('actions'), function($c) use ($BASE_URL) {
        $id = (int) $c['id'];
        if (($c['status'] ?? 'pending') !== 'pending') {
            return '<span class="text-sm-muted">—</span>';
        }
        return '<div class="btn-group">'
            . '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/open-shifts/' . $id . '/approve') . '" class="form-inline">'
            . csrf_field()
            . Button::make(__('approve'))->success()->sm()->submit()->render()
            . '</form>'
            . '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/open-shifts/' . $id . '/reject') . '" class="form-inline">'
            . csrf_field()
            . Button::make(__('shift_claim_reject'))->danger()->sm()->submit()->render()
            . '</form></div>';
    })
    ->render()
?></div>
<?php endif; ?>

<script src="<?= $BASE_URL ?>/assets/js/modules/type-badges.js"></script>
