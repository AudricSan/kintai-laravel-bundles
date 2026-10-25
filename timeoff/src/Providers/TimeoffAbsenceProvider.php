<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\TimeOff\Providers;

use kintai\Contract\Container;
use kintai\Contract\Provider\Absence;
use kintai\Contract\Provider\AbsenceProvider;
use kintai\Bundles\Installed\TimeOff\Repositories\TimeoffRequestRepositoryInterface;

/**
 * Absences (demandes de congé, tous statuts) pour le planning, l'export iCal et les statistiques du Core
 * (contrat 1.10). Mêmes données que la lecture historique de la table des congés par le Core.
 */
final class TimeoffAbsenceProvider implements AbsenceProvider
{
    public function __construct(private readonly Container $container) {}

    public function absences(?array $userIds, ?array $storeIds): array
    {
        $repo = $this->container->make(TimeoffRequestRepositoryInterface::class);
        if ($userIds !== null) {
            $rows = [];
            foreach ($userIds as $userId) {
                array_push($rows, ...$repo->findByUser((int) $userId));
            }
        } elseif ($storeIds !== null) {
            $rows = [];
            foreach ($storeIds as $storeId) {
                array_push($rows, ...$repo->findByStore((int) $storeId));
            }
        } else {
            $rows = $repo->findAll();
        }

        $absences = [];
        foreach ($rows as $r) {
            $storeId = (int) ($r['store_id'] ?? 0);
            if ($storeIds !== null && !in_array($storeId, $storeIds, true)) {
                continue;
            }
            try {
                $absences[] = new Absence((int) $r['id'], (int) ($r['user_id'] ?? 0), $storeId, (string) ($r['start_date'] ?? ''),
                    (string) ($r['end_date'] ?? ''), (string) ($r['type'] ?? 'vacation'), (string) ($r['status'] ?? 'pending'));
            } catch (\InvalidArgumentException) {
                // Ligne aux dates invalides : ignorée, comme elle l'était par les calculs du Core.
            }
        }
        return $absences;
    }
}
