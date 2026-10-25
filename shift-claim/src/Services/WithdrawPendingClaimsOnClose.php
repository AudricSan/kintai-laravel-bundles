<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftClaim\Services;

use kintai\Bundles\Installed\ShiftClaim\Repositories\ShiftClaimRepositoryInterface;
use kintai\Contract\Event\OpenShiftClosed;

/**
 * Shift retiré de la bourse depuis le formulaire de shift ou l'API (événement OpenShiftClosed du Core, contrat 1.17) :
 * les candidatures encore en attente sur ce shift passent à « retirée ». Le Core le faisait lui-même jusqu'ici (sans
 * notification) ; le bouton « Fermer la bourse » du bundle (unpublishShift) garde ses notifications.
 */
final class WithdrawPendingClaimsOnClose
{
    public function __construct(private readonly ShiftClaimRepositoryInterface $claims) {}

    public function __invoke(OpenShiftClosed $event): void
    {
        if ($event->shiftId() <= 0) {
            return;
        }
        foreach ($this->claims->findByShift($event->shiftId()) as $claim) {
            if (($claim['status'] ?? '') === 'pending') {
                $this->claims->save(array_merge($claim, ['status' => 'withdrawn']));
            }
        }
    }
}
