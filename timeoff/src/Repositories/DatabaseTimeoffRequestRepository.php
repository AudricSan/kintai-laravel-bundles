<?php
declare(strict_types=1);

namespace kintai\Bundles\Installed\TimeOff\Repositories;

use kintai\Bundles\Installed\TimeOff\Models\TimeoffRequest as EloquentTimeoffRequest;

final class DatabaseTimeoffRequestRepository implements TimeoffRequestRepositoryInterface
{
    public function __construct() {}

    public function findById(int $id): ?array
    {
        $record = EloquentTimeoffRequest::find($id);
        return $record ? $record->toArray() : null;
    }

    public function findByUser(int $userId): array
    {
        return EloquentTimeoffRequest::where('user_id', $userId)->get()->toArray();
    }

    public function findByStore(int $storeId): array
    {
        return EloquentTimeoffRequest::where('store_id', $storeId)->get()->toArray();
    }

    public function findByStatus(int $storeId, string $status): array
    {
        return EloquentTimeoffRequest::where('store_id', $storeId)
            ->where('status', $status)
            ->get()
            ->toArray();
    }

    public function findAll(): array
    {
        return EloquentTimeoffRequest::all()->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $record = EloquentTimeoffRequest::findOrFail((int) $data['id']);
            $record->fill($data);
            $record->save();
        } else {
            $record = EloquentTimeoffRequest::create($data);
        }
        return $record->toArray();
    }

    public function delete(int $id): int
    {
        $record = EloquentTimeoffRequest::find($id);
        return $record ? ($record->delete() ? 1 : 0) : 0;
    }
}
