<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftSwap\Providers;

use kintai\Contract\Container;
use kintai\Contract\Provider\PendingRequest;
use kintai\Contract\Provider\PendingRequestsProvider;
use kintai\Bundles\Installed\ShiftSwap\Repositories\ShiftSwapRequestRepositoryInterface;

/**
 * Demandes d'échange de shift en attente, pour la page « Demandes » et le compteur du tableau de bord du Core
 * (contrat 1.9). Mêmes demandes et mêmes colonnes que le fournisseur historique du Core.
 */
final class PendingSwapsProvider implements PendingRequestsProvider
{
    public function __construct(private readonly Container $container) {}

    public function key(): string { return 'swaps'; }
    public function label(): string { return 'shift_swap_pending_swaps'; }
    public function emptyLabel(): string { return 'shift_swap_no_pending_swap'; }
    public function permission(): string { return 'swaps.view'; }
    public function storeFeature(): ?string { return 'swaps'; }
    public function order(): int { return 20; }
    public function manageUrl(): string { return route_url('admin.swap_requests'); }

    public function pending(?array $storeIds): array
    {
        $requests = [];
        foreach ($this->container->make(ShiftSwapRequestRepositoryInterface::class)->findAll() as $r) {
            $storeId = (int) ($r['store_id'] ?? 0);
            if (($r['status'] ?? '') !== 'pending' || ($storeIds !== null && !in_array($storeId, $storeIds, true))) {
                continue;
            }
            $requests[] = new PendingRequest((int) $r['id'], $storeId, (int) ($r['requester_id'] ?? 0), (string) ($r['created_at'] ?? ''), [
                ['label' => '#', 'value' => (int) $r['id']],
                ['label' => 'shift_swap_requester', 'value' => (int) ($r['requester_id'] ?? 0), 'style' => PendingRequest::USER],
                ['label' => 'shift_swap_target', 'value' => (int) ($r['target_user_id'] ?? 0), 'style' => PendingRequest::USER],
                ['label' => 'shifts', 'value' => '#' . (int) ($r['requester_shift_id'] ?? 0)],
            ]);
        }
        return $requests;
    }
}
