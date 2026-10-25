<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\TimeOff\Services;

use kintai\Bundles\Installed\TimeOff\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;

/**
 * Données des widgets et de la carte « Congés en attente » des tableaux de bord (contrats 1.4 et 1.19), reprises du
 * Core.
 */
final class PendingTimeoffWidget
{
    public function __construct(
        private readonly TimeoffRequestRepositoryInterface $timeoff,
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Gérant : demandes de congé en attente des magasins de son périmètre, et noms des employés.
     *
     * @param list<int> $storeIds
     * @return array{pending_timeoff: list<array<string, mixed>>, users_map: array<int, string>}
     */
    public function forManager(array $storeIds): array
    {
        $pending = array_values(array_filter(
            $this->timeoff->findAll(),
            static fn(array $r): bool => in_array((int) ($r['store_id'] ?? 0), $storeIds, true) && ($r['status'] ?? '') === 'pending'
        ));
        $usersMap = [];
        foreach ($this->users->findAll() as $u) {
            $usersMap[(int) $u['id']] = $u['display_name'] ?? $u['email'] ?? ('#' . $u['id']);
        }
        return ['pending_timeoff' => $pending, 'users_map' => $usersMap];
    }

    /**
     * Employé : ses demandes de congé en attente.
     *
     * @return list<array<string, mixed>>
     */
    public function forEmployee(int $userId): array
    {
        return array_values(array_filter(
            $this->timeoff->findByUser($userId),
            static fn(array $r): bool => ($r['status'] ?? '') === 'pending'
        ));
    }
}
