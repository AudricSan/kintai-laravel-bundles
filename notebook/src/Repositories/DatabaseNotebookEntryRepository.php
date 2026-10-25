<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Notebook\Repositories;

use kintai\Bundles\Installed\Notebook\Models\NotebookEntry;

final class DatabaseNotebookEntryRepository implements NotebookEntryRepositoryInterface
{
    public function findById(int $id): ?array
    {
        $record = NotebookEntry::find($id);
        return $record ? $record->toArray() : null;
    }

    public function findByStore(int $storeId): array
    {
        return NotebookEntry::where('store_id', $storeId)->get()->toArray();
    }

    public function findAll(): array
    {
        return NotebookEntry::all()->toArray();
    }

    public function findVisibleForStores(array $storeIds): array
    {
        return NotebookEntry::where(function ($query) use ($storeIds) {
            $query->whereNull('store_id');
            if ($storeIds !== []) {
                $query->orWhereIn('store_id', $storeIds);
            }
        })->get()->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $record = NotebookEntry::findOrFail((int) $data['id']);
            $record->fill($data);
            $record->save();
        } else {
            $record = NotebookEntry::create($data);
        }
        return $record->toArray();
    }

    public function delete(int $id): int
    {
        $record = NotebookEntry::find($id);
        return $record ? ($record->delete() ? 1 : 0) : 0;
    }
}
