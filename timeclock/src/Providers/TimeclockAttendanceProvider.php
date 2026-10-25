<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Timeclock\Providers;

use kintai\Contract\Container;
use kintai\Contract\Provider\Attendance;
use kintai\Contract\Provider\AttendanceProvider;
use kintai\Bundles\Installed\Timeclock\Repositories\TimeclockRepositoryInterface;

/**
 * Pointages pour les tableaux de bord du Core (pointages en cours ou oubliés, retards) — contrat 1.11. Mêmes données
 * que la lecture historique de la table des pointages par le Core.
 */
final class TimeclockAttendanceProvider implements AttendanceProvider
{
    public function __construct(private readonly Container $container) {}

    public function attendance(?array $userIds, ?array $storeIds, ?string $from = null, ?string $to = null): array
    {
        $repo = $this->container->make(TimeclockRepositoryInterface::class);
        if ($storeIds !== null && count($storeIds) === 1 && $from !== null && $to !== null) {
            $rows = $repo->findByStoreBetween((int) $storeIds[0], $from, $to);
        } else {
            $rows = $repo->findAll();
        }

        $attendance = [];
        foreach ($rows as $r) {
            $userId = (int) ($r['user_id'] ?? 0);
            $storeId = (int) ($r['store_id'] ?? 0);
            $day = (string) ($r['shift_date'] ?? '');
            if (($userIds !== null && !in_array($userId, $userIds, true)) || ($storeIds !== null && !in_array($storeId, $storeIds, true))
                || ($from !== null && $day < $from) || ($to !== null && $day > $to)) {
                continue;
            }
            try {
                $attendance[] = new Attendance((int) $r['id'], $userId, $storeId, $day, (string) ($r['clock_in_time'] ?? ''),
                    empty($r['clock_out_time']) ? null : (string) $r['clock_out_time']);
            } catch (\InvalidArgumentException) {
                // Pointage incomplet (sans heure d'arrivée) : ignoré.
            }
        }
        return $attendance;
    }
}
