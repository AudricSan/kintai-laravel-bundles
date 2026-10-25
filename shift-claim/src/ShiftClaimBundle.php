<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftClaim;

use kintai\Bundles\Installed\ShiftClaim\Providers\PendingClaimsProvider;
use kintai\Bundles\Installed\ShiftClaim\Services\WithdrawPendingClaimsOnClose;
use kintai\Contract\Bundle;
use kintai\Contract\Event\OpenShiftClosed;
use kintai\Contract\Container;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\ShiftFormSection;
use kintai\Contract\Extension\StoreFeature;
use kintai\Bundles\Installed\ShiftClaim\Repositories\DatabaseShiftClaimRepository;
use kintai\Bundles\Installed\ShiftClaim\Repositories\ShiftClaimRepositoryInterface;

/**
 * Le bundle possède son modèle, son dépôt et sa table (shift_claims). Ce qu'il apporte au Core passe par ses
 * déclarations : navigation, permissions, fonctionnalité par magasin, candidatures en attente (page « Demandes »),
 * champs et boutons « Shift ouvert » du formulaire de shift, notifications ; il retire les candidatures en attente
 * d'un shift que le Core retire de la bourse (OpenShiftClosed). Les colonnes is_open / open_shift_note restent dans la
 * table shifts du Core. Désactiver ou désinstaller « shift-claim » retire tout cela ; la table et ses données restent.
 */
final class ShiftClaimBundle extends Bundle
{
    public function getName(): string
    {
        return 'shift-claim';
    }

    public function getVersion(): string
    {
        return '1.2.0';
    }

    public function getLabel(): string
    {
        return __('bundle_shift_claim');
    }

    public function getDescription(): string
    {
        return __('bundle_shift_claim_desc');
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'shift-claim');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(
            ShiftClaimRepositoryInterface::class,
            fn() => new DatabaseShiftClaimRepository()
        );
    }

    /**
     * Navigation (contrat 1.1) : entrée(s) de la barre du haut et de la barre mobile.
     */
    public function navigation(): array
    {
        return [
            new NavItem('open_shifts', NavItem::REQUESTS, 'shift_claim_open_shifts', 'admin.open_shifts', 'employee.open_shifts',
                icon: 'plus', activePaths: ['/admin/open-shifts', '/employee/open-shifts'],
                storeFeature: 'open_shifts', order: 30, mobile: true),
        ];
    }

    /**
     * Catégories de permissions (contrat 1.2), proposées sur la page des rôles.
     */
    public function permissions(): array
    {
        return ['open_shifts' => ['view', 'publish', 'approve']];
    }

    /**
     * Fonctionnalité activable par magasin (contrat 1.3), sur la fiche magasin.
     */
    public function storeFeatures(): array
    {
        return [new StoreFeature('open_shifts', 'shift_claim_feature_open_shifts', 'shift_claim_feature_open_shifts_desc', '📋', 50)];
    }

    /**
     * Demandes en attente (contrat 1.9) : page « Demandes » et compteur du tableau de bord du Core.
     */
    public function pendingRequestsProviders(): array
    {
        return [new PendingClaimsProvider(Container::getInstance())];
    }

    /**
     * Champs et boutons « Shift ouvert » du formulaire de shift (contrat 1.17), si la bourse est active pour le magasin.
     */
    public function shiftFormSections(): array
    {
        return [
            new ShiftFormSection('open_shift', 'shift-claim::shift-form-section', ['is_open', 'open_shift_note'],
                actionsView: 'shift-claim::shift-form-actions', storeFeature: 'open_shifts'),
        ];
    }

    /**
     * Événements du Core (contrat 1.17) : un shift retiré de la bourse perd ses candidatures en attente.
     */
    public function eventListeners(): array
    {
        return [
            OpenShiftClosed::class => fn(OpenShiftClosed $event) => Container::getInstance()->make(WithdrawPendingClaimsOnClose::class)($event),
        ];
    }

    /**
     * Types de notification, au format enrichi (contrat 1.23) : catégorie « planning » pour la bourse aux shifts,
     * « requests » pour les candidatures. L'identifiant passé à notify() est toujours celui du shift, mais l'objet
     * désigné dépend de la page où l'utilisateur est renvoyé : « open_shift » (propre à ce bundle) pour celles qui
     * ouvrent la bourse, « shift » (celui des notifications de planning du Core) pour celles qui ouvrent la vue jour
     * du shift, où le Core les marque lues. Ainsi ouvrir la bourse ne marque pas lues des notifications de planning
     * (shift assigné, modifié) portant le même identifiant.
     */
    public function notificationTypes(): array
    {
        return [
            'open_shift_published'    => ['icon' => '📢', 'category' => 'planning', 'channels' => ['push' => true, 'email' => false], 'reference_type' => 'open_shift'],
            'shift_claim_submitted'   => ['icon' => '🙋', 'category' => 'requests', 'channels' => ['push' => true, 'email' => false], 'reference_type' => 'open_shift'],
            'shift_claim_approved'    => ['icon' => '✓', 'category' => 'requests', 'channels' => ['push' => true, 'email' => true], 'reference_type' => 'shift'],
            'shift_claim_rejected'    => ['icon' => '✗', 'category' => 'requests', 'channels' => ['push' => true, 'email' => true], 'reference_type' => 'open_shift'],
            'shift_claim_withdrawn'   => ['icon' => '⊘', 'category' => 'requests', 'channels' => ['push' => true, 'email' => false], 'reference_type' => 'open_shift'],
            'shift_claim_handed_over' => ['icon' => '⇄', 'category' => 'planning', 'channels' => ['push' => true, 'email' => true], 'reference_type' => 'shift'],
        ];
    }
}
