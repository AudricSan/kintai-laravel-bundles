<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\SalaryReport\Repositories;

use kintai\Bundles\Installed\SalaryReport\Models\SalaryReport as EloquentSalaryReport;

final class DatabaseSalaryReportRepository implements SalaryReportRepositoryInterface
{
    public function findById(int $id): ?array
    {
        $r = EloquentSalaryReport::find($id);
        return $r ? $r->toArray() : null;
    }

    public function findByStore(int $storeId): array
    {
        return EloquentSalaryReport::where('store_id', $storeId)
            ->orderByDesc('target_month')
            ->get()
            ->toArray();
    }

    public function findAll(?array $storeIds = null, array $filters = []): array
    {
        $q = EloquentSalaryReport::orderByDesc('target_month');
        if ($storeIds !== null && $storeIds !== []) {
            $q->whereIn('store_id', $storeIds);
        }
        if (!empty($filters['year'])) {
            $q->where('target_month', 'LIKE', $filters['year'] . '-%');
        }
        if (!empty($filters['month'])) {
            $q->where('target_month', 'LIKE', '%-' . str_pad((string) $filters['month'], 2, '0', STR_PAD_LEFT));
        }
        if (!empty($filters['person_in_charge'])) {
            $q->where('person_in_charge', 'LIKE', '%' . $filters['person_in_charge'] . '%');
        }
        if (!empty($filters['store_id'])) {
            $q->where('store_id', (int) $filters['store_id']);
        }
        return $q->get()->toArray();
    }

    public function findByStoreAndMonth(int $storeId, string $targetMonth, ?int $userId = null): ?array
    {
        $q = EloquentSalaryReport::where('store_id', $storeId)
            ->where('target_month', $targetMonth);
        $userId !== null ? $q->where('user_id', $userId) : $q->whereNull('user_id');
        $r = $q->first();
        return $r ? $r->toArray() : null;
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $r = EloquentSalaryReport::findOrFail($data['id']);
            $r->fill($data);
            $r->save();
        } else {
            $r = EloquentSalaryReport::create($data);
        }
        return $r->toArray();
    }

    public function delete(int $id): int
    {
        $r = EloquentSalaryReport::find($id);
        if ($r) {
            return $r->delete() ? 1 : 0;
        }
        return 0;
    }
}
