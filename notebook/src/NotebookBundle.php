<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Notebook;

use kintai\Bundles\Installed\Notebook\Repositories\DatabaseNotebookEntryRepository;
use kintai\Bundles\Installed\Notebook\Repositories\NotebookEntryRepositoryInterface;
use kintai\Bundles\Installed\Notebook\Services\TeamNotesWidget;
use kintai\Contract\Bundle;
use kintai\Contract\Extension\DashboardWidget;
use kintai\Contract\Extension\StoreFeature;

final class NotebookBundle extends Bundle
{
    public function getName(): string
    {
        return 'notebook';
    }

    public function getVersion(): string
    {
        return '1.2.0';
    }

    public function getLabel(): string
    {
        return __('bundle_notebook');
    }

    public function getDescription(): string
    {
        return __('bundle_notebook_desc');
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'notebook');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
        $this->loadAssetsFrom('public');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(
            NotebookEntryRepositoryInterface::class,
            fn() => new DatabaseNotebookEntryRepository()
        );
    }

    /**
     * Catégories de permissions (contrat 1.2), proposées sur la page des rôles.
     */
    public function permissions(): array
    {
        return ['notebook' => ['view', 'create', 'manage']];
    }

    /**
     * Fonctionnalité activable par magasin (contrat 1.3), sur la fiche magasin.
     */
    public function storeFeatures(): array
    {
        return [new StoreFeature('notes', 'notebook_feature_notes', 'notebook_feature_notes_desc', '📝', 90)];
    }

    /**
     * Widget « Notes d'équipe » des tableaux de bord gérant et employé (contrat 1.4). La clé « team_notes » est celle
     * du widget que le Core affichait jusqu'ici : les préférences des utilisateurs sont conservées.
     */
    public function dashboardWidgets(): array
    {
        $entries = fn(array $ctx): array => [
            'notebook_entries' => $this->app->container()->make(TeamNotesWidget::class)
                ->entries($ctx['store_ids'] ?? [], $ctx['stores_map'] ?? []),
        ];

        return [
            new DashboardWidget('team_notes', DashboardWidget::MANAGER, 'notebook_widget_team_notes', 'notebook::widgets.dashboard-manager',
                $entries, permission: 'notebook.view', storeFeature: 'notes'),
            new DashboardWidget('team_notes', DashboardWidget::EMPLOYEE, 'notebook_my_team_notes', 'notebook::widgets.dashboard-employee',
                $entries, permission: 'notebook.view', storeFeature: 'notes'),
        ];
    }

    /**
     * Type de notification créé à la publication d'une note ; libellé « notif_notebook_entry_created ». Format enrichi
     * (contrat 1.23) : catégorie « messages », push par défaut, pas d'e-mail ; l'identifiant passé à notify() est celui de la note.
     */
    public function notificationTypes(): array
    {
        return [
            'notebook_entry_created' => ['icon' => '📓', 'category' => 'messages', 'channels' => ['push' => true, 'email' => false], 'reference_type' => 'notebook_entry'],
        ];
    }
}
