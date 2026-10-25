<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Timeclock\Repositories;

interface TimeclockRepositoryInterface
{
    public function findById(int $id): ?array;

    public function findByUser(int $userId): array;

    public function findByStore(int $storeId): array;

    /** Pointages d'un store dont shift_date est comprise entre $from et $to inclus (YYYY-MM-DD). */
    public function findByStoreBetween(int $storeId, string $from, string $to): array;

    /** Toutes les entrées d'un employé pour une date donnée (format Y-m-d). */
    public function findByUserAndDate(int $userId, string $date): array;

    /** Toutes les entrées d'une boutique pour une date donnée (format Y-m-d). */
    public function findByStoreAndDate(int $storeId, string $date): array;

    /** Retourne le clock-in actif (sans clock_out_time) de l'employé, ou null. */
    public function findActiveByUser(int $userId): ?array;

    public function findAll(): array;

    public function save(array $data): array;

    public function delete(int $id): int;
}
