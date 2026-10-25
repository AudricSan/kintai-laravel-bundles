<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftClaim\Repositories;

interface ShiftClaimRepositoryInterface
{
    public function findById(int $id): ?array;

    public function findByShift(int $shiftId): array;

    public function findByUser(int $userId): array;

    public function findByStore(int $storeId): array;

    public function findByUserAndShift(int $userId, int $shiftId): ?array;

    /** Retourne les candidatures en attente pour un ou plusieurs stores. */
    public function findPendingByStore(int $storeId): array;

    public function findAll(): array;

    public function save(array $data): array;

    /**
     * Approuve la candidature, mais uniquement si elle est encore "pending" au
     * moment de l'écriture (condition portée par l'UPDATE lui-même, pas par
     * une lecture préalable) — protège contre deux résolutions concurrentes
     * de la même candidature. Retourne la candidature à jour, ou null si elle
     * n'était déjà plus pending (quelqu'un d'autre l'a déjà résolue).
     */
    public function approveIfPending(int $id, string $resolvedAt, int $resolvedBy): ?array;

    public function delete(int $id): int;
}
