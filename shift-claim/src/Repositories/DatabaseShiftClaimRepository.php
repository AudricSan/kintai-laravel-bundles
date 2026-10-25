<?php
declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftClaim\Repositories;

use kintai\Bundles\Installed\ShiftClaim\Models\ShiftClaim as EloquentShiftClaim;

final class DatabaseShiftClaimRepository implements ShiftClaimRepositoryInterface
{
    public function __construct() {}

    public function findById(int $id): ?array
    {
        $record = EloquentShiftClaim::find($id);
        return $record ? $record->toArray() : null;
    }

    public function findByShift(int $shiftId): array
    {
        return EloquentShiftClaim::where('shift_id', $shiftId)->get()->toArray();
    }

    public function findByUser(int $userId): array
    {
        return EloquentShiftClaim::where('user_id', $userId)->get()->toArray();
    }

    public function findByStore(int $storeId): array
    {
        return EloquentShiftClaim::where('store_id', $storeId)->get()->toArray();
    }

    public function findByUserAndShift(int $userId, int $shiftId): ?array
    {
        $record = EloquentShiftClaim::where('user_id', $userId)
            ->where('shift_id', $shiftId)
            ->first();
        return $record ? $record->toArray() : null;
    }

    public function findPendingByStore(int $storeId): array
    {
        return EloquentShiftClaim::where('store_id', $storeId)
            ->where('status', 'pending')
            ->get()
            ->toArray();
    }

    public function findAll(): array
    {
        return EloquentShiftClaim::all()->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $record = EloquentShiftClaim::findOrFail((int) $data['id']);
            $record->fill($data);
            $record->save();
        } else {
            $record = EloquentShiftClaim::create($data);
        }
        return $record->toArray();
    }

    public function delete(int $id): int
    {
        $record = EloquentShiftClaim::find($id);
        return $record ? ($record->delete() ? 1 : 0) : 0;
    }

    public function approveIfPending(int $id, string $resolvedAt, int $resolvedBy): ?array
    {
        $affected = EloquentShiftClaim::where('id', $id)
            ->where('status', 'pending')
            ->update([
                'status'      => 'approved',
                'resolved_at' => $resolvedAt,
                'resolved_by' => $resolvedBy,
            ]);
        if ($affected === 0) {
            return null;
        }
        return EloquentShiftClaim::find($id)->toArray();
    }
}
