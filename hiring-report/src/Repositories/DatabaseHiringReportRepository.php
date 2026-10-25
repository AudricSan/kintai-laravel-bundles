<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\HiringReport\Repositories;

use kintai\Bundles\Installed\HiringReport\Models\HiringReport as EloquentHiringReport;

final class DatabaseHiringReportRepository implements HiringReportRepositoryInterface
{
    public function findById(int $id): ?array
    {
        $r = EloquentHiringReport::find($id);
        return $r ? $r->toArray() : null;
    }

    public function findByStore(int $storeId): array
    {
        return EloquentHiringReport::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function findAll(?array $storeIds = null, array $filters = []): array
    {
        $q = EloquentHiringReport::orderByDesc('created_at');
        if ($storeIds !== null && $storeIds !== []) {
            $q->whereIn('store_id', $storeIds);
        }
        if (!empty($filters['year'])) {
            $q->whereYear('hire_date', (int) $filters['year']);
        }
        if (!empty($filters['month'])) {
            $q->whereMonth('hire_date', (int) $filters['month']);
        }
        if (!empty($filters['store_id'])) {
            $q->where('store_id', (int) $filters['store_id']);
        }
        return $q->get()->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $r = EloquentHiringReport::findOrFail($data['id']);
            $r->fill($data);
            $r->save();
        } else {
            $r = EloquentHiringReport::create($data);
        }
        return $r->toArray();
    }

    public function delete(int $id): int
    {
        $r = EloquentHiringReport::find($id);
        if ($r) {
            return $r->delete() ? 1 : 0;
        }
        return 0;
    }
}
