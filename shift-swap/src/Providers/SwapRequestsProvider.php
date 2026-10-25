<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftSwap\Providers;

use kintai\Bundles\Installed\ShiftSwap\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Contract\Container;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Provider\Swap;
use kintai\Contract\Provider\SwapProvider;

/**
 * Demandes d'échange fournies au Core (contrat 1.18) : il en compte les échanges d'un employé pour ses statistiques
 * (rapport employés d'un magasin, page statistiques d'un employé).
 */
final class SwapRequestsProvider implements SwapProvider
{
    public function __construct(private readonly Container $container) {}

    public function swaps(?array $storeIds, ?string $from = null, ?string $to = null): array
    {
        $repo = $this->container->make(ShiftSwapRequestRepositoryInterface::class);
        $storeIds ??= array_map(fn(array $s): int => (int) $s['id'], $this->container->make(StoreRepositoryInterface::class)->findAll());

        $swaps = [];
        foreach ($storeIds as $storeId) {
            foreach ($repo->findByStore((int) $storeId) as $r) {
                $date = substr((string) ($r['created_at'] ?? ''), 0, 10);
                if (($from !== null && $date < $from) || ($to !== null && $date > $to) || ($r['created_at'] ?? '') === '') {
                    continue;
                }
                $swaps[] = new Swap((int) $r['id'], (int) $r['store_id'], (int) ($r['requester_id'] ?? 0),
                    (int) ($r['target_user_id'] ?? 0), (string) $r['created_at'], (string) ($r['status'] ?? 'pending'));
            }
        }
        return $swaps;
    }
}
