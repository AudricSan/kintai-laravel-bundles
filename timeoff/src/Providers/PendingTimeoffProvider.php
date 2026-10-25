<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\TimeOff\Providers;

use kintai\Contract\Container;
use kintai\Contract\Provider\PendingRequest;
use kintai\Contract\Provider\PendingRequestsProvider;
use kintai\Bundles\Installed\TimeOff\Repositories\TimeoffRequestRepositoryInterface;

/**
 * Demandes de congé en attente, pour la page « Demandes » et le compteur du tableau de bord du Core (contrat 1.9).
 * Mêmes demandes et mêmes colonnes que le fournisseur historique du Core.
 */
final class PendingTimeoffProvider implements PendingRequestsProvider
{
    public function __construct(private readonly Container $container) {}

    public function key(): string { return 'timeoff'; }
    public function label(): string { return 'timeoff_pending_timeoff'; }
    public function emptyLabel(): string { return 'timeoff_no_pending_request'; }
    public function permission(): string { return 'timeoff.view'; }
    public function storeFeature(): ?string { return 'timeoff'; }
    public function order(): int { return 10; }
    public function manageUrl(): string { return route_url('admin.timeoff'); }

    public function pending(?array $storeIds): array
    {
        $requests = [];
        foreach ($this->container->make(TimeoffRequestRepositoryInterface::class)->findAll() as $r) {
            $storeId = (int) ($r['store_id'] ?? 0);
            if (($r['status'] ?? '') !== 'pending' || ($storeIds !== null && !in_array($storeId, $storeIds, true))) {
                continue;
            }
            $requests[] = new PendingRequest((int) $r['id'], $storeId, (int) ($r['user_id'] ?? 0), (string) ($r['created_at'] ?? ''), [
                ['label' => '#', 'value' => (int) $r['id']],
                ['label' => 'user', 'value' => (int) ($r['user_id'] ?? 0), 'style' => PendingRequest::USER],
                ['label' => 'type', 'value' => (string) ($r['type'] ?? '—'), 'style' => PendingRequest::BADGE],
                ['label' => 'from', 'value' => (string) ($r['start_date'] ?? '—')],
                ['label' => 'to', 'value' => (string) ($r['end_date'] ?? '—')],
            ]);
        }
        return $requests;
    }
}
