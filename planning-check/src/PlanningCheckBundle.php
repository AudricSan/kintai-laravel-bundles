<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\PlanningCheck;

use kintai\Bundles\Installed\PlanningCheck\Cron\EscalateJob;
use kintai\Bundles\Installed\PlanningCheck\Repositories\DatabasePlanningCheckRepository;
use kintai\Bundles\Installed\PlanningCheck\Repositories\PlanningCheckRepositoryInterface;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningCheckOverlayResolver;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningCheckService;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningMailService;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningPdfService;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningShiftRows;
use kintai\Contract\Auth\PermissionService;
use kintai\Contract\Bundle;
use kintai\Contract\Container;
use kintai\Contract\Data\ShiftRepositoryInterface;
use kintai\Contract\Data\ShiftTypeRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\PageOverlay;
use kintai\Contract\Services\Mailer;
use kintai\Contract\Services\NotificationService;
use kintai\Contract\Services\TranslationService;
use kintai\Contract\Services\UserSelection;
use kintai\Contract\View\ViewRenderer;

/**
 * Validation du planning de demain : avant l'heure limite, une fenêtre demande au manager si les shifts du lendemain sont
 * corrects (aperçu à l'appui) ; il les soumet, un valideur les approuve, et le PDF part au patron. Le bundle possède ses
 * tables, son dépôt et ses services ; ce qu'il apporte au Core passe par ses déclarations (navigation, permissions,
 * notifications, tâche planifiée, fenêtres de page).
 */
final class PlanningCheckBundle extends Bundle
{
    public function getName(): string
    {
        return 'planning-check';
    }

    public function getVersion(): string
    {
        return '0.1.0';
    }

    public function getLabel(): string
    {
        return __('bundle_planning_check');
    }

    public function getDescription(): string
    {
        return __('bundle_planning_check_desc');
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'planning-check');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
        $this->loadAssetsFrom('public');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(PlanningCheckRepositoryInterface::class, fn() => new DatabasePlanningCheckRepository());
        $container->singleton(PlanningShiftRows::class, fn(Container $c) => new PlanningShiftRows(
            $c->make(ShiftRepositoryInterface::class),
            $c->make(ShiftTypeRepositoryInterface::class),
            $c->make(UserRepositoryInterface::class),
        ));
        $container->singleton(PlanningPdfService::class, fn(Container $c) => new PlanningPdfService(
            $c->make(ViewRenderer::class),
            $c->make(TranslationService::class),
        ));
        $container->singleton(PlanningMailService::class, fn(Container $c) => new PlanningMailService(
            $c->make(Mailer::class),
            $c->make(TranslationService::class),
            $c->make(PlanningPdfService::class),
            $c->make(UserSelection::class),
        ));
        $container->singleton(PlanningCheckService::class, fn(Container $c) => new PlanningCheckService(
            $c->make(PlanningCheckRepositoryInterface::class),
            $c->make(StoreRepositoryInterface::class),
            $c->make(StoreUserRepositoryInterface::class),
            $c->make(UserRepositoryInterface::class),
            $c->make(PermissionService::class),
            $c->make(NotificationService::class),
            $c->make(PlanningShiftRows::class),
            $c->make(PlanningPdfService::class),
            $c->make(PlanningMailService::class),
        ));
        $container->singleton(PlanningCheckOverlayResolver::class, fn(Container $c) => new PlanningCheckOverlayResolver(
            $c->make(PlanningCheckRepositoryInterface::class),
            $c->make(StoreRepositoryInterface::class),
            $c->make(PlanningCheckService::class),
            $c->make(PlanningShiftRows::class),
        ));
    }

    /** Entrée de menu : l'historique des validations (un magasin : directement le sien). */
    public function navigation(): array
    {
        return [
            new NavItem('planning_check', NavItem::REPORTS, 'planning_check', 'admin.planning_check.index', 'admin.planning_check.index', 'clipboard',
                activePaths: ['/planning-check'], order: 35),
        ];
    }

    /**
     * Droits calqués sur le rapport journalier : consulter l'historique, soumettre le planning de demain (managers par
     * défaut), le valider et envoyer le PDF, régler le bundle par magasin.
     */
    public function permissions(): array
    {
        return ['planning_check' => ['view', 'submit', 'approve', 'settings']];
    }

    /** Tâche planifiée « planning-check-escalate » (contrat 1.8) : alerte des valideurs à l'heure limite. */
    public function cronJobs(): array
    {
        return [EscalateJob::class];
    }

    /** Types de notification (contrat 1.23) : planning à valider, validé, refusé, en retard, envoi échoué. */
    public function notificationTypes(): array
    {
        $type = static fn(string $icon, bool $email = false): array => [
            'icon' => $icon, 'category' => 'reports', 'channels' => ['push' => true, 'email' => $email], 'reference_type' => 'planning_check',
        ];
        return [
            'planning_check_submitted' => $type('📋'),
            'planning_check_validated' => $type('✓'),
            'planning_check_rejected'  => $type('↩'),
            'planning_check_overdue'   => $type('⏰'),
            'planning_check_failed'    => $type('⚠'),
        ];
    }

    /**
     * Fenêtres de page (contrat 1.27) : la validation à effectuer (valideur), le planning de demain à soumettre (manager) et
     * la barre de rappel pendant la correction des shifts. Une seule s'affiche à la fois ; la décision est prise côté serveur
     * (PlanningCheckOverlayResolver) et toute action passe par les routes du bundle.
     */
    public function pageOverlays(): array
    {
        $resolver = static fn(): PlanningCheckOverlayResolver => Container::getInstance()->make(PlanningCheckOverlayResolver::class);
        $kind = static fn(string $kind): \Closure => static function (array $ctx) use ($resolver, $kind): bool {
            return ($resolver()->resolve($ctx)['kind'] ?? null) === $kind;
        };
        $data = static fn(array $ctx): array => (array) $resolver()->resolve($ctx);

        return [
            new PageOverlay('planning_check_approve', 'planning-check::overlay-approve', $kind(PlanningCheckOverlayResolver::KIND_APPROVE), $data,
                permission: 'planning_check.approve', priority: 10),
            new PageOverlay('planning_check_submit', 'planning-check::overlay-submit', $kind(PlanningCheckOverlayResolver::KIND_SUBMIT), $data,
                permission: 'planning_check.submit', priority: 20),
            new PageOverlay('planning_check_editing', 'planning-check::overlay-editing', $kind(PlanningCheckOverlayResolver::KIND_EDITING), $data,
                permission: 'planning_check.submit', priority: 30, dismissible: false, placement: PageOverlay::BAR),
        ];
    }
}
