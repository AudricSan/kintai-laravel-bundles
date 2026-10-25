<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\HiringReport\Repositories;

interface HiringReportRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findByStore(int $storeId): array;

    /**
     * @param int[]|null $storeIds Restreint aux stores donnés (null/[] = tous, selon le scope de l'appelant).
     * @param array{year?: string, month?: string, store_id?: int} $filters
     */
    public function findAll(?array $storeIds = null, array $filters = []): array;

    public function save(array $data): array;
    public function delete(int $id): int;
}
