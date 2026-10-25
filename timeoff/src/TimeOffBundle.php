<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\TimeOff;

use kintai\Bundles\Installed\TimeOff\Providers\PendingTimeoffProvider;
use kintai\Bundles\Installed\TimeOff\Providers\TimeoffAbsenceProvider;
use kintai\Bundles\Installed\TimeOff\Repositories\DatabaseTimeoffRequestRepository;
use kintai\Bundles\Installed\TimeOff\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Bundles\Installed\TimeOff\Services\PendingTimeoffWidget;
use kintai\Contract\Bundle;
use kintai\Contract\Container;
use kintai\Contract\Extension\DashboardStat;
use kintai\Contract\Extension\DashboardWidget;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\StoreFeature;

/**
 * Le bundle possède son modèle, son dépôt et sa table (timeoff_requests). Ce qu'il apporte au Core passe par ses
 * déclarations : navigation, permissions, fonctionnalité par magasin, demandes en attente, absences (planning,
 * export iCal, statistiques), widgets et carte « Congés en attente » des tableaux de bord, notifications. Désactiver
 * ou désinstaller « timeoff » retire tout cela (le Core ne voit alors plus aucun congé) ; la table et ses données
 * restent.
 */
final class TimeOffBundle extends Bundle
{
    public function getName(): string
    {
        return 'timeoff';
    }

    public function getVersion(): string
    {
        return '1.2.0';
    }

    public function getLabel(): string
    {
        return __('bundle_timeoff');
    }

    public function getDescription(): string
    {
        return __('bundle_timeoff_desc');
    }

    public function register(): void
    {
        $this->app->container()->singleton(TimeoffRequestRepositoryInterface::class, fn() => new DatabaseTimeoffRequestRepository());
        $this->loadViewsFrom($this->getPath() . '/Views', 'timeoff');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
        $this->loadAssetsFrom('public');
    }

    /**
     * Navigation (contrat 1.1) : entrée(s) de la barre du haut et de la barre mobile.
     */
    public function navigation(): array
    {
        return [
            new NavItem('timeoff', NavItem::REQUESTS, 'timeoff', 'admin.timeoff', 'employee.timeoff',
                icon: 'leaf', employeeLabel: 'timeoff_my_timeoff', activePaths: ['/admin/timeoff', '/employee/timeoff'],
                storeFeature: 'timeoff', order: 10, mobile: true),
        ];
    }

    /**
     * Catégories de permissions (contrat 1.2), proposées sur la page des rôles.
     */
    public function permissions(): array
    {
        return ['timeoff' => ['view', 'create', 'update', 'approve', 'delete']];
    }

    /**
     * Fonctionnalité activable par magasin (contrat 1.3), sur la fiche magasin.
     */
    public function storeFeatures(): array
    {
        return [new StoreFeature('timeoff', 'timeoff_feature_timeoff', 'timeoff_feature_desc', '🏖', 30)];
    }

    /**
     * Demandes en attente (contrat 1.9) : page « Demandes » et compteur du tableau de bord du Core.
     */
    public function pendingRequestsProviders(): array
    {
        return [new PendingTimeoffProvider(Container::getInstance())];
    }

    /**
     * Absences (contrat 1.10) : planning, export iCal et statistiques du Core.
     */
    public function absenceProviders(): array
    {
        return [new TimeoffAbsenceProvider(Container::getInstance())];
    }

    /**
     * Widget « Congés en attente » des tableaux de bord gérant et employé (contrat 1.4). La clé « pending_timeoff » est
     * celle du widget que le Core affichait jusqu'ici : les préférences des utilisateurs sont conservées.
     */
    public function dashboardWidgets(): array
    {
        $widget = static fn(): PendingTimeoffWidget => Container::getInstance()->make(PendingTimeoffWidget::class);

        return [
            new DashboardWidget('pending_timeoff', DashboardWidget::MANAGER, 'timeoff_widget_pending_timeoff', 'timeoff::widgets.dashboard-manager',
                static fn(array $ctx): array => $widget()->forManager(array_map('intval', $ctx['store_ids'] ?? [])),
                permission: 'timeoff.view', storeFeature: 'timeoff'),
            new DashboardWidget('pending_timeoff', DashboardWidget::EMPLOYEE, 'timeoff_my_pending_timeoff', 'timeoff::widgets.dashboard-employee',
                static fn(array $ctx): array => ['pending_timeoff' => $widget()->forEmployee((int) ($ctx['user']['id'] ?? 0))],
                storeFeature: 'timeoff'),
        ];
    }

    /**
     * Carte « Congés en attente » du tableau de bord employé (contrat 1.19) : ses demandes en attente.
     */
    public function dashboardStats(): array
    {
        return [
            new DashboardStat('pending_timeoff', 'timeoff_pending_timeoff',
                static fn(array $ctx): int => count(Container::getInstance()->make(PendingTimeoffWidget::class)->forEmployee((int) ($ctx['user']['id'] ?? 0))),
                '🌴', 'warning', 'timeoff', 10),
        ];
    }

    /**
     * Types de notification (contrat 1.7).
     */
    public function notificationTypes(): array
    {
        // Format enrichi (contrat 1.23) : catégorie, canaux par défaut, objet désigné par l'id passé à notify().
        // Les décisions arrivent aussi par e-mail ; la demande à traiter reste une notification push.
        return [
            'timeoff_approved'  => ['icon' => '✓', 'category' => 'requests', 'channels' => ['push' => true, 'email' => true], 'reference_type' => 'timeoff'],
            'timeoff_refused'   => ['icon' => '✗', 'category' => 'requests', 'channels' => ['push' => true, 'email' => true], 'reference_type' => 'timeoff'],
            'timeoff_submitted' => ['icon' => '🏖', 'category' => 'requests', 'channels' => ['push' => true, 'email' => false], 'reference_type' => 'timeoff'],
        ];
    }
}
