<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftSwap\Controllers\Web;

use kintai\Contract\Exceptions\ForbiddenException;
use kintai\Contract\Data\ShiftRepositoryInterface;
use kintai\Bundles\Installed\ShiftSwap\Repositories\ShiftSwapRequestRepositoryInterface;
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

final class EmployeeSwapController
{
    use HasBaseUrl;
    use HasStoreFeatureCheck;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly ShiftSwapRequestRepositoryInterface $swapRequests,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifs,
    ) {}

    public function swaps(Request $request): Response
    {
        if (($resp = $this->assertFeature($request, 'swaps')) !== null) {
            return $resp;
        }
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $sent     = $this->swapRequests->findByRequester($userId);
        $received = $this->swapRequests->findByTarget($userId);

        usort($sent,     fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        usort($received, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        // L'employé voit ses échanges : les notifications correspondantes sont lues.
        $swapIds = array_values(array_unique(array_map(fn(array $s) => (int) $s['id'], array_merge($sent, $received))));
        if ($userId > 0 && $swapIds !== []) {
            $this->notifs->markReadFor($userId, 'swap', $swapIds);
        }

        return Response::html($this->view->render('shift-swap::swaps', [
            'title'      => 'Échanges de shifts',
            'highlight_id' => (int) $request->query('highlight', 0),
            'sent'       => $sent,
            'received'   => $received,
            'users_map'  => $this->buildUsersMap(),
            'shifts_map' => $this->buildShiftsMap(),
        ], 'layout.app'));
    }

    public function createSwap(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);
        $today  = date('Y-m-d');

        $myShifts = array_values(array_filter(
            $this->shifts->findByUser($userId),
            fn($s) => ($s['shift_date'] ?? '') >= $today && self::isPublished($s)
        ));
        usort($myShifts, fn($a, $b) => strcmp($a['shift_date'] ?? '', $b['shift_date'] ?? ''));

        // Collègues du même store (hors moi)
        $memberships = $this->storeUsers->findByUser($userId);
        $myStoreIds  = array_unique(array_map(fn($m) => (int) $m['store_id'], $memberships));

        $colleagues = [];
        foreach ($myStoreIds as $sid) {
            foreach ($this->storeUsers->findByStore($sid) as $m) {
                $mid = (int) $m['user_id'];
                if ($mid !== $userId) {
                    $u = $this->users->findById($mid);
                    if ($u) {
                        $colleagues[$mid] = $u;
                    }
                }
            }
        }

        $targetId     = (int) ($request->query('target_id') ?? 0);
        $targetShifts = [];
        if ($targetId > 0 && isset($colleagues[$targetId])) {
            $targetShifts = array_values(array_filter(
                $this->shifts->findByUser($targetId),
                fn($s) => ($s['shift_date'] ?? '') >= $today && self::isPublished($s)
            ));
            usort($targetShifts, fn($a, $b) => strcmp($a['shift_date'] ?? '', $b['shift_date'] ?? ''));
        }

        $typesMap = [];
        foreach ($this->shiftTypes->findAll() as $t) {
            $typesMap[(int) $t['id']] = $t;
        }

        return Response::html($this->view->render('shift-swap::swaps-form', [
            'title'         => 'Demander un échange',
            'my_shifts'     => $myShifts,
            'colleagues'    => array_values($colleagues),
            'target_id'     => $targetId,
            'target_shifts' => $targetShifts,
            'types_map'     => $typesMap,
        ], 'layout.app'));
    }

    public function storeSwap(Request $request): Response
    {
        if (($resp = $this->assertFeature($request, 'swaps')) !== null) {
            return $resp;
        }
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $myShiftId     = (int) $request->post('requester_shift_id', 0);
        $targetId      = (int) $request->post('target_id', 0);
        $targetShiftId = (int) $request->post('target_shift_id', 0);
        $reason        = $request->post('reason', '') ?: null;

        $myShift = $this->shifts->findById($myShiftId);
        if ($myShift === null || (int) $myShift['user_id'] !== $userId || !self::isPublished($myShift)) {
            throw new ForbiddenException(__('error_shift_not_found'));
        }

        $targetShift = $this->shifts->findById($targetShiftId);
        if ($targetShift === null || (int) $targetShift['user_id'] !== $targetId || !self::isPublished($targetShift)) {
            throw new ForbiddenException(__('shift_swap_error_target_shift_not_found'));
        }

        if ((int) $myShift['store_id'] !== (int) $targetShift['store_id']) {
            throw new ForbiddenException(__('shift_swap_error_shifts_different_stores'));
        }

        $savedSwap = $this->swapRequests->save([
            'store_id'           => (int) $myShift['store_id'],
            'requester_id'       => $userId,
            'target_user_id'     => $targetId,
            'requester_shift_id' => $myShiftId,
            'target_shift_id'    => $targetShiftId,
            'reason'             => $reason,
            'status'             => 'pending',
            'accepted_at'        => null,
        ]);

        $this->auditLogger->log($request, 'swap.created', 'shift_swap_request', (int) ($savedSwap['id'] ?? 0), [
            'target_id' => $targetId,
        ], (int) $myShift['store_id']);

        $this->notifs->notify(
            $targetId,
            'swap_requested',
            'notif_swap_requested_body',
            $this->swapReplace($myShift, $targetShift, $userId),
            (int) ($savedSwap['id'] ?? 0),
            '/employee/swaps?highlight=' . (int) ($savedSwap['id'] ?? 0)
        );

        return Response::redirect($this->base() . '/employee/swaps?success=created');
    }

    public function acceptSwap(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $swap = $this->swapRequests->findById((int) $request->param('id'));
        if ($swap === null || (int) $swap['target_user_id'] !== $userId) {
            throw new ForbiddenException(__('shift_swap_error_request_not_found'));
        }
        if (($swap['status'] ?? '') !== 'pending' || ($swap['accepted_at'] ?? null) !== null) {
            return Response::redirect($this->base() . '/employee/swaps?error=invalid_state');
        }

        $newSwap = array_merge($swap, [
            'accepted_at' => date('Y-m-d H:i:s'),
        ]);
        $this->swapRequests->save($newSwap);

        $this->auditLogger->logUpdate($request, 'swap.peer_accepted', 'shift_swap_request', (int) $swap['id'], $swap, $newSwap, [
            'requester_id' => $swap['requester_id'] ?? null,
        ], (int) ($swap['store_id'] ?? 0) ?: null);

        $myShift     = $this->shifts->findById((int) ($swap['requester_shift_id'] ?? 0));
        $targetShift = $this->shifts->findById((int) ($swap['target_shift_id'] ?? 0));
        $this->notifs->notify(
            (int) ($swap['requester_id'] ?? 0),
            'swap_peer_accepted',
            'notif_swap_peer_accepted_body',
            $this->swapReplace($myShift ?? [], $targetShift ?? [], $userId),
            (int) $swap['id'],
            '/employee/swaps?highlight=' . (int) $swap['id']
        );

        return Response::redirect($this->base() . '/employee/swaps?success=accepted');
    }

    public function refuseSwap(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $swap = $this->swapRequests->findById((int) $request->param('id'));
        if ($swap === null || (int) $swap['target_user_id'] !== $userId) {
            throw new ForbiddenException(__('shift_swap_error_request_not_found'));
        }
        if (($swap['status'] ?? '') !== 'pending' || ($swap['accepted_at'] ?? null) !== null) {
            return Response::redirect($this->base() . '/employee/swaps?error=invalid_state');
        }

        $refusedSwap = array_merge($swap, ['status' => 'refused']);
        $this->swapRequests->save($refusedSwap);
        $this->auditLogger->logUpdate($request, 'swap.peer_refused', 'shift_swap_request', (int) $swap['id'], $swap, $refusedSwap, [
            'requester_id' => $swap['requester_id'] ?? null,
        ], (int) ($swap['store_id'] ?? 0) ?: null);

        $myShift     = $this->shifts->findById((int) ($swap['requester_shift_id'] ?? 0));
        $targetShift = $this->shifts->findById((int) ($swap['target_shift_id'] ?? 0));
        $this->notifs->notify(
            (int) ($swap['requester_id'] ?? 0),
            'swap_peer_refused',
            'notif_swap_peer_refused_body',
            $this->swapReplace($myShift ?? [], $targetShift ?? [], $userId),
            (int) $swap['id'],
            '/employee/swaps?highlight=' . (int) $swap['id']
        );

        return Response::redirect($this->base() . '/employee/swaps?success=refused');
    }

    public function cancelSwap(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $swap = $this->swapRequests->findById((int) $request->param('id'));
        if ($swap === null || (int) $swap['requester_id'] !== $userId) {
            throw new ForbiddenException(__('shift_swap_error_request_not_found'));
        }
        if (($swap['status'] ?? '') !== 'pending') {
            return Response::redirect($this->base() . '/employee/swaps?error=invalid_state');
        }

        $cancelledSwap = array_merge($swap, ['status' => 'cancelled']);
        $this->swapRequests->save($cancelledSwap);
        $this->auditLogger->logUpdate($request, 'swap.cancelled', 'shift_swap_request', (int) $swap['id'], $swap, $cancelledSwap, []);

        $myShift     = $this->shifts->findById((int) ($swap['requester_shift_id'] ?? 0));
        $targetShift = $this->shifts->findById((int) ($swap['target_shift_id'] ?? 0));
        $this->notifs->notify(
            (int) ($swap['target_user_id'] ?? 0),
            'swap_cancelled',
            'notif_swap_cancelled_body',
            $this->swapReplace($myShift ?? [], $targetShift ?? [], $userId),
            (int) $swap['id'],
            '/employee/swaps?highlight=' . (int) $swap['id']
        );

        return Response::redirect($this->base() . '/employee/swaps?success=cancelled');
    }

    /** Valeurs de remplacement (:colleague/:my_date/:target_date/:store) pour les notifications d'échange. */
    private function swapReplace(array $myShift, array $targetShift, int $colleagueId): array
    {
        $colleague     = $this->users->findById($colleagueId);
        $colleagueName = $colleague['display_name'] ?? $colleague['email'] ?? ('#' . $colleagueId);
        $store         = $this->stores->findById((int) ($myShift['store_id'] ?? $targetShift['store_id'] ?? 0));
        return [
            'colleague'   => $colleagueName,
            'my_date'     => $myShift['shift_date'] ?? '',
            'target_date' => $targetShift['shift_date'] ?? '',
            'store'       => $store['name'] ?? '',
        ];
    }

    private function buildUsersMap(): array
    {
        $map = [];
        foreach ($this->users->findAll() as $u) {
            $map[(int) $u['id']] = $u['display_name'] ?? $u['email'] ?? ('#' . $u['id']);
        }
        return $map;
    }

    private function buildShiftsMap(): array
    {
        $map = [];
        foreach ($this->shifts->findAll() as $s) {
            $map[(int) $s['id']] = $s;
        }
        return $map;
    }

    /**
     * Un brouillon de planning (contrat 1.32, publication du planning) n'est pas encore visible des employés : il ne
     * peut ni être proposé ni être demandé en échange. Sans la fonction (Core plus ancien), tout shift est publié.
     */
    private static function isPublished(array $shift): bool
    {
        return !function_exists('shift_is_published') || shift_is_published($shift);
    }
}
