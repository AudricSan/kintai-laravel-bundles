<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\PlanningCheck\Repositories;

use Illuminate\Database\Capsule\Manager as DB;

final class DatabasePlanningCheckRepository implements PlanningCheckRepositoryInterface
{
    public function settings(int $storeId): array
    {
        $row = DB::table('planning_check_settings')->where('store_id', $storeId)->first();
        $defaults = self::DEFAULT_SETTINGS;
        if ($row === null) {
            return $defaults;
        }
        $row = (array) $row;
        $weekdays = array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) ($row['weekdays'] ?? ''))),
            static fn(int $d): bool => $d >= 1 && $d <= 7,
        )));
        $recipients = json_decode((string) ($row['mail_recipients'] ?? ''), true);
        $recipientIds = json_decode((string) ($row['mail_recipient_ids'] ?? ''), true);

        return [
            'enabled'             => (bool) $row['enabled'],
            'deadline_time'       => self::time((string) ($row['deadline_time'] ?? ''), $defaults['deadline_time']),
            'window_start_time'   => self::time((string) ($row['window_start_time'] ?? ''), $defaults['window_start_time']),
            'weekdays'            => $weekdays !== [] ? $weekdays : $defaults['weekdays'],
            'mail_recipient_ids'  => is_array($recipientIds) ? array_values(array_map('intval', $recipientIds)) : [],
            'mail_recipients'     => is_array($recipients) ? array_values(array_filter($recipients, 'is_string')) : [],
            'include_open_shifts' => (bool) $row['include_open_shifts'],
        ];
    }

    public function saveSettings(int $storeId, array $settings): void
    {
        $values = [
            'enabled'             => !empty($settings['enabled']) ? 1 : 0,
            'deadline_time'       => (string) $settings['deadline_time'],
            'window_start_time'   => (string) $settings['window_start_time'],
            'weekdays'            => implode(',', array_map('intval', (array) $settings['weekdays'])),
            'mail_recipient_ids'  => json_encode(array_values(array_map('intval', (array) ($settings['mail_recipient_ids'] ?? []))), JSON_UNESCAPED_UNICODE),
            'mail_recipients'     => json_encode(array_values((array) $settings['mail_recipients']), JSON_UNESCAPED_UNICODE),
            'include_open_shifts' => !empty($settings['include_open_shifts']) ? 1 : 0,
            'updated_at'          => date('Y-m-d H:i:s'),
        ];
        $query = DB::table('planning_check_settings')->where('store_id', $storeId);
        if ($query->exists()) {
            $query->update($values);
            return;
        }
        DB::table('planning_check_settings')->insert(['store_id' => $storeId] + $values);
    }

    public function enabledStoreIds(): array
    {
        return array_values(array_map('intval', DB::table('planning_check_settings')->where('enabled', 1)->pluck('store_id')->all()));
    }

    public function find(int $id): ?array
    {
        $row = DB::table('planning_checks')->where('id', $id)->first();
        return $row === null ? null : self::check((array) $row);
    }

    public function findFor(int $storeId, string $targetDate): ?array
    {
        $row = DB::table('planning_checks')->where('store_id', $storeId)->where('target_date', $targetDate)->first();
        return $row === null ? null : self::check((array) $row);
    }

    public function ensure(int $storeId, string $targetDate): array
    {
        $existing = $this->findFor($storeId, $targetDate);
        if ($existing !== null) {
            return $existing;
        }
        try {
            DB::table('planning_checks')->insert([
                'store_id'    => $storeId,
                'target_date' => $targetDate,
                'status'      => self::STATUS_PENDING,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Deux requêtes simultanées : l'autre a créé la ligne (index unique) ; on la relit.
        }
        return $this->findFor($storeId, $targetDate) ?? throw new \RuntimeException('planning_check_not_created');
    }

    public function update(int $id, array $changes): array
    {
        DB::table('planning_checks')->where('id', $id)->update($changes + ['updated_at' => date('Y-m-d H:i:s')]);
        return $this->find($id) ?? throw new \RuntimeException('planning_check_not_found');
    }

    public function recentForStore(int $storeId, int $limit = 30): array
    {
        return array_values(array_map(
            static fn($row): array => self::check((array) $row),
            DB::table('planning_checks')->where('store_id', $storeId)->orderByDesc('target_date')->limit($limit)->get()->all(),
        ));
    }

    public function submittedForStores(array $storeIds): array
    {
        if ($storeIds === []) {
            return [];
        }
        return array_values(array_map(
            static fn($row): array => self::check((array) $row),
            DB::table('planning_checks')->whereIn('store_id', array_map('intval', $storeIds))
                ->where('status', self::STATUS_SUBMITTED)->orderBy('submitted_at')->get()->all(),
        ));
    }

    public function addSend(array $send): void
    {
        DB::table('planning_check_sends')->insert([
            'check_id'    => (int) $send['check_id'],
            'sent_by'     => $send['sent_by'] ?? null,
            'status'      => (string) $send['status'],
            'shifts_hash' => (string) ($send['shifts_hash'] ?? ''),
            'recipients'  => json_encode(array_values((array) ($send['recipients'] ?? [])), JSON_UNESCAPED_UNICODE),
            'error'       => $send['error'] ?? null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function sendsFor(int $checkId): array
    {
        return array_values(array_map(
            static fn($row): array => (array) $row,
            DB::table('planning_check_sends')->where('check_id', $checkId)->orderByDesc('id')->get()->all(),
        ));
    }

    private static function time(string $value, string $default): string
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : $default;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function check(array $row): array
    {
        foreach (['id', 'store_id'] as $int) {
            $row[$int] = (int) $row[$int];
        }
        foreach (['submitted_by', 'validated_by', 'rejected_by', 'editing_by'] as $ref) {
            $row[$ref] = $row[$ref] === null ? null : (int) $row[$ref];
        }
        $row['confirmed_empty'] = (bool) $row['confirmed_empty'];
        return $row;
    }
}
