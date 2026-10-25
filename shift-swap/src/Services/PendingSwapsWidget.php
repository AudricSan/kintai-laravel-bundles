<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftSwap\Services;

use kintai\Bundles\Installed\ShiftSwap\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;

/**
 * Données des widgets et de la carte « Échanges » des tableaux de bord (contrats 1.4 et 1.19), reprises du Core.
 */
final class PendingSwapsWidget
{
    public function __construct(
        private readonly ShiftSwapRequestRepositoryInterface $swaps,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Gérant : demandes d'échange en attente des magasins de son périmètre, et noms des employés concernés.
     *
     * @param list<int> $storeIds
     * @return array{pending_swaps: list<array<string, mixed>>, users_map: array<int, string>}
     */
    public function forManager(array $storeIds): array
    {
        $pending = array_values(array_filter(
            $this->swaps->findAll(),
            static fn(array $r): bool => in_array((int) ($r['store_id'] ?? 0), $storeIds, true) && ($r['status'] ?? '') === 'pending'
        ));
        $usersMap = [];
        foreach ($this->users->findAll() as $u) {
            $usersMap[(int) $u['id']] = $u['display_name'] ?? $u['email'] ?? ('#' . $u['id']);
        }
        return ['pending_swaps' => $pending, 'users_map' => $usersMap];
    }

    /**
     * Employé : demandes d'échange qu'on lui adresse et qu'il n'a pas encore acceptées.
     *
     * @return list<array<string, mixed>>
     */
    public function forEmployee(int $userId): array
    {
        return array_values(array_filter(
            $this->swaps->findByTarget($userId),
            static fn(array $s): bool => ($s['status'] ?? '') === 'pending' && ($s['accepted_at'] ?? null) === null
        ));
    }
}
