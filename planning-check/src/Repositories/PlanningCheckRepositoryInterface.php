<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\PlanningCheck\Repositories;

/**
 * Réglages, validations et historique des envois du bundle. Tous les identifiants d'utilisateur ne sont que des
 * références (pas de clé étrangère) : l'historique survit à la suppression d'un compte.
 */
interface PlanningCheckRepositoryInterface
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ESCALATED = 'escalated';

    public const DEFAULT_SETTINGS = [
        'enabled'             => false,
        'deadline_time'       => '12:00',
        'window_start_time'   => '06:00',
        'weekdays'            => [1, 2, 3, 4, 5, 6, 7],
        'mail_recipient_ids'  => [],
        'mail_recipients'     => [],
        'include_open_shifts' => false,
    ];

    /**
     * Réglages du magasin, complétés par les valeurs par défaut.
     *
     * @return array{enabled: bool, deadline_time: string, window_start_time: string, weekdays: list<int>, mail_recipient_ids: list<int>, mail_recipients: list<string>, include_open_shifts: bool}
     */
    public function settings(int $storeId): array;

    /** @param array<string, mixed> $settings déjà validés (voir PlanningCheckSettingsValidator) */
    public function saveSettings(int $storeId, array $settings): void;

    /** @return list<int> magasins pour lesquels la validation est activée */
    public function enabledStoreIds(): array;

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findFor(int $storeId, string $targetDate): ?array;

    /** La validation du magasin pour ce jour, créée (« pending ») si elle n'existe pas encore. @return array<string, mixed> */
    public function ensure(int $storeId, string $targetDate): array;

    /** @param array<string, mixed> $changes @return array<string, mixed> la ligne mise à jour */
    public function update(int $id, array $changes): array;

    /** @return list<array<string, mixed>> les plus récentes d'abord */
    public function recentForStore(int $storeId, int $limit = 30): array;

    /**
     * Validations en attente d'approbation (« submitted ») pour ces magasins, les plus anciennes d'abord.
     *
     * @param list<int> $storeIds
     * @return list<array<string, mixed>>
     */
    public function submittedForStores(array $storeIds): array;

    /** @param array<string, mixed> $send check_id, sent_by, status, shifts_hash, recipients, error */
    public function addSend(array $send): void;

    /** @return list<array<string, mixed>> les plus récents d'abord */
    public function sendsFor(int $checkId): array;
}
