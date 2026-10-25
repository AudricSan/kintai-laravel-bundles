<?php
use kintai\Contract\View\Components\Badge;
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Table;

/** @var int      $highlight_id échange désigné par une notification (0 = aucun) */
/** @var array[]  $sent        swap_requests envoyées */
/** @var array[]  $received    swap_requests reçues */
/** @var array    $users_map   id → nom */
/** @var array    $shifts_map  id → shift */

function swapShiftLabel(array $shifts_map, int $shiftId): string {
    $s = $shifts_map[$shiftId] ?? null;
    if (!$s) return '#' . $shiftId;
    return htmlspecialchars(substr($s['start_time'] ?? '', 0, 5) . ' – ' . substr($s['end_time'] ?? '', 0, 5) . ' · ' . ($s['shift_date'] ?? ''));
}
?>
<?php if ($shiftSwapCss = bundle_asset('shift-swap', 'css/shift-swap.css')): ?>
<link rel="stylesheet" href="<?= $shiftSwapCss ?>">
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('shift_swap_swaps') ?></h2>
    <div class="page-header__actions">
        <?= Button::make('+ ' . __('new_request'))->primary()->sm()->link(route_url('employee.swaps.create'))->render() ?>
    </div>
</div>

<?= \kintai\Contract\View\Components\Flash::fromQuery('success', [
    'created' => __('shift_swap_swap_request_sent'), 'accepted' => __('shift_swap_swap_accepted_wait_manager'),
    'refused' => __('rejected'), 'cancelled' => __('shift_swap_timeoff_cancelled'),
])->render() ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert--danger mb-sm"><?= __('shift_swap_action_impossible') ?></div>
<?php endif; ?>

<!-- Demandes reçues (en attente de ma réponse) -->
<?php
$pendingReceived = array_values(array_filter(
    $received,
    fn($s) => ($s['status'] ?? '') === 'pending' && ($s['accepted_at'] ?? null) === null
));
?>
<?php if (!empty($pendingReceived)): ?>
<div class="swap-card card card--mb">
    <div class="card-header swap-card__header">
        <h3 class="swap-card__title"><?= __('shift_swap_received_requests') ?> — <?= __('pending') ?> (<?= count($pendingReceived) ?>)</h3>
    </div>
    <?= Table::make()
        ->data($pendingReceived)
        ->rowAttrs(fn($s) => (int) ($highlight_id ?? 0) > 0 && (int) ($s['id'] ?? 0) === (int) $highlight_id ? ['class' => 'tr--highlight'] : [])
        ->column(__('from'), fn($s) => htmlspecialchars($users_map[(int)($s['requester_id'] ?? 0)] ?? '—'))
        ->column(__('shift_swap_his_shift'), fn($s) => '<span class="text-sm">' . swapShiftLabel($shifts_map, (int)($s['requester_shift_id'] ?? 0)) . '</span>')
        ->column(__('shift_swap_my_shift'), fn($s) => '<span class="text-sm">' . swapShiftLabel($shifts_map, (int)($s['target_shift_id'] ?? 0)) . '</span>')
        ->column(__('reason'), fn($s) => '<span class="text-sm-muted text-italic">' . htmlspecialchars($s['reason'] ?? '') . '</span>')
        ->column(__('shift_swap_received_on'), fn($s) => '<span class="text-sm-muted">' . htmlspecialchars(substr($s['created_at'] ?? '', 0, 10)) . '</span>')
        ->column(__('actions'), function($s) use ($BASE_URL) {
            return '<form method="POST" action="' . $BASE_URL . '/employee/swaps/' . (int)$s['id'] . '/accept" class="form-inline">' . csrf_field() . Button::make(__('shift_swap_accept'))->primary()->sm()->submit()->render() . '</form>'
                . '<form method="POST" action="' . $BASE_URL . '/employee/swaps/' . (int)$s['id'] . '/refuse" class="form-inline" data-confirm="' . htmlspecialchars(__('confirm') . '?', ENT_QUOTES) . '">' . csrf_field() . Button::make(__('shift_swap_refuse'))->danger()->sm()->submit()->render() . '</form>';
        }, 'swap-actions')
        ->render() ?>
</div>
<?php endif; ?>

