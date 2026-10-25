<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\TimeOff\Controllers\Web;

use kintai\Contract\Exceptions\ForbiddenException;
use kintai\Contract\Exceptions\NotFoundException;
use kintai\Contract\Exceptions\ValidationException;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Bundles\Installed\TimeOff\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;
use kintai\Contract\Services\NotificationService;
use kintai\Contract\Http\Concerns\HasAdminAccess;
use kintai\Contract\View\ViewRenderer;

final class AdminTimeoffController
{
    use HasAdminAccess;
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly TimeoffRequestRepositoryInterface $timeoffRequests,
        private readonly AuditLogger $auditLogger,
        private readonly NotificationService $notifs,
        private readonly StoreRepositoryInterface $stores,
        private readonly UserRepositoryInterface $users,
        private readonly StoreUserRepositoryInterface $storeUsers,
    ) {}

    public function timeoff(Request $request): Response
    {
        $managedIds = $this->managedIds($request);

        $usersMap  = $this->buildUsersMap();
        $storesMap = $this->buildStoresMap($managedIds);
        $requests  = $this->filterByStore($this->timeoffRequests->findAll(), $managedIds);

        $typeFilter   = (string) ($request->query('type') ?? '');
        $statusFilter = (string) ($request->query('status') ?? '');
        if ($typeFilter !== '') {
            $requests = array_values(array_filter($requests, fn($r) => ($r['type'] ?? '') === $typeFilter));
        }
        if ($statusFilter !== '') {
            $requests = array_values(array_filter($requests, fn($r) => ($r['status'] ?? 'pending') === $statusFilter));
        }

        $sort = $request->query('sort') ?? 'date_desc';
        usort($requests, function ($a, $b) use ($sort, $usersMap, $storesMap) {
            $dateA  = ($a['start_date'] ?? '');
            $dateB  = ($b['start_date'] ?? '');
            $userA  = strtolower($usersMap[(int)($a['user_id'] ?? 0)] ?? '');
            $userB  = strtolower($usersMap[(int)($b['user_id'] ?? 0)] ?? '');
            $typeA  = $a['type'] ?? '';
            $typeB  = $b['type'] ?? '';
            $statA  = $a['status'] ?? '';
            $statB  = $b['status'] ?? '';
            $storeA = strtolower($storesMap[(int)($a['store_id'] ?? 0)] ?? '');
            $storeB = strtolower($storesMap[(int)($b['store_id'] ?? 0)] ?? '');
            return match ($sort) {
                'date_asc'    => strcmp($dateA, $dateB),
                'user_asc'    => strcmp($userA, $userB) ?: strcmp($dateA, $dateB),
                'user_desc'   => strcmp($userB, $userA) ?: strcmp($dateA, $dateB),
                'type_asc'    => strcmp($typeA, $typeB) ?: strcmp($dateA, $dateB),
                'type_desc'   => strcmp($typeB, $typeA) ?: strcmp($dateA, $dateB),
                'status_asc'  => strcmp($statA, $statB) ?: strcmp($dateA, $dateB),
                'status_desc' => strcmp($statB, $statA) ?: strcmp($dateA, $dateB),
                'store_asc'   => strcmp($storeA, $storeB) ?: strcmp($dateA, $dateB),
                'store_desc'  => strcmp($storeB, $storeA) ?: strcmp($dateA, $dateB),
                default       => strcmp($dateB, $dateA),
            };
        });

        // Le gestionnaire voit les demandes : les notifications « à traiter » correspondantes sont lues.
        $viewerId = (int) (($request->getAttribute('auth_user')['id'] ?? 0));
        if ($viewerId > 0 && $requests !== []) {
            $this->notifs->markReadFor($viewerId, 'timeoff', array_map(fn($r) => (int) ($r['id'] ?? 0), $requests));
        }

        return Response::html($this->view->render('timeoff::timeoff', [
            'title'         => 'Congés',
            'highlight_id'  => (int) $request->query('highlight', 0),
            'requests'      => $requests,
            'users_map'     => $usersMap,
            'stores_map'    => $storesMap,
            'sort'          => $sort,
            'type_filter'   => $typeFilter,
            'status_filter' => $statusFilter,
        ], 'layout.app'));
    }

    /**
     * GET /admin/timeoff/create — formulaire pour créer un congé pour un employé,
     * sans demande préalable de sa part (ex : congé imposé, arrêt maladie déclaré par un tiers, etc.).
     */
    public function createTimeoff(Request $request): Response
    {
        $managedIds = $this->managedIds($request);
        $userIds    = $managedIds !== null ? $this->memberUserIds($managedIds) : null;
        $users      = $userIds !== null
            ? array_values(array_filter($this->users->findAll(), fn($u) => in_array((int) $u['id'], $userIds, true)))
            : $this->users->findAll();

        usort($users, fn($a, $b) => strcmp(
            strtolower(trim(($a['last_name'] ?? '') . ' ' . ($a['first_name'] ?? ''))),
            strtolower(trim(($b['last_name'] ?? '') . ' ' . ($b['first_name'] ?? '')))
        ));

        return Response::html($this->view->render('timeoff::timeoff-form', [
            'title' => __('timeoff_create_timeoff'),
            'users' => $users,
            'today' => date('Y-m-d'),
        ], 'layout.app'));
    }

    /**
     * POST /admin/timeoff/create — enregistre un congé directement au statut "approved"
     * pour qu'il apparaisse immédiatement au calendrier.
     */
    public function storeTimeoffForEmployee(Request $request): Response
    {
        $userId = (int) $request->post('user_id', 0);
        $user   = $userId > 0 ? $this->users->findById($userId) : null;
        if ($user === null) {
            throw new NotFoundException(__('error_employee_not_found'));
        }

        $managedIds = $this->managedIds($request);
        if ($managedIds !== null && !in_array($userId, $this->memberUserIds($managedIds), true)) {
            throw new ForbiddenException(__('timeoff_error_not_employee_manager'));
        }

        $storeId = $this->resolveStoreId($request, $userId);
        $this->assertStoreAccess($request, $storeId);

        $validTypes = ['vacation', 'sick', 'personal', 'unpaid', 'other'];
        $type       = $request->post('type', 'vacation');
        if (!in_array($type, $validTypes, true)) {
            $type = 'vacation';
        }
        [$startDate, $endDate] = $this->validDates($request->post('start_date', ''), $request->post('end_date', ''));
        $reason   = $request->post('reason', '') ?: null;
        $authUser = $request->getAttribute('auth_user');

        $saved = $this->timeoffRequests->save([
            'store_id'    => $storeId,
            'user_id'     => $userId,
            'type'        => $type,
            'start_date'  => $startDate,
            'end_date'    => $endDate,
            'reason'      => $reason,
            'status'      => 'approved',
            'processed_by' => $authUser['id'] ?? null,
            'processed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditLogger->log($request, 'timeoff.created_by_admin', 'timeoff_request', (int) ($saved['id'] ?? 0), [
            'user_id' => $userId, 'type' => $type, 'start' => $startDate, 'end' => $endDate,
        ], $storeId ?: null);

        $this->notifs->notify(
            $userId,
            'timeoff_approved',
            'notif_timeoff_added_body',
            $this->timeoffReplace($type, $startDate, $endDate),
            (int) ($saved['id'] ?? 0),
            '/employee/timeoff?highlight=' . (int) ($saved['id'] ?? 0)
        );

        return Response::redirect($this->base() . '/admin/timeoff?success=created');
    }

    public function approveTimeoff(Request $request): Response
    {
        $req = $this->timeoffRequests->findById((int) $request->param('id'));
        if ($req === null) {
            throw new NotFoundException(__('timeoff_error_request_not_found'));
        }
        $this->assertStoreAccess($request, (int) ($req['store_id'] ?? 0));
        // Seule une demande en attente se décide : sinon un gérant ressuscitait une demande annulée
        // par l'employé, ou ré-approuvait (double notification).
        if (($req['status'] ?? '') !== 'pending') {
            return Response::redirect($this->base() . '/admin/timeoff?error=invalid_state');
        }
        $decided = $this->decision($request, $req, 'approved');
        $this->timeoffRequests->save($decided);
        $this->auditLogger->logUpdate($request, 'timeoff.approved', 'timeoff_request', (int) $req['id'], $req, $decided, [], (int) ($req['store_id'] ?? 0) ?: null);
        $this->notifs->notify(
            (int) $req['user_id'],
            'timeoff_approved',
            'notif_timeoff_approved_body',
            $this->timeoffReplace($req['type'] ?? '', $req['start_date'] ?? '', $req['end_date'] ?? ''),
            (int) $req['id'],
            '/employee/timeoff?highlight=' . (int) $req['id']
        );
        return Response::redirect($this->base() . '/admin/timeoff?success=approved');
    }

    public function refuseTimeoff(Request $request): Response
    {
        $req = $this->timeoffRequests->findById((int) $request->param('id'));
        if ($req === null) {
            throw new NotFoundException(__('timeoff_error_request_not_found'));
        }
        $this->assertStoreAccess($request, (int) ($req['store_id'] ?? 0));
        if (($req['status'] ?? '') !== 'pending') {
            return Response::redirect($this->base() . '/admin/timeoff?error=invalid_state');
        }
        $decided = $this->decision($request, $req, 'refused');
        $this->timeoffRequests->save($decided);
        $this->auditLogger->logUpdate($request, 'timeoff.refused', 'timeoff_request', (int) $req['id'], $req, $decided, [], (int) ($req['store_id'] ?? 0) ?: null);
        $this->notifs->notify(
            (int) $req['user_id'],
            'timeoff_refused',
            'notif_timeoff_refused_body',
            $this->timeoffReplace($req['type'] ?? '', $req['start_date'] ?? '', $req['end_date'] ?? ''),
            (int) $req['id'],
            '/employee/timeoff?highlight=' . (int) $req['id']
        );
        return Response::redirect($this->base() . '/admin/timeoff?success=refused');
    }

    /**
     * Demande décidée : nouveau statut, avec qui l'a traitée et quand (comme l'API et « Ajouter un congé »).
     *
     * @param array<string, mixed> $req
     * @return array<string, mixed>
     */
    private function decision(Request $request, array $req, string $status): array
    {
        $authUser = (array) ($request->getAttribute('auth_user') ?? []);
        return array_merge($req, [
            'status'       => $status,
            'processed_by' => isset($authUser['id']) ? (int) $authUser['id'] : null,
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * POST /admin/timeoff/{id}/delete — supprime définitivement un congé
     * (quel que soit son statut), y compris pour retirer un congé approuvé du calendrier.
     */
    public function deleteTimeoff(Request $request): Response
    {
        $req = $this->timeoffRequests->findById((int) $request->param('id'));
        if ($req === null) {
            throw new NotFoundException(__('timeoff_error_request_not_found'));
        }
        $this->assertStoreAccess($request, (int) ($req['store_id'] ?? 0));

        $this->auditLogger->log($request, 'timeoff.deleted', 'timeoff_request', (int) $req['id'], [
            'user_id' => $req['user_id'] ?? null,
            'type'    => $req['type'] ?? null,
            'start'   => $req['start_date'] ?? null,
            'end'     => $req['end_date'] ?? null,
            'was_status' => $req['status'] ?? null,
        ], (int) ($req['store_id'] ?? 0) ?: null);

        $this->timeoffRequests->delete((int) $req['id']);

        return Response::redirect($this->base() . '/admin/timeoff?success=deleted');
    }

    /**
     * Magasin d'un congé saisi par un gérant : celui posté s'il fait partie des magasins de
     * l'employé, sinon le premier de ses magasins que le gérant gère (avant : toujours la
     * première adhésion, donc un 403 pour le gérant du second magasin).
     *
     * @throws ValidationException
     */
    private function resolveStoreId(Request $request, int $userId): int
    {
        $storeIds = array_values(array_unique(array_map(
            fn(array $m): int => (int) $m['store_id'],
            $this->storeUsers->findByUser($userId)
        )));
        if ($storeIds === []) {
            throw new ValidationException(['user_id' => __('timeoff_error_no_store')]);
        }
        $requested = (int) $request->post('store_id', 0);
        if ($requested > 0) {
            if (!in_array($requested, $storeIds, true)) {
                throw new ValidationException(['store_id' => __('timeoff_error_store_not_member')]);
            }
            return $requested;
        }
        $managed = $this->managedIds($request);
        foreach ($storeIds as $sid) {
            if ($managed === null || in_array($sid, $managed, true)) {
                return $sid;
            }
        }
        return $storeIds[0];
    }

    /**
     * Dates d'une demande de congé : format Y-m-d obligatoire, fin >= début (fin absente = début).
     * Avant : n'importe quelle chaîne était enregistrée et s'affichait telle quelle au calendrier.
     *
     * @return array{0: string, 1: string} [début, fin]
     * @throws ValidationException
     */
    private function validDates(mixed $start, mixed $end): array
    {
        $parse = static function (mixed $value): ?\DateTimeImmutable {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', is_string($value) ? trim($value) : '');
            return ($date !== false && $date->format('Y-m-d') === trim((string) $value)) ? $date : null;
        };
        $startDate = $parse($start);
        $endDate   = ($end === null || trim((string) $end) === '') ? $startDate : $parse($end);
        if ($startDate === null || $endDate === null) {
            throw new ValidationException(['start_date' => __('timeoff_error_invalid_date')]);
        }
        if ($endDate < $startDate) {
            throw new ValidationException(['end_date' => __('timeoff_error_end_before_start')]);
        }
        return [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
    }

    /** Valeurs de remplacement (:type/:start/:end) pour les notifications de congé. */
    private function timeoffReplace(string $type, string $startDate, string $endDate): array
    {
        return [
            'type'  => __($type),
            'start' => $startDate,
            'end'   => $endDate,
        ];
    }
}
