<?php
use kintai\Contract\View\Components\Badge;
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\FilterBar;
use kintai\Contract\View\Components\Flash;
use kintai\Contract\View\Components\Table;

/** @var array  $requests */
/** @var int    $highlight_id demande désignée par une notification (0 = aucune) */
/** @var array  $users_map */
/** @var array  $stores_map */
/** @var string $sort */
/** @var string $type_filter */
/** @var string $status_filter */

$users_map     ??= [];
$stores_map    ??= [];
$sort          ??= 'date_desc';
$type_filter   ??= '';
$status_filter ??= '';

$typeLabels = [
    'vacation' => __('vacation'),
    'sick'     => __('sick'),
    'personal' => __('personal'),
    'unpaid'   => __('unpaid'),
    'other'    => __('other'),
];

$statusLabels = [
    'pending'   => __('pending'),
    'approved'  => __('approved'),
    'refused'   => __('rejected'),
    'cancelled' => __('cancelled'),
];

echo Flash::fromQuery('success', [
    'approved' => __('approved'),
    'refused'  => __('rejected'),
    'created'  => __('timeoff_created_admin'),
    'deleted'  => __('timeoff_deleted'),
])->render();
echo Flash::fromQuery('error', ['invalid_state' => __('timeoff_action_impossible')])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('timeoff_requests') ?> <span class="page-count">(<?= count($requests) ?>)</span></h2>
    <div class="page-header__actions">
        <?= Button::make('+ ' . __('timeoff_new_timeoff'))->primary()->link($BASE_URL . '/admin/timeoff/create')->render() ?>
    </div>
</div>

<?= FilterBar::make()
    ->select('type', __('type'), $typeLabels, $type_filter, __('all'))
    ->select('status', __('status'), $statusLabels, $status_filter, __('all'))
    ->render()
?>

<div class="card card--filters mb-sm">
<?= Table::make()
    ->data($requests)
    ->rowAttrs(fn($r) => (int) ($highlight_id ?? 0) > 0 && (int) ($r['id'] ?? 0) === (int) $highlight_id ? ['class' => 'tr--highlight'] : [])
    ->emptyMessage(__('timeoff_no_found'))
    ->currentSort($sort)
    ->filters(['type' => $type_filter, 'status' => $status_filter])
    ->column('#', fn($r) => (string) (int) $r['id'])
    ->sortable(__('store'), 'store', fn($r) => htmlspecialchars($stores_map[(int)($r['store_id'] ?? 0)] ?? ('ID ' . (int)($r['store_id'] ?? 0))))
    ->sortable(__('user'), 'user', fn($r) =>
        '<a href="' . htmlspecialchars($BASE_URL . '/admin/users/' . (int)($r['user_id'] ?? 0) . '/edit') . '">'
        . htmlspecialchars($users_map[(int)($r['user_id'] ?? 0)] ?? ('ID ' . (int)($r['user_id'] ?? 0))) . '</a>'
    )
    ->sortable(__('type'), 'type', fn($r) => htmlspecialchars($r['type'] ?? ''))
    ->sortable(__('from'), 'date', fn($r) => htmlspecialchars($r['start_date'] ?? ''))
    ->column(__('to'), fn($r) => htmlspecialchars($r['end_date'] ?? ''))
    ->sortable(__('status'), 'status', function($r) use ($statusLabels, $users_map) {
        $st = $r['status'] ?? 'pending';
        $html = Badge::make($statusLabels[$st] ?? $st)->status($st)->render();
        // Qui a traité la demande, et quand (vide pour une demande traitée avant cet enregistrement).
        $by = (int) ($r['processed_by'] ?? 0);
        if ($by > 0 && !empty($r['processed_at'])) {
            $html .= '<div class="text-sm text-muted">' . htmlspecialchars(__('timeoff_processed_by', [
                'name' => $users_map[$by] ?? ('ID ' . $by),
                'date' => substr((string) $r['processed_at'], 0, 10),
            ])) . '</div>';
        }
        return $html;
    })
    ->column(__('reason'), fn($r) => '<span class="td-reason">' . htmlspecialchars($r['reason'] ?? '—') . '</span>')
    ->column(__('actions'), function($r) use ($BASE_URL) {
        $status = $r['status'] ?? 'pending';
        $id = (int) $r['id'];
        $html = '<div class="btn-group">';
        if ($status === 'pending') {
            $html .= '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/timeoff/' . $id . '/approve') . '" class="form-inline">' . csrf_field()
                . Button::make(__('approve'))->success()->sm()->submit()->render() . '</form>';
            $html .= '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/timeoff/' . $id . '/refuse') . '" class="form-inline">' . csrf_field()
                . Button::make(__('timeoff_reject'))->danger()->sm()->submit()->render() . '</form>';
        }
        $html .= '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/timeoff/' . $id . '/delete') . '" class="form-inline" data-confirm="' . htmlspecialchars(__('timeoff_confirm_delete_timeoff'), ENT_QUOTES) . '">' . csrf_field()
            . Button::make(__('delete'))->danger()->sm()->submit()->render() . '</form>';
        $html .= '</div>';
        return $html;
    })
    ->render()
?></div>
