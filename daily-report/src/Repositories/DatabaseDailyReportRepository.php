<?php
declare(strict_types=1);

namespace kintai\Bundles\Installed\DailyReport\Repositories;

use kintai\Bundles\Installed\DailyReport\Models\DailyReport as EloquentDailyReport;

final class DatabaseDailyReportRepository implements DailyReportRepositoryInterface
{
    public function __construct() {}

    public function findById(int $id): ?array
    {
        $record = EloquentDailyReport::whereNull('deleted_at')->find($id);
        return $record ? $record->toArray() : null;
    }

    public function findByStore(int $storeId): array
    {
        return EloquentDailyReport::where('store_id', $storeId)
            ->whereNull('deleted_at')
            ->orderBy('report_date', 'desc')
            ->get()
            ->toArray();
    }

    public function findByStoreAndDate(int $storeId, string $date): ?array
    {
        $record = EloquentDailyReport::where('store_id', $storeId)
            ->where('report_date', $date)
            ->whereNull('deleted_at')
            ->first();
        return $record ? $record->toArray() : null;
    }

    public function findByAuthor(int $authorId): array
    {
        return EloquentDailyReport::where('author_id', $authorId)
            ->whereNull('deleted_at')
            ->orderBy('report_date', 'desc')
            ->get()
            ->toArray();
    }

    public function findByStoreAndStatus(int $storeId, string $status): array
    {
        return EloquentDailyReport::where('store_id', $storeId)
            ->where('status', $status)
            ->whereNull('deleted_at')
            ->orderBy('report_date', 'desc')
            ->get()
            ->toArray();
    }

    public function findByStoreAndDateRange(int $storeId, string $from, string $to): array
    {
        return EloquentDailyReport::where('store_id', $storeId)
            ->whereNull('deleted_at')
            ->where('report_date', '>=', $from)
            ->where('report_date', '<=', $to)
            ->orderBy('report_date', 'desc')
            ->get()
            ->toArray();
    }

    public function findAllActive(array $storeIds = []): array
    {
        $query = EloquentDailyReport::whereNull('deleted_at');
        if (!empty($storeIds)) {
            $query->whereIn('store_id', $storeIds);
        }
        return $query->orderBy('report_date', 'desc')->get()->toArray();
    }

    public function save(array $data): array
    {
        if (!empty($data['id'])) {
            $record = EloquentDailyReport::findOrFail((int) $data['id']);
            $record->fill($data);
            $record->save();
        } else {
            $record = EloquentDailyReport::create($data);
        }
        return $record->toArray();
    }

    public function delete(int $id): int
    {
        $record = EloquentDailyReport::find($id);
        return $record ? ($record->delete() ? 1 : 0) : 0;
    }
}
