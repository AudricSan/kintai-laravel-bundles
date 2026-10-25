<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\PlanningCheck\Services;

use kintai\Contract\Data\ShiftRepositoryInterface;
use kintai\Contract\Data\ShiftTypeRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;

/**
 * Les shifts d'un magasin pour un jour, prêts à être affichés (aperçu de la fenêtre, historique) et imprimés (PDF) :
 * par employé, avec type, horaires, durée nette et total d'heures. Un shift supprimé n'y figure jamais ; un shift ouvert
 * (sans employé) n'y figure que si le magasin l'a demandé.
 */
final class PlanningShiftRows
{
    public function __construct(
        private readonly ShiftRepositoryInterface $shifts,
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly UserRepositoryInterface $users,
    ) {}

    /** Shifts bruts du jour, sans les supprimés (sert aussi à l'empreinte). @return list<array<string, mixed>> */
    public function raw(int $storeId, string $date): array
    {
        return array_values(array_filter(
            $this->shifts->findByDate($storeId, $date),
            static fn(array $s): bool => empty($s['deleted_at']),
        ));
    }

    /**
     * @param array<string, mixed> $settings réglages du magasin (include_open_shifts)
     * @return array{rows: list<array<string, mixed>>, total_minutes: int, assigned: int, open: int}
     */
    public function forDay(int $storeId, string $date, array $settings): array
    {
        $includeOpen = !empty($settings['include_open_shifts']);
        $types = array_column($this->shiftTypes->findByStore($storeId), null, 'id');
        $names = [];
        $rows = [];
        $assigned = 0;
        $open = 0;

        foreach ($this->raw($storeId, $date) as $shift) {
            $userId = (int) ($shift['user_id'] ?? 0);
            if ($userId === 0) {
                $open++;
                if (!$includeOpen) {
                    continue;
                }
            } else {
                $assigned++;
                if (!isset($names[$userId])) {
                    $user = $this->users->findById($userId);
                    $names[$userId] = $user === null
                        ? '—'
                        : (trim(($user['last_name'] ?? '') . ' ' . ($user['first_name'] ?? '')) ?: ($user['email'] ?? '—'));
                }
            }
            $gross = (int) ($shift['duration_minutes'] ?? 0);
            $pause = (int) ($shift['pause_minutes'] ?? 0);
            $typeId = (int) ($shift['shift_type_id'] ?? 0);
            $rows[] = [
                'employee'  => $userId === 0 ? null : $names[$userId],
                'type'      => $types[$typeId]['name'] ?? '—',
                'color'     => $types[$typeId]['color'] ?? null,
                'start'     => substr((string) ($shift['start_time'] ?? ''), 0, 5),
                'end'       => substr((string) ($shift['end_time'] ?? ''), 0, 5),
                'net_min'   => max(0, $gross - $pause),
                'open'      => $userId === 0,
            ];
        }

        usort($rows, static fn(array $a, array $b): int => [$a['start'], (string) $a['employee']] <=> [$b['start'], (string) $b['employee']]);

        return [
            'rows'          => $rows,
            'total_minutes' => array_sum(array_column($rows, 'net_min')),
            'assigned'      => $assigned,
            'open'          => $open,
        ];
    }
}
