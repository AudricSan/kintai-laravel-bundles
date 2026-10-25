<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\TimeOff\Controllers\Api;

use kintai\Contract\Http\Paginator;
use kintai\Contract\Auth\PermissionService;
use kintai\Contract\Exceptions\ForbiddenException;
use kintai\Contract\Exceptions\ValidationException;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Bundles\Installed\TimeOff\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;

/**
 * Régression (audit RBAC du 11/09/2026) : mêmes correctifs que
 * ShiftSwapRequestController — voir son docblock pour le détail de la faille.
 *
 * Régression (audit du 03/10/2026) : store() et update() fusionnaient le JSON brut du client. La
 * route de création est en libre-service (`self => user_id`, aucune permission exigée pour soi-même),
 * donc un employé pouvait poster `status: approved` (auto-approbation), `processed_by`, un `store_id`
 * qui n'est pas le sien, ou un `id` pour écraser la demande d'un collègue (save() fait un upsert sur
 * l'id). Seuls les champs de la liste blanche sont maintenant acceptés ; le statut initial est
 * toujours `pending` et seul update() (timeoff.update sur le magasin réel) traite une demande.
 */
final class TimeoffRequestController
{
    /** Champs qu'un client peut renseigner à la création (user_id, store_id, status et created_at sont imposés). */
    private const CREATE_FIELDS = ['start_date', 'end_date', 'type', 'reason'];

    /** Champs qu'un gestionnaire peut modifier ; user_id et store_id ne changent jamais. */
    private const UPDATE_FIELDS = ['start_date', 'end_date', 'type', 'reason', 'status', 'admin_note'];

    public function __construct(
        private readonly TimeoffRequestRepositoryInterface $timeoffRequests,
        private readonly AuditLogger $auditLogger,
        private readonly PermissionService $permissions,
        private readonly StoreUserRepositoryInterface $storeUsers,
    ) {}

    /** GET /api/v1/timeoff-requests?store_id=X&user_id=Y&status=Z&page=1&limit=20 */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $storeId = $request->query('store_id');
        $userId  = $request->query('user_id');
        $status  = $request->query('status');

        if ($storeId !== null && $status !== null) {
            $items = $this->timeoffRequests->findByStatus((int) $storeId, $status);
        } elseif ($storeId !== null) {
            $items = $this->timeoffRequests->findByStore((int) $storeId);
        } elseif ($userId !== null) {
            $items = $this->timeoffRequests->findByUser((int) $userId);
        } else {
            $items = [];
        }

        // Éléments couverts par la permission, PLUS ceux de l'appelant : un employé sans timeoff.view lit les siens avec
        // ?user_id=<lui> (règle self de la route). Avant, restrictToScope() vidait toujours sa liste.
        $authUser  = $this->authUser($request);
        $authId    = (int) ($authUser['id'] ?? 0);
        $scopedIds = array_column($this->permissions->restrictToScope($authUser, 'timeoff.view', $items), 'id');
        $items     = array_values(array_filter(
            $items,
            fn(array $i): bool => in_array($i['id'] ?? null, $scopedIds, true) || (int) ($i['user_id'] ?? 0) === $authId,
        ));

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/timeoff-requests/{id} */
    public function show(Request $request): Response
    {
        $item = $this->requireTimeoff($request, 'timeoff.view');
        return Response::json($item);
    }

    /** POST /api/v1/timeoff-requests */
    public function store(Request $request): Response
    {
        $authUser = $this->authUser($request);
        $authId   = (int) ($authUser['id'] ?? 0);
        $body     = $request->json() ?? [];

        $storeId = (int) ($body['store_id'] ?? 0);
        if ($storeId <= 0) {
            throw new ValidationException(['store_id' => __('error_api_store_id_required')]);
        }
        $userId = (int) ($body['user_id'] ?? $authId);

        // Pour soi-même : membre du magasin suffit. Pour un tiers : timeoff.create sur ce magasin.
        $forSelf = $userId === $authId && $this->storeUsers->findMembership($storeId, $authId) !== null;
        if (!$forSelf && !$this->permissions->can($authUser, 'timeoff.create', $storeId)) {
            throw new ForbiddenException(__('error_permission_insufficient', ['key' => 'timeoff.create']));
        }

        $data = array_intersect_key($body, array_flip(self::CREATE_FIELDS)) + [
            'user_id'    => $userId,
            'store_id'   => $storeId,
            'status'     => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $saved = $this->timeoffRequests->save($data);
        $this->auditLogger->log($request, 'timeoff_request.created', 'timeoff_request', resourceId: (int) ($saved['id'] ?? 0) ?: null, details: $data, storeId: $storeId);
        return Response::json($saved, 201);
    }

    /** PUT /api/v1/timeoff-requests/{id} */
    public function update(Request $request): Response
    {
        $old = $this->requireTimeoff($request, 'timeoff.update');
        $id  = (int) $old['id'];
        $data = array_intersect_key($request->json() ?? [], array_flip(self::UPDATE_FIELDS)) + ['id' => $id];
        // Un changement de statut est un traitement : on enregistre qui l'a fait et quand.
        if (isset($data['status'])) {
            $data['processed_by'] = (int) ($this->authUser($request)['id'] ?? 0);
            $data['processed_at'] = date('Y-m-d H:i:s');
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        $saved = $this->timeoffRequests->save($data);
        $this->auditLogger->logUpdate($request, 'timeoff_request.updated', 'timeoff_request', resourceId: $id, oldData: $old, newData: $saved, extraContext: $data, storeId: (int) ($old['store_id'] ?? 0) ?: null);
        return Response::json($saved);
    }

    /** DELETE /api/v1/timeoff-requests/{id} */
    public function destroy(Request $request): Response
    {
        $item = $this->requireTimeoff($request, 'timeoff.delete');
        $id   = (int) $item['id'];
        $this->timeoffRequests->delete($id);
        $this->auditLogger->log($request, 'timeoff_request.deleted', 'timeoff_request', resourceId: $id);
        return Response::empty();
    }

    private function authUser(Request $request): array
    {
        return $request->getAttribute('auth_user') ?? [];
    }

    /** Charge la demande par id et vérifie $permissionKey sur son store réel. */
    private function requireTimeoff(Request $request, string $permissionKey): array
    {
        return $this->permissions->requireOwnedResource(
            $this->authUser($request),
            fn(int $id) => $this->timeoffRequests->findById($id),
            (int) $request->param('id'),
            $permissionKey,
            notFoundMessage: 'Demande de congé introuvable.',
        );
    }
}
