<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftClaim\Providers;

use kintai\Contract\Container;
use kintai\Contract\Data\ShiftRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Provider\PendingRequest;
use kintai\Contract\Provider\PendingRequestsProvider;
use kintai\Bundles\Installed\ShiftClaim\Repositories\ShiftClaimRepositoryInterface;

/**
 * Candidatures en attente sur la bourse aux shifts, pour la page « Demandes » et le compteur du tableau de bord du
 * Core (contrat 1.9). Mêmes candidatures et mêmes colonnes que le fournisseur historique du Core.
 */
final class PendingClaimsProvider implements PendingRequestsProvider
{
    public function __construct(private readonly Container $container) {}

    public function key(): string { return 'open_shifts'; }
    public function label(): string { return 'shift_claim_pending_claims'; }
    public function emptyLabel(): string { return 'shift_claim_no_pending_claims'; }
    public function permission(): string { return 'open_shifts.view'; }
    public function storeFeature(): ?string { return 'open_shifts'; }
    public function order(): int { return 30; }
    public function manageUrl(): string { return route_url('admin.open_shifts'); }

    public function pending(?array $storeIds): array
    {
        if (!$this->container->has(ShiftClaimRepositoryInterface::class)) {
            return [];
        }
        $claims = $this->container->make(ShiftClaimRepositoryInterface::class);
        $shifts = $this->container->make(ShiftRepositoryInterface::class);
        $storeIds ??= array_map(fn($s) => (int) $s['id'], $this->container->make(StoreRepositoryInterface::class)->findAll());

        $found = [];
        foreach ($storeIds as $storeId) {
            foreach ($claims->findPendingByStore((int) $storeId) as $claim) {
                $shift = $shifts->findById((int) ($claim['shift_id'] ?? 0));
                $found[] = ['claim' => $claim, 'store_id' => (int) $storeId, 'shift' => $shift ?? []];
            }
        }
        usort($found, fn($a, $b) => strcmp((string) ($a['shift']['shift_date'] ?? ''), (string) ($b['shift']['shift_date'] ?? '')));

        return array_map(static fn(array $f): PendingRequest => new PendingRequest(
            (int) $f['claim']['id'],
            $f['store_id'],
            isset($f['claim']['user_id']) ? (int) $f['claim']['user_id'] : null,
            // Date de la candidature : claimed_at existe sur toutes les instances (created_at manque aux plus anciennes).
            (string) ($f['claim']['claimed_at'] ?? $f['claim']['created_at'] ?? ''),
            [
                ['label' => 'applicant', 'value' => (int) ($f['claim']['user_id'] ?? 0), 'style' => PendingRequest::USER],
                ['label' => 'store', 'value' => $f['store_id'], 'style' => PendingRequest::STORE],
                ['label' => 'date', 'value' => (string) ($f['shift']['shift_date'] ?? '—')],
                ['label' => 'start', 'value' => (string) ($f['shift']['start_time'] ?? ''), 'style' => PendingRequest::TIME],
                ['label' => 'end', 'value' => (string) ($f['shift']['end_time'] ?? ''), 'style' => PendingRequest::TIME],
            ],
        ), $found);
    }
}
