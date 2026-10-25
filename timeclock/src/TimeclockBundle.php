<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Timeclock;

use kintai\Bundles\Installed\Timeclock\Providers\TimeclockAttendanceProvider;
use kintai\Bundles\Installed\Timeclock\Repositories\DatabaseTimeclockRepository;
use kintai\Bundles\Installed\Timeclock\Repositories\TimeclockRepositoryInterface;
use kintai\Bundles\Installed\Timeclock\Services\TimeclockWidget;
use kintai\Contract\Bundle;
use kintai\Contract\Container;
use kintai\Contract\Extension\DashboardWidget;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\StoreFeature;

/**
 * Le bundle possède son modèle, son dépôt et sa table (timeclocks). Ce qu'il apporte au Core passe par ses
 * déclarations : navigation, permissions, fonctionnalité par magasin, pointages (alerte « pointages oubliés »,
 * retards du widget RH), widgets « Pointage » et « Pointages actifs » des tableaux de bord. Désactiver ou
 * désinstaller « timeclock » retire tout cela (le Core ne compte alors plus aucun pointage) ; la table et ses
 * données restent.
 */
final class TimeclockBundle extends Bundle
{
    public function getName(): string
    {
        return 'timeclock';
    }

    public function getVersion(): string
    {
        return '1.2.0';
    }

    public function getLabel(): string
    {
        return __('bundle_timeclock');
    }

    public function getDescription(): string
    {
        return __('bundle_timeclock_desc');
    }

    public function register(): void
    {
        $this->app->container()->singleton(TimeclockRepositoryInterface::class, fn() => new DatabaseTimeclockRepository());
        $this->loadViewsFrom($this->getPath() . '/Views', 'timeclock');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
        $this->loadAssetsFrom('public');
    }

    /**
     * Navigation (contrat 1.1) : entrée(s) de la barre du haut et de la barre mobile.
     */
    public function navigation(): array
    {
        return [
            new NavItem('timeclocks', NavItem::PLANNING, 'timeclock_timeclocks', 'admin.timeclocks', 'employee.timeclock',
                icon: 'clock', employeeLabel: 'timeclock', activePaths: ['/admin/timeclocks', '/employee/timeclock'],
                storeFeature: 'timeclock', order: 40, mobile: true),
        ];
    }

    /**
     * Catégories de permissions (contrat 1.2), proposées sur la page des rôles.
     */
    public function permissions(): array
    {
        return ['timeclock' => ['view', 'update', 'delete']];
    }

    /**
     * Fonctionnalité activable par magasin (contrat 1.3), sur la fiche magasin.
     */
    public function storeFeatures(): array
    {
        return [new StoreFeature('timeclock', 'timeclock_feature_timeclock', 'timeclock_feature_desc', '🕐', 20)];
    }

    /**
     * Pointages (contrat 1.11) : tableaux de bord du Core.
     */
    public function attendanceProviders(): array
    {
        return [new TimeclockAttendanceProvider(Container::getInstance())];
    }

    /**
     * Widgets de pointage des tableaux de bord (contrat 1.4) : « Pointages actifs » (gérant) et « Pointage » (employé).
     * Les clés « timeclocks_today » et « timeclock » sont celles des widgets que le Core affichait jusqu'ici : les
     * préférences des utilisateurs sont conservées.
     */
    public function dashboardWidgets(): array
    {
        $widget = static fn(): TimeclockWidget => Container::getInstance()->make(TimeclockWidget::class);

        return [
            new DashboardWidget('timeclocks_today', DashboardWidget::MANAGER, 'timeclock_widget_timeclocks_today', 'timeclock::widgets.dashboard-manager',
                static fn(array $ctx): array => ['active_clocks_now' => $widget()->activeNow(
                    array_map('intval', $ctx['store_ids'] ?? []), (array) ($ctx['stores_map'] ?? []), (string) ($ctx['today'] ?? date('Y-m-d'))
                )],
                permission: 'timeclock.view', storeFeature: 'timeclock'),
            new DashboardWidget('timeclock', DashboardWidget::EMPLOYEE, 'timeclock', 'timeclock::widgets.dashboard-employee',
                static fn(array $ctx): array => ['active_clock' => $widget()->activeForUser((int) ($ctx['user']['id'] ?? 0))],
                storeFeature: 'timeclock'),
        ];
    }
}
