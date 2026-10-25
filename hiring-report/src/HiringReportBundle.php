<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\HiringReport;

use kintai\Bundles\Installed\HiringReport\Repositories\DatabaseHiringReportRepository;
use kintai\Bundles\Installed\HiringReport\Repositories\HiringReportRepositoryInterface;
use kintai\Bundles\Installed\HiringReport\Services\HiringReportOnEmployeeCreated;
use kintai\Contract\Bundle;
use kintai\Contract\Event\EmployeeCreated;
use kintai\Contract\Extension\NavItem;

/**
 * Le bundle possède son modèle, son dépôt et sa table (hiring_reports). À chaque création d'employé affecté à un
 * magasin, le Core annonce EmployeeCreated (contrat 1.13) et le bundle crée le rapport d'embauche
 * (Services\HiringReportOnEmployeeCreated). Désactiver ou désinstaller « hiring-report » retire la consultation et
 * l'édition des rapports, ainsi que leur création automatique ; la table et ses données restent.
 */
final class HiringReportBundle extends Bundle
{
    public function getName(): string
    {
        return 'hiring-report';
    }

    public function getVersion(): string
    {
        return '1.2.0';
    }

    public function getLabel(): string
    {
        return __('bundle_hiring_report');
    }

    public function getDescription(): string
    {
        return __('bundle_hiring_report_desc');
    }

    public function register(): void
    {
        $this->app->container()->singleton(HiringReportRepositoryInterface::class, fn() => new DatabaseHiringReportRepository());
        $this->loadViewsFrom($this->getPath() . '/Views', 'hiring-report');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
        $this->loadAssetsFrom('public');
    }

    /**
     * Navigation (contrat 1.1) : entrée(s) de la barre du haut et de la barre mobile.
     */
    public function navigation(): array
    {
        return [
            new NavItem('hiring_report', NavItem::REPORTS, 'hiring_report_hiring_reports', 'admin.reports.hiring', 'admin.reports.hiring',
                icon: 'person', activePaths: ['*/reports/hiring'], order: 10),
        ];
    }

    /**
     * Catégories de permissions (contrat 1.2), proposées sur la page des rôles.
     */
    public function permissions(): array
    {
        return ['hiring_reports' => ['view', 'create', 'update', 'delete']];
    }

    /**
     * Événements du Core (contrat 1.13) : le rapport d'embauche est créé à la création de l'employé.
     */
    public function eventListeners(): array
    {
        return [
            EmployeeCreated::class => fn(EmployeeCreated $event) => $this->app->container()->make(HiringReportOnEmployeeCreated::class)($event),
        ];
    }
}
