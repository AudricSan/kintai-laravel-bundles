<?php
use kintai\Contract\View\Components\Badge;
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Card;
use kintai\Contract\View\Components\Table;

/** @var array[] $requests   timeoff_requests */
/** @var int     $highlight_id demande désignée par une notification (0 = aucune) */
/** @var string  $today      Y-m-d */
/** @var array[] $stores     Magasins de l'employé [['id' => int, 'name' => string], ...] */

$today = date('Y-m-d');

$typeLabels = [
    'vacation' => __('vacation'), 'sick' => __('sick'), 'personal' => __('personal'),
    'unpaid' => __('unpaid'), 'other' => __('other'),
];
?>
<?php if ($timeoffCss = bundle_asset('timeoff', 'css/timeoff.css')): ?>
<link rel="stylesheet" href="<?= $timeoffCss ?>">
<?php endif; ?>

<div class="page-header">
    <h2 class="page-header__title"><?= __('timeoff_my_timeoff') ?></h2>
</div>

<?= \kintai\Contract\View\Components\Flash::fromQuery('success', [
    'created' => __('timeoff_request_sent'), 'cancelled' => __('timeoff_cancelled'),
])->render() ?>
<?= \kintai\Contract\View\Components\Flash::fromQuery('error', ['not_pending' => __('timeoff_error_not_pending'), 'invalid_state' => __('timeoff_action_impossible')])->render() ?>

<!-- Formulaire création -->
<?php
ob_start();
?>
<form method="POST" action="<?= route_url('employee.timeoff') ?>" class="timeoff-form">
    <?= csrf_field() ?>
    <?php if (count($stores ?? []) > 1): ?>
        <div class="timeoff-form__date">
            <label class="form-label" for="to_store"><?= __('store') ?></label>
            <select id="to_store" name="store_id" class="form-control">
                <?php foreach ($stores as $_s): ?>
                    <option value="<?= (int) $_s['id'] ?>"><?= htmlspecialchars((string) $_s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <div class="timeoff-form__date">
        <label class="form-label" for="to_type"><?= __('type') ?></label>
        <select id="to_type" name="type" class="form-control" required>
            <?php foreach ($typeLabels as $val => $label): ?>
                <option value="<?= $val ?>"><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="timeoff-form__short">
        <label class="form-label" for="to_start"><?= __('start') ?></label>
        <input id="to_start" type="date" name="start_date" class="form-control" required value="<?= $today ?>">
    </div>
    <div class="timeoff-form__short">
        <label class="form-label" for="to_end"><?= __('end') ?></label>
        <input id="to_end" type="date" name="end_date" class="form-control" value="<?= $today ?>">
    </div>
    <div class="timeoff-form__grow">
        <label class="form-label" for="to_reason"><?= __('reason') ?> (<?= __('timeoff_optional') ?? 'optionnel' ?>)</label>
        <input id="to_reason" type="text" name="reason" class="form-control" placeholder="<?= __('timeoff_reason_placeholder') ?>">
    </div>
    <div>
        <?= Button::make(__('send') ?? 'Envoyer')->primary()->submit()->render() ?>
    </div>
</form>
<?php echo Card::make(__('new_request'))->body(ob_get_clean())->render(); ?>

<!-- Liste des demandes -->
<div class="card">
    <?php if (empty($requests)):
        echo '<p class="empty-state">' . __('timeoff_no_pending_timeoff') . '</p>';
    else: ?>
    <?= Table::make()
        ->data($requests)
        ->rowAttrs(fn($r) => (int) ($highlight_id ?? 0) > 0 && (int) ($r['id'] ?? 0) === (int) $highlight_id ? ['class' => 'tr--highlight'] : [])
        ->column(__('type'), fn($r) => htmlspecialchars(($typeLabels[$r['type'] ?? ''] ?? ($r['type'] ?? '—'))))
        ->column(__('start'), fn($r) => htmlspecialchars($r['start_date'] ?? '—'))
        ->column(__('end'), fn($r) => htmlspecialchars($r['end_date'] ?? '—'))
        ->column(__('reason'), fn($r) => '<span class="td-muted text-italic">' . htmlspecialchars($r['reason'] ?? '') . '</span>')
        ->column(__('status'), function($r) {
            $status = $r['status'] ?? 'pending';
            return Badge::make(match($status) {
                'pending'   => __('pending'),
                'approved'  => __('approved'),
                'refused'   => __('rejected'),
                'cancelled' => __('cancelled'),
                default     => htmlspecialchars($status),
            })->status($status)->render();
        })
        ->column(__('timeoff_sent_at'), fn($r) => '<span class="text-sm-muted">' . htmlspecialchars(substr($r['created_at'] ?? '', 0, 10)) . '</span>')
        ->column('', function($r) use ($BASE_URL) {
            $status = $r['status'] ?? 'pending';
            if ($status !== 'pending') return '';
            return '<form method="POST" action="' . $BASE_URL . '/employee/timeoff/' . (int) $r['id'] . '/cancel" class="form-inline" data-confirm="' . htmlspecialchars(__('timeoff_confirm_cancel_request'), ENT_QUOTES) . '">'
                . csrf_field()
                . Button::make(__('cancel'))->ghost()->sm()->submit()->render()
                . '</form>';
        })
        ->render() ?>
    <?php endif; ?>
</div>
