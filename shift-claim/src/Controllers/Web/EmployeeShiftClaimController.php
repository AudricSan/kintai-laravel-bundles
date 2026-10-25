<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftClaim\Controllers\Web;

use kintai\Contract\Auth\PermissionService;
use kintai\Bundles\Installed\ShiftClaim\Repositories\ShiftClaimRepositoryInterface;
use kintai\Contract\Data\ShiftRepositoryInterface;
use kintai\Contract\Data\ShiftTypeRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;
use kintai\Contract\Services\NotificationService;
use kintai\Contract\Http\Concerns\HasBaseUrl;
use kintai\Contract\Http\Concerns\HasStoreFeatureCheck;
use kintai\Contract\View\ViewRenderer;

final class EmployeeShiftClaimController
{
    use HasBaseUrl;
    use HasStoreFeatureCheck;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly UserRepositoryInterface $users,
        private readonly ShiftClaimRepositoryInterface $shiftClaims,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifs,
        private readonly PermissionService $permissions,
    ) {}

    public function openShifts(Request $request): Response
    {
        if (($resp = $this->assertFeature($request, 'open_shifts')) !== null) {
            return $resp;
        }
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        // Stores dont l'employé est membre
        $myStoreIds = array_map(
            fn($su) => (int) $su['store_id'],
            $this->storeUsers->findByUser($userId)
        );

        $openShifts = [];
        foreach ($myStoreIds as $sid) {
            foreach ($this->shifts->findOpen($sid) as $s) {
                $openShifts[(int) $s['id']] = $s;
            }
        }
        $openShifts = array_values($openShifts);
        usort($openShifts, fn($a, $b) => strcmp($a['shift_date'] ?? '', $b['shift_date'] ?? ''));

        $typesMap  = [];
        foreach ($this->shiftTypes->findAll() as $t) {
            $typesMap[(int) $t['id']] = $t;
        }
        $storesMap = [];
        foreach ($this->stores->findAll() as $s) {
            $storesMap[(int) $s['id']] = $s['name'] ?? ('#' . $s['id']);
        }

        // Marquer chaque shift avec la candidature de cet employé s'il existe
        $myClaims = [];
        foreach ($this->shiftClaims->findByUser($userId) as $c) {
            $myClaims[(int) $c['shift_id']] = $c;
        }

        foreach ($openShifts as &$shift) {
            $shift['_my_claim'] = $myClaims[(int) $shift['id']] ?? null;
            // Son propre shift cédé : visible, mais on ne postule pas à son propre shift.
            $shift['_mine'] = (int) ($shift['user_id'] ?? 0) === $userId;
        }
        unset($shift);

        // L'employé voit les shifts publiés : les notifications de bourse correspondantes sont lues.
        if ($userId > 0 && $openShifts !== []) {
            $this->notifs->markReadFor($userId, 'open_shift', array_map(fn($s) => (int) $s['id'], $openShifts));
        }

        return Response::html($this->view->render('shift-claim::open-shifts', [
            'title'      => __('shift_claim_open_shifts'),
            'highlight_id' => (int) $request->query('highlight', 0),
            'shifts'     => $openShifts,
            'types_map'  => $typesMap,
            'stores_map' => $storesMap,
            'can_manage' => false,
        ], 'layout.app'));
    }

    public function claimShift(Request $request): Response
    {
        if (($resp = $this->assertFeature($request, 'open_shifts')) !== null) {
            return $resp;
        }
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);
        $shiftId = (int) $request->param('id');

        $shift = $this->shifts->findById($shiftId);
        // Shift publié requis. Il peut avoir un titulaire : publier un shift attribué, c'est le céder (son titulaire
        // le garde jusqu'à l'approbation d'un candidat). Le titulaire ne peut pas postuler à son propre shift.
        if ($shift === null || !(int) ($shift['is_open'] ?? 0)) {
            return Response::redirect($this->base() . '/employee/open-shifts?error=not_found');
        }
        if ((int) ($shift['user_id'] ?? 0) === $userId) {
            return Response::redirect($this->base() . '/employee/open-shifts?error=own_shift');
        }

        // Vérifier appartenance au store
        $myStoreIds = array_map(
            fn($su) => (int) $su['store_id'],
            $this->storeUsers->findByUser($userId)
        );
        if (!in_array((int) ($shift['store_id'] ?? 0), $myStoreIds, true)) {
            return Response::redirect($this->base() . '/employee/open-shifts?error=forbidden');
        }

        // Éviter doublon (UNIQUE shift_id + user_id)
        $existing = $this->shiftClaims->findByUserAndShift($userId, $shiftId);
        if ($existing !== null && in_array($existing['status'] ?? '', ['pending', 'approved'], true)) {
            return Response::redirect($this->base() . '/employee/open-shifts?error=already_claimed');
        }

        $note = trim($request->post('note') ?? '');
        $claim = $this->shiftClaims->save([
            'shift_id'   => $shiftId,
            'user_id'    => $userId,
            'store_id'   => (int) ($shift['store_id'] ?? 0),
            'status'     => 'pending',
            'note'       => $note !== '' ? $note : null,
            'claimed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditLogger->log($request, 'shift_claim.created', 'shift_claim', (int) ($claim['id'] ?? 0), [
            'shift_id' => $shiftId,
            'store_id' => $shift['store_id'] ?? null,
        ], (int) ($shift['store_id'] ?? 0) ?: null);

        $this->notifyManagers((int) ($shift['store_id'] ?? 0), $shift, $userId, $shiftId);

        return Response::redirect($this->base() . '/employee/open-shifts?success=claimed');
    }

    public function withdrawShiftClaim(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);
        $shiftId = (int) $request->param('id');

        $claim = $this->shiftClaims->findByUserAndShift($userId, $shiftId);
        if ($claim === null || ($claim['status'] ?? '') !== 'pending') {
            return Response::redirect($this->base() . '/employee/open-shifts?error=not_found');
        }

        $withdrawn = $this->shiftClaims->save(array_merge($claim, ['status' => 'withdrawn']));
        $this->auditLogger->logUpdate($request, 'shift_claim.withdrawn', 'shift_claim', (int) ($claim['id'] ?? 0), $claim, $withdrawn, [
            'shift_id' => $claim['shift_id'] ?? null,
        ], (int) ($claim['store_id'] ?? 0) ?: null);

        return Response::redirect($this->base() . '/employee/open-shifts?success=withdrawn');
    }

    /** Notifie les membres du store détenant open_shifts.approve (candidature à décider). */
    private function notifyManagers(int $storeId, array $shift, int $claimantId, int $referenceId): void
    {
        $recipients = [];
        foreach ($this->storeUsers->findByStore($storeId) as $m) {
            $uid = (int) $m['user_id'];
            $candidate = $this->users->findById($uid);
            if ($candidate !== null && $this->permissions->can($candidate, 'open_shifts.approve', $storeId)) {
                $recipients[] = $uid;
            }
        }
        if ($recipients === []) {
            return;
        }

        $store        = $this->stores->findById($storeId);
        $claimant     = $this->users->findById($claimantId);
        $claimantName = trim(($claimant['last_name'] ?? '') . ' ' . ($claimant['first_name'] ?? ''));
        $claimantName = $claimantName !== '' ? $claimantName : ('#' . $claimantId);

        $this->notifs->notifyMany(
            $recipients,
            'shift_claim_submitted',
            'notif_shift_claim_pending_body',
            [
                'author' => $claimantName,
                'date'   => $shift['shift_date'] ?? '',
                'start'  => substr($shift['start_time'] ?? '', 0, 5),
                'end'    => substr($shift['end_time'] ?? '', 0, 5),
                'store'  => $store['name'] ?? '',
            ],
            $referenceId,
            '/admin/open-shifts?highlight=' . $referenceId
        );
    }
}
