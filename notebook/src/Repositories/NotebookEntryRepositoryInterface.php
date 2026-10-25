<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Notebook\Repositories;

interface NotebookEntryRepositoryInterface
{
    public function findById(int $id): ?array;

    public function findByStore(int $storeId): array;

    public function findAll(): array;

    /**
     * Notes visibles pour un ensemble de stores : celles rattachées à l'un de
     * ces stores, plus les notes "toute l'organisation" (store_id null).
     *
     * @param int[] $storeIds
     */
    public function findVisibleForStores(array $storeIds): array;

    public function save(array $data): array;

    public function delete(int $id): int;
}
