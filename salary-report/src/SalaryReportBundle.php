<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\SalaryReport;

use kintai\Contract\Bundle;
use kintai\Contract\Extension\Action;
use kintai\Contract\Extension\NavItem;
use kintai\Bundles\Installed\SalaryReport\Repositories\DatabaseSalaryReportRepository;
use kintai\Bundles\Installed\SalaryReport\Repositories\SalaryReportRepositoryInterface;

/**
 * Le bundle possède son modèle, son dépôt et sa table (salary_reports) ; aucun composant du Core n'en dépend.
 * Désactiver ou désinstaller « salary-report » retire entièrement la fonctionnalité (la table et ses données restent).
 *
 * Le total des ventes du mois est pré-rempli à partir des ventes journalières, lues par le contrat
 * (kintai\Contract\Services\Sales, fournisseurs de ventes 1.14) : sans fournisseur (daily-report absent), il reste à 0.
 */
final class SalaryReportBundle extends Bundle
{
    public function getName(): string
    {
        return 'salary-report';
    }

    public function getVersion(): string
    {
        return '1.2.0';
    }

    public function getLabel(): string
    {
        return __('bundle_salary_report');
    }

    public function getDescription(): string
    {
        return __('bundle_salary_report_desc');
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'salary-report');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
        $this->loadAssetsFrom('public');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(
            SalaryReportRepositoryInterface::class,
            fn() => new DatabaseSalaryReportRepository()
        );
    }

    /**
     * Navigation (contrat 1.1) : entrée(s) de la barre du haut et de la barre mobile.
     */
    public function navigation(): array
    {
        return [
            new NavItem('salary_report', NavItem::REPORTS, 'salary_report', 'admin.reports.salary', 'admin.reports.salary',
                icon: 'money', activePaths: ['*/reports/salary'], order: 50),
        ];
    }

    /**
     * Liens dans les pages du Core (contrat 1.6).
     */
    public function actions(): array
    {
        // Lien « Rapport de salaire » vers le formulaire de création, pré-rempli pour l'employé quand il est connu.
        $salary = static fn(array $c): string => base_url() . '/admin/stores/' . (int) $c['store_id'] . '/reports/salary/create'
            . (isset($c['user_id']) ? '?user_id=' . (int) $c['user_id'] : '');

        return [
            new Action('salary_report', Action::EMPLOYEE_ROW, 'salary_report', $salary, '💰', permission: 'payroll.generate', order: 10),
            new Action('salary_report', Action::EMPLOYEE_STORE, 'salary_report', $salary, permission: 'payroll.generate', order: 20),
            new Action('salary_report', Action::STORE_REPORT, 'salary_report', static fn(array $c): string => $salary(['store_id' => $c['store_id']]), '💰', Action::PRIMARY, order: 10),
            new Action('salary_report', Action::STORE_REPORT_ROW, 'salary_report', $salary, '💰', order: 10),
        ];
    }
}
