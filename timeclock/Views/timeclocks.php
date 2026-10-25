<?php
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Badge;
use kintai\Contract\View\Components\Table;
use kintai\Contract\View\Components\Flash;
use kintai\Contract\View\Components\FilterBar;
use kintai\Contract\View\Components\Pagination;

/** @var array[]  $entries */
/** @var array    $users_map */
/** @var array    $stores_map */
/** @var string   $filter_date */
/** @var string   $filter_store_id */
/** @var int      $total */
/** @var int      $page */
/** @var int      $total_pages */

$users_map       ??= [];
$stores_map      ??= [];
$filter_date     ??= '';
$filter_store_id ??= '';
$total           ??= count($entries);
$page            ??= 1;
$total_pages     ??= 1;

echo Flash::fromQuery('success', [
    'edited' => __('timeclock_edited'),
    'deleted' => __('timeclock_deleted'),
])->render();
?>
<div class="page-header">
    <h2 class="page-header__title"><?= __('timeclock_timeclocks') ?> <span class="page-count">(<?= $total ?>)</span></h2>
</div>

<div class="shifts-toolbar">
    <div class="btn-group btn-group--switcher btn-group--switcher-2">
        <span class="btn-group__thumb btn-group__thumb--pos-1" aria-hidden="true"></span>
        <a href="<?= route_url('employee.timeclock') ?>" class="btn btn--ghost btn--sm"><?= __('timeclock_my_timeclock') ?></a>
        <a href="<?= route_url('admin.timeclocks') ?>" class="btn btn--ghost btn--sm btn--active"><?= __('timeclock_timeclocks') ?></a>
    </div>
</div>

<?= FilterBar::make()
    ->action(route_url('admin.timeclocks'))
    ->date('date', __('date'), $filter_date)
    ->select('store_id', __('store'), $stores_map, $filter_store_id, __('all_stores'))
    ->render()
?>

<?php if ($total_pages > 1): ?>
    <div class="pagination-info text-sm-muted mb-xs"><?= sprintf(__('page_of'), $page, $total_pages) ?></div>
<?php endif; ?>

<div class="card">
<?= Table::make()
    ->data($entries)
    ->emptyMessage(__('timeclock_no_entries'))
    ->column('#', fn($e) => (string) (int) ($e['id'] ?? 0))
    ->column(__('employee'), fn($e) =>
        '<a href="' . htmlspecialchars($BASE_URL . '/admin/users/' . (int)($e['user_id'] ?? 0) . '/edit') . '">'
        . htmlspecialchars($users_map[(int)($e['user_id'] ?? 0)] ?? '—') . '</a>')
    ->column(__('store'), fn($e) => htmlspecialchars($stores_map[(int)($e['store_id'] ?? 0)] ?? '—'))
    ->column(__('timeclock_clock_in'), fn($e) => htmlspecialchars(substr($e['clock_in_time'] ?? '', 0, 16)))
    ->column(__('timeclock_clock_out'), function($e) {
        if (empty($e['clock_out_time'])) {
            return Badge::make(__('timeclock_active'))->warning()->render();
        }
        return htmlspecialchars(substr($e['clock_out_time'], 0, 16));
    })
    ->column(__('timeclock_duration'), function($e) {
        if (!empty($e['clock_out_time']) && $e['duration_minutes'] !== null && $e['duration_minutes'] !== '') {
            $m = (int) $e['duration_minutes'];
            return floor($m / 60) . 'h' . str_pad((string)($m % 60), 2, '0', STR_PAD_LEFT);
        }
        return '—';
    })
    ->column(__('status'), function($e) {
        return empty($e['clock_out_time'])
            ? Badge::make(__('timeclock_clocked_in'))->success()->render()
            : Badge::make(__('timeclock_clocked_out'))->inactive()->render();
    })
    ->column('', function($e) use ($BASE_URL, $filter_date) {
        $id = (int) ($e['id'] ?? 0);
        $html = '<div class="btn-group">';
        $html .= '<button type="button" class="btn btn--ghost btn--sm js-tc-edit"'
            . ' data-id="' . $id . '"'
            . ' data-clock-in="' . htmlspecialchars(substr($e['clock_in_time'] ?? '', 0, 16)) . '"'
            . ' data-clock-out="' . htmlspecialchars(substr($e['clock_out_time'] ?? '', 0, 16)) . '">'
            . __('edit') . '</button>';
        $html .= '<form method="POST" action="' . htmlspecialchars($BASE_URL . '/admin/timeclocks/' . $id . '/delete?date=' . urlencode($filter_date)) . '"'
            . ' data-confirm="' . htmlspecialchars(__('timeclock_confirm_delete_timeclock'), ENT_QUOTES) . '">'
            . csrf_field() . Button::make(__('delete'))->danger()->sm()->submit()->render() . '</form>';
        $html .= '</div>';
        return $html;
    })
    ->render()
?>
<?php if ($total_pages > 1):
    echo Pagination::make($page, $total_pages)
        ->queryParams(array_filter([
            'date'     => $filter_date ?: null,
            'store_id' => $filter_store_id ?: null,
        ]))
        ->render();
endif; ?>
</div>

<div id="tc-admin-meta" data-base-url="<?= htmlspecialchars($BASE_URL) ?>" hidden></div>

<div id="tc-edit-modal" class="modal">
    <div class="modal__backdrop js-tc-close"></div>
    <div class="modal__dialog">
        <div class="modal__header">
            <h3 class="modal__title"><?= __('timeclock_edit_timeclock') ?></h3>
            <button type="button" class="modal__close js-tc-close">✕</button>
        </div>
        <form id="tc-edit-form" method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect_date" value="<?= htmlspecialchars($filter_date) ?>">
            <div class="modal__body">
                <div class="form-group">
                    <label class="form-label form-label--required"><?= __('timeclock_clock_in') ?></label>
                    <input type="datetime-local" name="clock_in_time" id="tc-in" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><?= __('timeclock_clock_out') ?> <small class="text-muted">(<?= __('timeclock_leave_empty_if_active') ?>)</small></label>
                    <input type="datetime-local" name="clock_out_time" id="tc-out" class="form-control">
                </div>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--ghost js-tc-close"><?= __('cancel') ?></button>
                <button type="submit" class="btn btn--primary"><?= __('save') ?></button>
            </div>
        </form>
    </div>
</div>

<?php if ($timeclockAdminJs = bundle_asset('timeclock', 'js/timeclock-admin.js')): ?>
<script src="<?= $timeclockAdminJs ?>"></script>
<?php endif; ?>
