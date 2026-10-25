<?php
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Card;
use kintai\Contract\View\Components\Table;

/**
 * Widget « Échanges en attente » du tableau de bord employé (ShiftSwapBundle::dashboardWidgets(), contrat 1.4), repris
 * du Core : rien quand aucune demande n'attend l'employé.
 *
 * @var list<array<string, mixed>> $pending_swaps
 */
?>
<?php if (!empty($pending_swaps)): ?>
<?php
ob_start();
?>
<div class="table-wrap">
    <?= Table::make()
        ->data($pending_swaps)
        ->text(__('date'), fn($s) => (string) ($s['shift_date'] ?? '—'))
        ->text(__('from'), fn($s) => (string) ($s['requester_name'] ?? '#' . ($s['requester_id'] ?? '?')))
        ->column('', fn($s) => Button::make(__('view'))->outline()->sm()->link(route_url('employee.swaps'))->render())
        ->render() ?>
</div>
<?php
echo Card::make()
    ->header('<span>' . __('shift_swap_pending_swaps') . '</span> ' . Button::make(__('view_all'))->ghost()->sm()->link(route_url('employee.swaps'))->render())
    ->body(ob_get_clean())
    ->render();
?>
<?php endif; ?>
