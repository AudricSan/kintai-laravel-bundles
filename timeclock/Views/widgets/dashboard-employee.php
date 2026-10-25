<?php
use kintai\Contract\View\Components\Badge;
use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Card;

/**
 * Widget « Pointage » du tableau de bord employé (TimeclockBundle::dashboardWidgets(), contrat 1.4), repris du Core :
 * pointage en cours (heure d'entrée, bouton de sortie) ou bouton d'entrée.
 *
 * @var array<string, mixed>|null $active_clock
 */
$activeClock = $active_clock ?? null;
ob_start();
if ($activeClock !== null):
    echo Badge::make(__('timeclock_clocked_in'))->success()->render();
    echo ' <span class="text-muted">' . __('timeclock_clocked_in_since') . ' <strong>' . htmlspecialchars(substr($activeClock['clock_in_time'] ?? '', 11, 5)) . '</strong></span>';
    echo ' ' . Button::make(__('timeclock_clock_out'))->danger()->sm()->link(route_url('employee.timeclock'))->render();
else:
    echo Badge::make(__('timeclock_not_clocked_in'))->neutral()->render();
    echo ' ' . Button::make(__('timeclock_clock_in'))->success()->sm()->link(route_url('employee.timeclock'))->render();
endif;
echo Card::make()
    ->header('<span>' . __('timeclock') . '</span> ' . Button::make(__('view_all'))->ghost()->sm()->link(route_url('employee.timeclock'))->render())
    ->body(ob_get_clean())
    ->render();