<!-- Mes demandes envoyées -->
<div class="card card--mb">
    <div class="card-header"><h3 class="swap-card__title"><?= __('shift_swap_sent_requests') ?></h3></div>
    <?php if (empty($sent)): ?>
        <div class="card-body"><p class="td-center td-muted"><?= __('shift_swap_no_swap_found') ?></p></div>
    <?php else:
        echo Table::make()
            ->data($sent)
            ->rowAttrs(fn($s) => (int) ($highlight_id ?? 0) > 0 && (int) ($s['id'] ?? 0) === (int) $highlight_id ? ['class' => 'tr--highlight'] : [])
            ->column(__('staff'), fn($s) => htmlspecialchars($users_map[(int)($s['target_user_id'] ?? 0)] ?? '—'))
            ->column(__('shift_swap_my_shift'), fn($s) => '<span class="text-sm">' . swapShiftLabel($shifts_map, (int)($s['requester_shift_id'] ?? 0)) . '</span>')
            ->column(__('shift_swap_his_shift'), fn($s) => '<span class="text-sm">' . swapShiftLabel($shifts_map, (int)($s['target_shift_id'] ?? 0)) . '</span>')
            ->column(__('reason'), fn($s) => '<span class="text-sm-muted text-italic">' . htmlspecialchars($s['reason'] ?? '') . '</span>')
            ->column(__('status'), function($s) {
                $status = $s['status'] ?? 'pending';
                $isPeerPending    = $status === 'pending' && ($s['accepted_at'] ?? null) === null;
                $isManagerPending = $status === 'pending' && ($s['accepted_at'] ?? null) !== null;
                if ($isPeerPending)    return Badge::make(__('shift_swap_wait_peer'))->warning()->render();
                if ($isManagerPending) return Badge::make(__('shift_swap_wait_manager'))->info()->render();
                return Badge::make(match($status) {
                    'pending'   => __('pending'),
                    'accepted'  => __('shift_swap_accepted'),
                    'refused'   => __('rejected'),
                    'cancelled' => __('cancelled'),
                    default     => htmlspecialchars($status),
                })->status($status)->render();
            })
            ->column(__('shift_swap_sent_on'), fn($s) => '<span class="text-sm-muted">' . htmlspecialchars(substr($s['created_at'] ?? '', 0, 10)) . '</span>')
            ->column('', function($s) use ($BASE_URL) {
                $status = $s['status'] ?? 'pending';
                $isPeerPending = $status === 'pending' && ($s['accepted_at'] ?? null) === null;
                if (!$isPeerPending) return '';
                return '<form method="POST" action="' . $BASE_URL . '/employee/swaps/' . (int)$s['id'] . '/cancel" class="form-inline" data-confirm="' . htmlspecialchars(__('shift_swap_confirm_cancel_request'), ENT_QUOTES) . '">'
                    . csrf_field() . Button::make(__('cancel'))->ghost()->sm()->submit()->render() . '</form>';
            })
            ->render();
    endif; ?>
</div>

<!-- Toutes les demandes reçues (historique) -->
<?php if (!empty($received)): ?>
<div class="card">
    <div class="card-header"><h3 class="swap-card__title"><?= __('shift_swap_received_requests') ?> — <?= __('shift_swap_history') ?></h3></div>
    <?= Table::make()
        ->data($received)
        ->rowAttrs(fn($s) => (int) ($highlight_id ?? 0) > 0 && (int) ($s['id'] ?? 0) === (int) $highlight_id ? ['class' => 'tr--highlight'] : [])
        ->column(__('from'), fn($s) => htmlspecialchars($users_map[(int)($s['requester_id'] ?? 0)] ?? '—'))
        ->column(__('shift_swap_his_shift'), fn($s) => '<span class="text-sm">' . swapShiftLabel($shifts_map, (int)($s['requester_shift_id'] ?? 0)) . '</span>')
        ->column(__('shift_swap_my_shift'), fn($s) => '<span class="text-sm">' . swapShiftLabel($shifts_map, (int)($s['target_shift_id'] ?? 0)) . '</span>')
        ->column(__('status'), function($s) {
            $status = $s['status'] ?? 'pending';
            $isPeerPending    = $status === 'pending' && ($s['accepted_at'] ?? null) === null;
            $isManagerPending = $status === 'pending' && ($s['accepted_at'] ?? null) !== null;
            if ($isPeerPending)    return Badge::make(__('pending'))->warning()->render();
            if ($isManagerPending) return Badge::make(__('shift_swap_wait_manager'))->info()->render();
            return Badge::make(match($status) {
                'pending'   => __('pending'),
                'accepted'  => __('shift_swap_accepted'),
                'refused'   => __('rejected'),
                'cancelled' => __('cancelled'),
                default     => htmlspecialchars($status),
            })->status($status)->render();
        })
        ->column(__('date'), fn($s) => '<span class="text-sm-muted">' . htmlspecialchars(substr($s['created_at'] ?? '', 0, 10)) . '</span>')
        ->render() ?>
</div>
<?php endif; ?>
