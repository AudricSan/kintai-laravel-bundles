<?php
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Card;
use kintai\Contract\View\Components\Table;

/** @var array|null  $active_clock   Entrée de pointage active (clock_out_time = null), ou null */
/** @var string      $today          Date du jour Y-m-d */
/** @var array[]     $today_entries  Pointages du jour */
/** @var array[]     $week_entries   Pointages de la semaine en cours */
/** @var int         $store_id       Store primaire de l'employé */
/** @var array[]     $stores         Magasins de l'employé : [['id' => int, 'name' => string], ...] */

$authUser    = $auth_user ?? [];
$userId      = (int) ($authUser['id'] ?? 0);
$isActive    = $active_clock !== null;
$clockInTime = $isActive ? ($active_clock['clock_in_time'] ?? '') : '';
$clockInIso  = $isActive ? str_replace(' ', 'T', $clockInTime) : '';

// Un manager/Owner voit aussi la gestion (permission timeclock.view) : on lui propose
// le même switcher que sur le planning plutôt que de le priver d'auto-pointage faute
// de lien dans la nav (celle-ci ne pointe jamais vers cette page pour un manager).
$_routeVisible  = $route_visible ?? fn(string $r): bool => false;
$_canSeeManage  = $_routeVisible('admin.timeclocks');
?>
<?php if ($timeclockCss = bundle_asset('timeclock', 'css/timeclock.css')): ?>
<link rel="stylesheet" href="<?= $timeclockCss ?>">
<?php endif; ?>

<div id="timeclock-meta"
     data-clock-in-url="<?= route_url('employee.timeclock.clock_in') ?>"
     data-clock-out-url="<?= route_url('employee.timeclock.clock_out') ?>"
     data-csrf-token="<?= htmlspecialchars(csrf_token()) ?>"
     data-user-id="<?= $userId ?>"
     data-store-id="<?= (int) ($store_id ?? 0) ?>"
     data-clocked-in="<?= $isActive ? '1' : '0' ?>"
     data-clock-in-ts="<?= htmlspecialchars($clockInIso) ?>"
     data-msg-clock-in-ok="<?= htmlspecialchars(__('timeclock_clock_in_success')) ?>"
     data-msg-clock-out-ok="<?= htmlspecialchars(__('timeclock_clock_out_success')) ?>"
     data-msg-error="<?= htmlspecialchars(__('error_generic')) ?>"
     data-msg-pending-one="<?= htmlspecialchars(__('timeclock_pending_one')) ?>"
     data-msg-pending-many="<?= htmlspecialchars(__('timeclock_pending_many')) ?>"
     data-msg-offline-sync-failed="<?= htmlspecialchars(__('timeclock_offline_sync_failed')) ?>"
     data-msg-offline-queued="<?= htmlspecialchars(__('timeclock_offline_queued')) ?>"
     hidden></div>

<div class="page-header">
    <h2 class="page-header__title"><?= __('timeclock') ?></h2>
</div>

<?php if ($_canSeeManage): ?>
<div class="shifts-toolbar">
    <div class="btn-group btn-group--switcher btn-group--switcher-2">
        <span class="btn-group__thumb btn-group__thumb--pos-0" aria-hidden="true"></span>
        <a href="<?= route_url('employee.timeclock') ?>" class="btn btn--ghost btn--sm btn--active"><?= __('timeclock_my_timeclock') ?></a>
        <a href="<?= route_url('admin.timeclocks') ?>" class="btn btn--ghost btn--sm"><?= __('timeclock_timeclocks') ?></a>
    </div>
</div>
<?php endif; ?>

<!-- Widget principal -->
<?php
ob_start();
?>
<div class="timeclock-clock" id="timeclock-clock">--:--:--</div>
<p class="timeclock-pending" id="timeclock-pending"></p>

<?php if ($isActive): ?>
    <p class="timeclock-status timeclock-status--active">
        <?= __('timeclock_clocked_in_since') ?> <strong><?= htmlspecialchars(substr($clockInTime, 11, 5)) ?></strong>
        &nbsp;·&nbsp; <span id="timeclock-elapsed"></span>
    </p>
    <?= Button::make(__('timeclock_clock_out'))->danger()->lg()->attrs(['id' => 'btn-clockout'])->render() ?>
<?php else: ?>
    <p class="timeclock-status timeclock-status--idle"><?= __('timeclock_not_clocked_in') ?></p>
    <?php if (count($stores ?? []) > 1): ?>
        <div class="form-group timeclock-store">
            <label class="form-label" for="timeclock-store"><?= __('timeclock_store') ?></label>
            <select id="timeclock-store" class="form-control">
                <?php foreach ($stores as $_s): ?>
                    <option value="<?= (int) $_s['id'] ?>" <?= (int) $_s['id'] === (int) $store_id ? 'selected' : '' ?>><?= htmlspecialchars((string) $_s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <?= Button::make(__('timeclock_clock_in'))->success()->lg()->attrs(['id' => 'btn-clockin'])->render() ?>
<?php endif; ?>
<div id="timeclock-message" class="alert mt-sm hidden"></div>
<?php echo Card::make()->body(ob_get_clean())->render(); ?>

<!-- Pointages du jour -->
<?php if (!empty($today_entries)):
ob_start();
?>
<?= Table::make()
    ->data($today_entries)
    ->column(__('timeclock_clock_in'), fn($e) => htmlspecialchars(substr($e['clock_in_time'] ?? '', 11, 5)))
    ->column(__('timeclock_clock_out'), fn($e) => $e['clock_out_time'] ? htmlspecialchars(substr($e['clock_out_time'], 11, 5)) : '—')
    ->column(__('timeclock_duration'), fn($e) => ($e['duration_minutes'] !== null && $e['duration_minutes'] !== '')
        ? floor((int)$e['duration_minutes'] / 60) . 'h' . str_pad((string)((int)$e['duration_minutes'] % 60), 2, '0', STR_PAD_LEFT)
        : '—')
    ->render() ?>
<?php echo Card::make()->header(__('today'))->body(ob_get_clean())->render();
endif; ?>

<!-- Historique de la semaine -->
<?php
ob_start();
if (empty($week_entries)):
    echo '<p class="empty-state empty-state--timeclock">' . __('timeclock_no_entries') . '</p>';
else: ?>
<?= Table::make()
    ->data($week_entries)
    ->column(__('date'), fn($e) => htmlspecialchars($e['shift_date'] ?? ''))
    ->column(__('timeclock_clock_in'), fn($e) => htmlspecialchars(substr($e['clock_in_time'] ?? '', 11, 5)))
    ->column(__('timeclock_clock_out'), fn($e) => $e['clock_out_time'] ? htmlspecialchars(substr($e['clock_out_time'], 11, 5)) : '—')
    ->column(__('timeclock_duration'), fn($e) => ($e['duration_minutes'] !== null && $e['duration_minutes'] !== '')
        ? floor((int)$e['duration_minutes'] / 60) . 'h' . str_pad((string)((int)$e['duration_minutes'] % 60), 2, '0', STR_PAD_LEFT)
        : '—')
    ->render() ?>
<?php endif;
echo Card::make()->header(__('timeclock_this_week'))->body(ob_get_clean())->render();
?>

<?php if ($timeclockJs = bundle_asset('timeclock', 'js/timeclock.js')): ?>
<script src="<?= $timeclockJs ?>"></script>
<?php endif; ?>
