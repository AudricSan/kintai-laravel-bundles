<?php
declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftSwap\Repositories;

use kintai\Bundles\Installed\ShiftSwap\Models\ShiftSwapRequest as EloquentShiftSwapRequest;

final class DatabaseShiftSwapRequestRepository implements ShiftSwapRequestRepositoryInterface
{
    public function __construct() {}

    public function findById(int $id): ?array
    {
        $record = EloquentShiftSwapRequest::find($id);
        return $record ? $record->toArray() : null;
    }

    public function findByStore(int $storeId): array
    {
        return EloquentShiftSwapRequest::where('store_id', $storeId)->get()->toArray();
    }

    public function findByRequester(int $requesterId): array
    {
        return EloquentShiftSwapRequest::where('requester_id', $requesterId)->get()->toArray();
    }

    public function findByTarget(int $targetId): array
    {
        return EloquentShiftSwapRequest::where('target_user_id', $targetId)->get()->toArray();
    }

    public function findByStatus(int $storeId, string $status): array
    {
        return EloquentShiftSwapRequest::where('store_id', $storeId)
            ->where('status', $status)
            ->get()
            ->toArray();
    }

    public function findAll(): array
    {
        return EloquentShiftSwapRequest::all()->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $record = EloquentShiftSwapRequest::findOrFail((int) $data['id']);
            $record->fill($data);
            $record->save();
        } else {
            $record = EloquentShiftSwapRequest::create($data);
        }
        return $record->toArray();
    }

    public function delete(int $id): int
    {
        $record = EloquentShiftSwapRequest::find($id);
        return $record ? ($record->delete() ? 1 : 0) : 0;
    }
}
