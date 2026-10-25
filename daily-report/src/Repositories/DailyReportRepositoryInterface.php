<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\DailyReport\Repositories;

interface DailyReportRepositoryInterface
{
    public function findById(int $id): ?array;

    /** Retourne tous les rapports actifs (deleted_at IS NULL) d'un store, triés par date DESC. */
    public function findByStore(int $storeId): array;

    /** Retourne le rapport actif d'un store pour une date donnée (YYYY-MM-DD), ou null. */
    public function findByStoreAndDate(int $storeId, string $date): ?array;

    /** Retourne tous les rapports actifs d'un auteur donné. */
    public function findByAuthor(int $authorId): array;

    /**
     * Retourne les rapports actifs d'un store filtrés par statut.
     * @param string $status 'draft'|'submitted'|'validated'
     */
    public function findByStoreAndStatus(int $storeId, string $status): array;

    /**
     * Retourne les rapports actifs d'un store dans une plage de dates (incluse).
     * @param string $from YYYY-MM-DD
     * @param string $to   YYYY-MM-DD
     */
    public function findByStoreAndDateRange(int $storeId, string $from, string $to): array;

    /**
     * Retourne tous les rapports actifs, optionnellement filtrés par liste de store IDs.
     * Liste vide = tous les stores.
     * @param int[] $storeIds
     */
    public function findAllActive(array $storeIds = []): array;

    /** Crée ou met à jour un rapport (upsert via id). */
    public function save(array $data): array;

    /** Supprime physiquement (hard delete). Utiliser soft delete via save() pour un workflow normal. */
    public function delete(int $id): int;
}
