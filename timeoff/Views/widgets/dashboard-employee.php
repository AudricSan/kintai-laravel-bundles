<?php
use kintai\Contract\View\Components\Badge;
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Card;
use kintai\Contract\View\Components\Table;

/**
 * Widget « Mes congés en attente » du tableau de bord employé (TimeOffBundle::dashboardWidgets(), contrat 1.4), repris
 * du Core : rien quand l'employé n'a aucune demande en attente.
 *
 * @var list<array<string, mixed>> $pending_timeoff
 */
?>
<?php if (!empty($pending_timeoff)): ?>
<?php
ob_start();
?>
<div class="table-wrap">
    <?= Table::make()
        ->data($pending_timeoff)
        ->text(__('type'), fn($r) => (string) ($r['type'] ?? '—'))
        ->text(__('from'), fn($r) => (string) ($r['start_date'] ?? '—'))
        ->text(__('to'), fn($r) => (string) ($r['end_date'] ?? '—'))
        ->column(__('status'), fn($r) => Badge::make(__('pending'))->pending()->render())
        ->render() ?>
</div>
<?php
echo Card::make()
    ->header('<span>' . __('timeoff_my_pending_timeoff') . '</span> ' . Button::make(__('view_all'))->ghost()->sm()->link(route_url('employee.timeoff'))->render())
    ->body(ob_get_clean())
    ->render();
?>
<?php endif; ?>
