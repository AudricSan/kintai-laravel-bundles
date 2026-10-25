<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftClaim\Services;

use kintai\Bundles\Installed\ShiftClaim\Repositories\ShiftClaimRepositoryInterface;
use kintai\Contract\Data\ShiftRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Services\NotificationService;

/**
 * Approbation d'une candidature, commune au web et à l'API (audit n°8 de Kintai). Avant, PUT /api/v1/shift-claims/{id}
 * avec status=approved marquait la candidature approuvée sans attribuer le shift ni fermer la bourse : le shift restait
 * ouvert, à son ancien titulaire, et les autres candidats n'étaient ni refusés ni prévenus.
 *
 * Écritures conditionnelles (approveIfPending, closeOpenShiftTo) : si deux approbations concurrentes visent le même
 * shift, une seule aboutit et l'autre est annulée proprement.
 */
final class ShiftClaimApproval
{
    public const APPROVED        = 'approved';
    public const ALREADY_RESOLVED = 'already_resolved';

    public function __construct(
        private readonly ShiftClaimRepositoryInterface $claims,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly StoreRepositoryInterface $stores,
        private readonly NotificationService $notifs,
    ) {}

    /**
     * @param array<string, mixed> $claim candidature (statut pending attendu)
     * @param array<string, mixed> $shift shift visé (ouvert attendu)
     * @return array{0: string, 1: array<string, mixed>|null} [résultat, candidature approuvée]
     */
    public function approve(array $claim, array $shift, int $resolverId): array
    {
        if (($claim['status'] ?? '') !== 'pending' || !(int) ($shift['is_open'] ?? 0)) {
            return [self::ALREADY_RESOLVED, null];
        }

        $now   = date('Y-m-d H:i:s');
        $saved = $this->claims->approveIfPending((int) $claim['id'], $now, $resolverId);
        if ($saved === null) {
            return [self::ALREADY_RESOLVED, null];
        }

        // Affecter le shift au candidat retenu, fermer la bourse.
        if ($this->shifts->closeOpenShiftTo((int) $shift['id'], (int) $claim['user_id']) === null) {
            // Shift fermé entre-temps par une autre approbation : on annule la nôtre plutôt que de laisser une
            // candidature « approved » sur un shift attribué à quelqu'un d'autre.
            $this->claims->save(array_merge($saved, ['status' => 'pending', 'resolved_at' => null, 'resolved_by' => null]));
            return [self::ALREADY_RESOLVED, null];
        }

        $replace = $this->shiftReplace($shift);

        // Refuser les autres candidatures encore en attente.
        foreach ($this->claims->findByShift((int) $shift['id']) as $other) {
            if ((int) $other['id'] !== (int) $claim['id'] && ($other['status'] ?? '') === 'pending') {
                $this->claims->save(array_merge($other, ['status' => 'rejected', 'resolved_at' => $now, 'resolved_by' => $resolverId]));
                $this->notifs->notify((int) $other['user_id'], 'shift_claim_rejected', 'notif_shift_claim_rejected_body',
                    $replace, (int) $shift['id'], '/employee/open-shifts?highlight=' . (int) $shift['id']);
            }
        }

        // Le shift est désormais le sien : le lien ouvre sa vue jour, où il est mis en évidence.
        $this->notifs->notify((int) $claim['user_id'], 'shift_claim_approved', 'notif_shift_claim_approved_body',
            $replace, (int) $shift['id'], '/employee/shifts/day?start=' . (string) ($shift['shift_date'] ?? '') . '&highlight=' . (int) $shift['id']);

        // Shift cédé : son ancien titulaire le perd, il doit le savoir.
        $formerHolder = (int) ($shift['user_id'] ?? 0);
        if ($formerHolder > 0 && $formerHolder !== (int) $claim['user_id']) {
            $this->notifs->notify($formerHolder, 'shift_claim_handed_over', 'notif_shift_claim_handed_over_body',
                $replace, (int) $shift['id'], '/employee/shifts/day?start=' . (string) ($shift['shift_date'] ?? '') . '&highlight=' . (int) $shift['id']);
        }

        return [self::APPROVED, $saved];
    }

    /** @return array{date: string, start: string, end: string, store: string} */
    private function shiftReplace(array $shift): array
    {
        $store = $this->stores->findById((int) ($shift['store_id'] ?? 0));
        return [
            'date'  => $shift['shift_date'] ?? '',
            'start' => substr($shift['start_time'] ?? '', 0, 5),
            'end'   => substr($shift['end_time'] ?? '', 0, 5),
            'store' => $store['name'] ?? '',
        ];
    }
}
