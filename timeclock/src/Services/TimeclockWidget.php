<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Timeclock\Services;

use kintai\Bundles\Installed\Timeclock\Repositories\TimeclockRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;

/**
 * Données des widgets de pointage des tableaux de bord (contrat 1.4), reprises du Core : pointages en cours du jour
 * (gérant) et pointage en cours de l'employé.
 */
final class TimeclockWidget
{
    public function __construct(
        private readonly TimeclockRepositoryInterface $timeclocks,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Gérant : pointages du jour sans sortie, dans les magasins de son périmètre, avec le nom de l'employé et du magasin,
     * du plus ancien au plus récent (comme le widget du Core).
     *
     * @param list<int>          $storeIds
     * @param array<int, string> $storesMap id => nom du magasin
     * @return list<array<string, mixed>>
     */
    public function activeNow(array $storeIds, array $storesMap, string $today): array
    {
        $active = array_values(array_filter(
            $this->timeclocks->findAll(),
            static fn(array $tc): bool => in_array((int) ($tc['store_id'] ?? 0), $storeIds, true)
                && ($tc['shift_date'] ?? '') === $today && empty($tc['clock_out_time'])
        ));
        if ($active === []) {
            return [];
        }
        $usersMap = [];
        foreach ($this->users->findAll() as $u) {
            $usersMap[(int) $u['id']] = $u['display_name'] ?? $u['email'] ?? ('#' . $u['id']);
        }
        usort($active, static fn(array $a, array $b): int => strcmp((string) ($a['clock_in_time'] ?? ''), (string) ($b['clock_in_time'] ?? '')));

        return array_map(static function (array $tc) use ($usersMap, $storesMap): array {
            $tc['user_name']  = $usersMap[(int) ($tc['user_id'] ?? 0)] ?? ('User #' . (int) ($tc['user_id'] ?? 0));
            $tc['store_name'] = $storesMap[(int) ($tc['store_id'] ?? 0)] ?? ('Store #' . (int) ($tc['store_id'] ?? 0));
            return $tc;
        }, $active);
    }

    /** Employé : son pointage en cours (sans sortie), ou null. */
    public function activeForUser(int $userId): ?array
    {
        return $this->timeclocks->findActiveByUser($userId);
    }
}
