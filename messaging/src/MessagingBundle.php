<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Messaging;

use kintai\Contract\Bundle;
use kintai\Contract\Container;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\StoreFeature;
use kintai\Bundles\Installed\Messaging\Repositories\DatabaseMessageRepository;
use kintai\Bundles\Installed\Messaging\Repositories\MessageRepositoryInterface;

final class MessagingBundle extends Bundle
{
    public function getName(): string
    {
        return 'messaging';
    }

    public function getVersion(): string
    {
        return '1.2.0';
    }

    public function getLabel(): string
    {
        return __('bundle_messaging');
    }

    public function getDescription(): string
    {
        return __('bundle_messaging_desc');
    }

    public function register(): void
    {
        $this->registerServices();
        $this->loadViewsFrom($this->getPath() . '/Views', 'messaging');
        $this->loadRoutesFrom($this->getPath() . '/routes.php');
        $this->loadAssetsFrom('public');
    }

    private function registerServices(): void
    {
        $container = $this->app->container();

        $container->singleton(
            MessageRepositoryInterface::class,
            fn() => new DatabaseMessageRepository()
        );
    }

    /**
     * Navigation (contrat 1.1) : entrée(s) de la barre du haut et de la barre mobile.
     */
    public function navigation(): array
    {
        return [
            new NavItem('messages', NavItem::REQUESTS, 'messaging_messages', 'admin.messages', 'employee.messages',
                icon: 'message', activePaths: ['/admin/messages', '/employee/messages'],
                storeFeature: 'messages', badge: 'unread_messages_count', order: 40, mobile: true),
        ];
    }

    /**
     * Catégories de permissions (contrat 1.2), proposées sur la page des rôles.
     */
    public function permissions(): array
    {
        return ['messaging' => ['view', 'send', 'delete']];
    }

    /**
     * Fonctionnalité activable par magasin (contrat 1.3), sur la fiche magasin.
     */
    public function storeFeatures(): array
    {
        return [new StoreFeature('messages', 'messaging_feature_messages', 'messaging_feature_messages_desc', '💬', 60)];
    }

    /**
     * Variables partagées avec les vues des pages authentifiées (contrat 1.5).
     */
    public function viewVariables(): array
    {
        return [
            // Badge des messages non lus dans la navigation (NavItem::$badge).
            'unread_messages_count' => static function (array $user): int {
                try {
                    return Container::getInstance()->make(MessageRepositoryInterface::class)->countUnread((int) $user['id']);
                } catch (\Throwable) {
                    return 0; // table pas encore créée
                }
            },
        ];
    }

    /**
     * Type de notification : nouveau message reçu ; libellé « notif_message_received ». Format enrichi (contrat 1.23) :
     * catégorie « messages », push par défaut, pas d'e-mail ; l'identifiant passé à notify() est celui du fil.
     */
    public function notificationTypes(): array
    {
        return [
            'message_received' => ['icon' => '✉', 'category' => 'messages', 'channels' => ['push' => true, 'email' => false], 'reference_type' => 'message_thread'],
        ];
    }
}
