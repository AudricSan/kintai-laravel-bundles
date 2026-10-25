<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ResignationReport\Repositories;

interface ResignationReportRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findByStore(int $storeId): array;
    public function findAll(?array $storeIds = null, array $filters = []): array;
    public function save(array $data): array;
    public function delete(int $id): int;

    /**
     * Rapports rattachés à un compte, pas encore appliqués (deactivated_at nul) et dont la date de démission est
     * atteinte (ou absente).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findDueForDeactivation(string $today): array;
}
