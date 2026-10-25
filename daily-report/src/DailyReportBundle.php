<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\DailyReport;

use kintai\Bundles\Installed\DailyReport\Cron\AutoValidateJob;
use kintai\Bundles\Installed\DailyReport\Providers\DailyReportSales;
use kintai\Bundles\Installed\DailyReport\Repositories\DailyReportRepositoryInterface;
use kintai\Bundles\Installed\DailyReport\Repositories\DatabaseDailyReportRepository;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportAutoValidateService;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportMailService;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportPdfService;
use kintai\Contract\Auth\AuthService;
use kintai\Contract\Auth\PermissionService;
use kintai\Contract\Bundle;
use kintai\Contract\Container;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\ShiftRepositoryInterface;
use kintai\Contract\Data\ShiftTypeRepositoryInterface;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\StoreFeature;
use kintai\Contract\Services\Mailer;
use kintai\Contract\Services\TranslationService;
use kintai\Contract\Services\UserSelection;
use kintai\Contract\View\ViewRenderer;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportPermissionService;

/**
 * Le bundle possède son modèle, son dépôt, sa table (daily_reports) et ses services (droits, PDF, e-mail, validation
 * automatique), liés ici. Ce qu'il apporte au Core passe par ses déclarations : navigation, permissions, fonctionnalité
 * par magasin, magasins de l'employé pour la navigation (viewVariables), tâche planifiée « auto-validate »,
 * notifications et ventes journalières (fournisseur de ventes, lu par la rentabilité du Core et par salary-report).
 * Désactiver ou désinstaller « daily-report » retire tout cela ; la table et ses données restent.
 */
final class DailyReportBundle extends Bundle
{
    public function getName(): string
    {
        return 'daily-report';
    }

    public function getVersion(): string
    {
        return '1.2.0';
    }

    public function getLabel(): string
    {
        return __('bundle_daily_report');
    }

    public function getDescription(): string
    {
        return __('bundle_daily_report_desc');
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'daily-report');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
        $this->loadAssetsFrom('public');
    }

    /** Dépôt et services du bundle (une instance par requête). */
    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(DailyReportRepositoryInterface::class, fn() => new DatabaseDailyReportRepository());
        $container->singleton(DailyReportPermissionService::class, fn(Container $c) => new DailyReportPermissionService(
            $c->make(PermissionService::class),
        ));
        $container->singleton(DailyReportPdfService::class, fn(Container $c) => new DailyReportPdfService(
            $c->make(ViewRenderer::class),
            $c->make(TranslationService::class),
            $c->make(ShiftRepositoryInterface::class),
            $c->make(ShiftTypeRepositoryInterface::class),
            $c->make(UserRepositoryInterface::class),
        ));
        $container->singleton(DailyReportMailService::class, fn(Container $c) => new DailyReportMailService(
            $c->make(DailyReportPermissionService::class),
            $c->make(Mailer::class),
            $c->make(TranslationService::class),
            $c->make(UserSelection::class),
        ));
        $container->singleton(DailyReportAutoValidateService::class, fn(Container $c) => new DailyReportAutoValidateService(
            $c->make(StoreRepositoryInterface::class),
            $c->make(DailyReportRepositoryInterface::class),
            $c->make(UserRepositoryInterface::class),
            $c->make(DailyReportPermissionService::class),
            $c->make(DailyReportPdfService::class),
            $c->make(DailyReportMailService::class),
        ));
    }

    /**
     * Navigation (contrat 1.1) : entrée(s) de la barre du haut et de la barre mobile.
     */
    public function navigation(): array
    {
        // Avec la permission globale : un lien vers tous les rapports ; sinon, un lien par magasin où l'utilisateur
        // rédige les rapports ($daily_report_staff_stores, voir viewVariables()).
        return [
            new NavItem('daily_reports', NavItem::REPORTS, 'daily_report_daily_reports', 'admin.daily_reports.all', 'admin.daily_reports.all',
                icon: 'chart', storeFeature: 'daily_reports', order: 30, mobile: true,
                links: static function (array $view, bool $isManager): array {
                    $path = (string) ($view['path'] ?? '');
                    if (($view['route_visible'])('admin.daily_reports.all')) {
                        return [['href' => ($view['route_url'])('admin.daily_reports.all'), 'label' => __('daily_report_daily_reports'), 'active' => str_contains($path, '/daily-reports')]];
                    }
                    $stores = (array) ($view['daily_report_staff_stores'] ?? []);
                    $links = [];
                    foreach ($stores as $store) {
                        $id = (int) ($store['id'] ?? 0);
                        $links[] = [
                            'href'   => ($view['route_url'])('admin.stores') . '/' . $id . '/daily-reports',
                            'label'  => __('daily_report_daily_reports') . (count($stores) > 1 ? ' — ' . (string) ($store['name'] ?? '') : ''),
                            'active' => str_contains($path, '/stores/' . $id . '/daily-reports'),
                        ];
                    }
                    return $links;
                }),
        ];
    }

    /**
     * Catégories de permissions (contrat 1.2), proposées sur la page des rôles.
     */
    public function permissions(): array
    {
        return ['daily_reports' => ['view', 'create', 'update', 'submit', 'approve', 'delete']];
    }

    /**
     * Fonctionnalité activable par magasin (contrat 1.3), sur la fiche magasin.
     */
    public function storeFeatures(): array
    {
        return [new StoreFeature('daily_reports', 'daily_report_feature_daily_reports', 'daily_report_feature_daily_reports_desc', '📊', 70)];
    }

    /**
     * Variables partagées avec les vues des pages authentifiées (contrat 1.5).
     */
    public function viewVariables(): array
    {
        return [
            // Magasins où l'employé rédige les rapports journaliers (liens de la navigation) ; vide pour l'Owner et les
            // gérants, qui ont leur propre lien.
            'daily_report_staff_stores' => static function (array $user): array {
                $container = Container::getInstance();
                if (!empty($user['is_admin']) || $container->make(AuthService::class)->isManager()) {
                    return [];
                }
                $stores = $container->make(StoreRepositoryInterface::class);
                $permissions = $container->make(DailyReportPermissionService::class);
                $accessible = [];
                foreach ($container->make(StoreUserRepositoryInterface::class)->findByUser((int) $user['id']) as $membership) {
                    $store = $stores->findById((int) $membership['store_id']);
                    if ($store !== null && $permissions->canCreate($user, $store, $membership)) {
                        $accessible[] = $store;
                    }
                }
                return $accessible;
            },
        ];
    }

    /**
     * Tâche planifiée « auto-validate » (contrat 1.8) : /cron/run/auto-validate ou php scripts/cron.php auto-validate.
     */
    public function cronJobs(): array
    {
        return [AutoValidateJob::class];
    }

    /**
     * Types de notification : rapport soumis (aux valideurs), rapport validé (à l'auteur). Format enrichi (contrat 1.23) :
     * catégorie « reports », push par défaut, pas d'e-mail ; l'identifiant passé à notify() est celui du rapport.
     */
    public function notificationTypes(): array
    {
        return [
            'daily_report_submitted' => ['icon' => '📝', 'category' => 'reports', 'channels' => ['push' => true, 'email' => false], 'reference_type' => 'daily_report'],
            'daily_report_validated' => ['icon' => '✓', 'category' => 'reports', 'channels' => ['push' => true, 'email' => false], 'reference_type' => 'daily_report'],
        ];
    }

    /**
     * Ventes journalières (contrat 1.14), lues par la rentabilité du Core et par salary-report.
     */
    public function salesProviders(): array
    {
        return [Container::getInstance()->make(DailyReportSales::class)];
    }
}
