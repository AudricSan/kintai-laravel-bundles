<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftSwap;

use kintai\Bundles\Installed\ShiftSwap\Providers\PendingSwapsProvider;
use kintai\Bundles\Installed\ShiftSwap\Providers\SwapRequestsProvider;
use kintai\Bundles\Installed\ShiftSwap\Repositories\DatabaseShiftSwapRequestRepository;
use kintai\Bundles\Installed\ShiftSwap\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Bundles\Installed\ShiftSwap\Services\PendingSwapsWidget;
use kintai\Contract\Bundle;
use kintai\Contract\Container;
use kintai\Contract\Extension\Action;
use kintai\Contract\Extension\DashboardStat;
use kintai\Contract\Extension\DashboardWidget;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\StoreFeature;

/**
 * Le bundle possède son modèle, son dépôt et sa table (shift_swap_requests). Ce qu'il apporte au Core passe par ses
 * déclarations : navigation, permissions, fonctionnalité par magasin, lien « Demander un échange », demandes en
 * attente, échanges pour les statistiques, widgets et carte des tableaux de bord, notifications. Désactiver ou
 * désinstaller « shift-swap » retire tout cela ; la table et ses données restent.
 */
final class ShiftSwapBundle extends Bundle
{
    public function getName(): string
    {
        return 'shift-swap';
    }

    public function getVersion(): string
    {
        return '1.2.0';
    }

    public function getLabel(): string
    {
        return __('bundle_shift_swap');
    }

    public function getDescription(): string
    {
        return __('bundle_shift_swap_desc');
    }

    public function register(): void
    {
        $this->app->container()->singleton(ShiftSwapRequestRepositoryInterface::class, fn() => new DatabaseShiftSwapRequestRepository());
        $this->loadViewsFrom($this->getPath() . '/Views', 'shift-swap');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
        $this->loadAssetsFrom('public');
    }

    /**
     * Navigation (contrat 1.1) : entrée(s) de la barre du haut et de la barre mobile.
     */
    public function navigation(): array
    {
        return [
            new NavItem('swaps', NavItem::REQUESTS, 'shift_swap_swaps', 'admin.swap_requests', 'employee.swaps',
                icon: 'arrows', activePaths: ['/admin/swap-requests', '/employee/swaps'],
                storeFeature: 'swaps', order: 20, mobile: true),
        ];
    }

    /**
     * Catégories de permissions (contrat 1.2), proposées sur la page des rôles.
     */
    public function permissions(): array
    {
        return ['swaps' => ['view', 'create', 'update', 'approve', 'delete']];
    }

    /**
     * Fonctionnalité activable par magasin (contrat 1.3), sur la fiche magasin.
     */
    public function storeFeatures(): array
    {
        return [new StoreFeature('swaps', 'shift_swap_feature_swaps', 'shift_swap_feature_swaps_desc', '🔄', 40)];
    }

    /**
     * Liens dans les pages du Core (contrat 1.6).
     */
    public function actions(): array
    {
        return [
            new Action('request_swap', Action::SHIFTS_EMPLOYEE, 'shift_swap_request_swap', static fn(): string => route_url('employee.swaps.create'), '⇄', Action::PRIMARY, order: 10),
        ];
    }

    /**
     * Demandes en attente (contrat 1.9) : page « Demandes » et compteur du tableau de bord du Core.
     */
    public function pendingRequestsProviders(): array
    {
        return [new PendingSwapsProvider(Container::getInstance())];
    }

    /**
     * Échanges de shifts (contrat 1.18) : statistiques du Core (nombre d'échanges d'un employé).
     */
    public function swapProviders(): array
    {
        return [new SwapRequestsProvider(Container::getInstance())];
    }

    /**
     * Widget « Échanges en attente » des tableaux de bord gérant et employé (contrat 1.4). La clé « pending_swaps » est
     * celle du widget que le Core affichait jusqu'ici : les préférences des utilisateurs sont conservées.
     */
    public function dashboardWidgets(): array
    {
        $widget = static fn(): PendingSwapsWidget => Container::getInstance()->make(PendingSwapsWidget::class);

        return [
            new DashboardWidget('pending_swaps', DashboardWidget::MANAGER, 'shift_swap_widget_pending_swaps', 'shift-swap::widgets.dashboard-manager',
                static fn(array $ctx): array => $widget()->forManager(array_map('intval', $ctx['store_ids'] ?? [])),
                permission: 'swaps.view', storeFeature: 'swaps'),
            new DashboardWidget('pending_swaps', DashboardWidget::EMPLOYEE, 'shift_swap_pending_swaps', 'shift-swap::widgets.dashboard-employee',
                static fn(array $ctx): array => ['pending_swaps' => $widget()->forEmployee((int) ($ctx['user']['id'] ?? 0))],
                storeFeature: 'swaps'),
        ];
    }

    /**
     * Carte « Échanges » du tableau de bord employé (contrat 1.19) : demandes qui l'attendent.
     */
    public function dashboardStats(): array
    {
        return [
            new DashboardStat('pending_swaps', 'shift_swap_swaps',
                static fn(array $ctx): int => count(Container::getInstance()->make(PendingSwapsWidget::class)->forEmployee((int) ($ctx['user']['id'] ?? 0))),
                '🔄', 'danger', 'swaps', 20),
        ];
    }

    /**
     * Types de notification, au format enrichi (contrat 1.23). L'identifiant passé à notify() est celui de la demande
     * d'échange (reference_type « swap »). Seuls l'échange validé et les deux changements de planning (appliqué par un
     * gestionnaire, rétabli après suppression) arrivent aussi par e-mail.
     * `swap_applied` et `swap_cancelled_restored` remplacent le type `shift_assigned` du Core, que ce bundle
     * réutilisait à tort.
     */
    public function notificationTypes(): array
    {
        $push  = ['push' => true, 'email' => false];
        $both  = ['push' => true, 'email' => true];

        return [
            'swap_requested'          => ['icon' => '⇄', 'category' => 'requests', 'channels' => $push, 'reference_type' => 'swap'],
            'swap_peer_accepted'      => ['icon' => '⇄', 'category' => 'requests', 'channels' => $push, 'reference_type' => 'swap'],
            'swap_peer_refused'       => ['icon' => '⇄', 'category' => 'requests', 'channels' => $push, 'reference_type' => 'swap'],
            'swap_accepted'           => ['icon' => '⇄', 'category' => 'requests', 'channels' => $both, 'reference_type' => 'swap'],
            'swap_refused'            => ['icon' => '⇄', 'category' => 'requests', 'channels' => $push, 'reference_type' => 'swap'],
            'swap_cancelled'          => ['icon' => '⇄', 'category' => 'requests', 'channels' => $push, 'reference_type' => 'swap'],
            'swap_applied'            => ['icon' => '⇄', 'category' => 'planning', 'channels' => $both, 'reference_type' => 'swap'],
            'swap_cancelled_restored' => ['icon' => '⇄', 'category' => 'planning', 'channels' => $both, 'reference_type' => 'swap'],
        ];
    }
}
