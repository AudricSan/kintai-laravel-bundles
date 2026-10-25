<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\SalaryReport\Repositories;

interface SalaryReportRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findByStore(int $storeId): array;
    public function findAll(?array $storeIds = null, array $filters = []): array;
    /**
     * @param int|null $userId null = rapport global du magasin, sinon rapport
     *                         propre à cet employé (voir migration 2026_07_15_000001).
     */
    public function findByStoreAndMonth(int $storeId, string $targetMonth, ?int $userId = null): ?array;
    public function save(array $data): array;
    public function delete(int $id): int;
}
