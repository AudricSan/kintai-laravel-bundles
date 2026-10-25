<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ResignationReport;

use kintai\Contract\Bundle;
use kintai\Contract\Extension\Action;
use kintai\Contract\Extension\NavItem;
use kintai\Bundles\Installed\ResignationReport\Cron\ResignationDeactivateJob;
use kintai\Bundles\Installed\ResignationReport\Repositories\DatabaseResignationReportRepository;
use kintai\Bundles\Installed\ResignationReport\Repositories\ResignationReportRepositoryInterface;

/**
 * Le bundle possède son modèle, son dépôt et sa table (resignation_reports) ; aucun composant du Core n'en dépend.
 * Désactiver ou désinstaller « resignation-report » retire entièrement la fonctionnalité (la table et ses données
 * restent).
 */
final class ResignationReportBundle extends Bundle
{
    public function getName(): string
    {
        return 'resignation-report';
    }

    public function getVersion(): string
    {
        return '1.2.0';
    }

    public function getLabel(): string
    {
        return __('bundle_resignation_report');
    }

    public function getDescription(): string
    {
        return __('bundle_resignation_report_desc');
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'resignation-report');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
        $this->loadAssetsFrom('public');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(
            ResignationReportRepositoryInterface::class,
            fn() => new DatabaseResignationReportRepository()
        );
    }

    /**
     * Navigation (contrat 1.1) : entrée(s) de la barre du haut et de la barre mobile.
     */
    public function navigation(): array
    {
        return [
            new NavItem('resignation_report', NavItem::REPORTS, 'resignation_report', 'admin.reports.resignation', 'admin.reports.resignation',
                icon: 'exit', activePaths: ['*/reports/resignation'], order: 40),
        ];
    }

    /**
     * Catégories de permissions (contrat 1.2), proposées sur la page des rôles.
     */
    public function permissions(): array
    {
        return ['resignation_reports' => ['view', 'create', 'update', 'delete']];
    }

    /**
     * Liens dans les pages du Core (contrat 1.6).
     */
    public function actions(): array
    {
        $resign = static fn(array $c): string => base_url() . '/admin/stores/' . (int) $c['store_id'] . '/reports/resignation/create?user_id=' . (int) $c['user_id'];

        return [
            new Action('resign', Action::EMPLOYEE_ROW, 'resignation_report_resign', $resign, '✕', Action::DANGER, 'resignation_reports.create', order: 20),
            new Action('resign', Action::EMPLOYEE_STORE, 'resignation_report_resign', $resign, '', Action::DANGER, 'resignation_reports.create', order: 10),
        ];
    }

    /**
     * Tâche planifiée (contrat 1.8) : désactive chaque jour les comptes dont la date de démission est atteinte.
     */
    public function cronJobs(): array
    {
        return [ResignationDeactivateJob::class];
    }
}
