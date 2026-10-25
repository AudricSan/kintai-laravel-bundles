<?php
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Table;
use kintai\Contract\View\Components\Flash;

/** @var array[] $shifts     shifts assignés, à venir, non publiés */
/** @var array   $users_map  uid → nom */
/** @var array   $stores_map id → nom */

$users_map  ??= [];
$stores_map ??= [];

echo Flash::fromQuery('error', ['default' => __('error_generic')])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('shift_claim_select_shift_to_publish') ?></h2>
    <div class="page-header__actions">
        <?= Button::make('+ ' . __('shift_claim_create_new_shift'))->primary()->link($BASE_URL . '/admin/shifts/create?is_open=1&redirect_to=' . urlencode($BASE_URL . '/admin/open-shifts'))->render() ?>
        <a href="<?= back_url($BASE_URL . '/admin/open-shifts') ?>" class="btn btn--ghost btn--sm">← <?= __('back') ?></a>
    </div>
</div>

<p class="text-muted text-sm mb-sm"><?= __('shift_claim_select_existing_shift_hint') ?></p>

<div class="card">
<?= Table::make()
    ->data($shifts)
    ->emptyMessage(__('shift_claim_no_publishable_shift'))
    ->column(__('date'), fn($s) => htmlspecialchars($s['shift_date'] ?? ''))
    ->column(__('schedule'), fn($s) => '<span class="td-mono">' . htmlspecialchars(substr($s['start_time'] ?? '', 0, 5)) . '–' . htmlspecialchars(substr($s['end_time'] ?? '', 0, 5)) . '</span>')
    ->column(__('store'), fn($s) => htmlspecialchars($stores_map[(int)($s['store_id'] ?? 0)] ?? ('ID ' . (int)($s['store_id'] ?? 0))))
    ->column(__('user'), fn($s) => htmlspecialchars($users_map[(int)($s['user_id'] ?? 0)] ?? ('ID ' . (int)($s['user_id'] ?? 0))))
    ->column(__('actions'), function($s) use ($BASE_URL) {
        $id = (int) $s['id'];
        return '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/shifts/' . $id . '/publish') . '" class="form-inline">'
            . csrf_field()
            . Button::make(__('shift_claim_publish_shift'))->success()->sm()->submit()->render()
            . '</form>';
    })
    ->render()
?></div>
