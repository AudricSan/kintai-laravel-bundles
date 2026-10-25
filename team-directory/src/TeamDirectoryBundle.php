<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\TeamDirectory;

use kintai\Contract\Bundle;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\ProfileSection;

final class TeamDirectoryBundle extends Bundle
{
    public function getName(): string
    {
        return 'team-directory';
    }

    public function getVersion(): string
    {
        return '1.2.0';
    }

    public function getLabel(): string
    {
        return __('bundle_team_directory');
    }

    public function getDescription(): string
    {
        return __('bundle_team_directory_desc');
    }

    public function register(): void
    {
        $this->loadViewsFrom($this->getPath() . '/Views', 'team-directory');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
        $this->loadAssetsFrom('public');
    }

    /**
     * Navigation (contrat 1.1) : entrée(s) de la barre du haut et de la barre mobile.
     */
    public function navigation(): array
    {
        return [
            new NavItem('team_directory', NavItem::MAIN, 'team_directory', 'team.index', 'team.index',
                icon: 'users', activePaths: ['/team'], order: 10),
        ];
    }

    /**
     * Sections du profil (contrat 1.12) : cases « apparaître dans l'annuaire » et partage des coordonnées, dans le
     * formulaire « Mes informations ». Le Core n'enregistre que ces colonnes de users.
     */
    public function profileSections(): array
    {
        return [
            new ProfileSection('directory', 'team-directory::profile-section',
                ['show_in_directory', 'share_email', 'share_phone', 'share_mobile_phone'], order: 10),
        ];
    }
}
