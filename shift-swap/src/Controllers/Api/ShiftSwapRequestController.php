<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftSwap\Controllers\Api;

use kintai\Contract\Http\Paginator;
use kintai\Contract\Auth\PermissionService;
use kintai\Contract\Exceptions\ForbiddenException;
use kintai\Contract\Exceptions\ValidationException;
use kintai\Bundles\Installed\ShiftSwap\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;

/**
 * Régression (audit RBAC du 11/09/2026) : ApiPermissionMiddleware ne peut borner
 * swaps.* au store réel que si le client fournit lui-même store_id — sur les routes
 * par {id} (show/update/destroy) rien ne l'y oblige, et PermissionService::can()
 * traite l'absence de store comme "n'importe où". Un manager n'ayant swaps.* que sur
 * un store pouvait ainsi lire/modifier/supprimer une demande d'échange de n'importe
 * quel autre store. Corrigé via PermissionService::requireOwnedResource() (RBAC-V2),
 * qui re-vérifie la permission sur le store réel de la ressource après chargement.
 *
 * Régression (audit du 03/10/2026) : store() et update() fusionnaient le JSON brut du client. Un `id`
 * dans le corps d'un POST écrasait la demande de quelqu'un d'autre (save() fait un upsert sur l'id,
 * ce qui contournait requireSwap()), et `status`, `approved_by_id`… étaient acceptés tels quels.
 * Seuls les champs de la liste blanche sont maintenant acceptés ; le statut initial est `pending`.
 */
final class ShiftSwapRequestController
{
    /** Champs qu'un client peut renseigner à la création (store_id, requester_id, status et created_at sont imposés ou contrôlés). */
    private const CREATE_FIELDS = ['target_user_id', 'requester_shift_id', 'target_shift_id', 'reason'];

    /** Champs modifiables ensuite ; le magasin, le demandeur et les shifts ne changent jamais. */
    private const UPDATE_FIELDS = ['status', 'reason', 'target_user_id'];

    public function __construct(
        private readonly ShiftSwapRequestRepositoryInterface $swapRequests,
        private readonly AuditLogger $auditLogger,
        private readonly PermissionService $permissions,
    ) {}

    /** GET /api/v1/shift-swap-requests?store_id=X&requester_id=Y&user_id=U&status=Z&page=1&limit=20 (user_id : demandeur ou destinataire) */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $storeId     = $request->query('store_id');
        $requesterId = $request->query('requester_id');
        $involvedId  = $request->query('user_id');
        $status      = $request->query('status');

        if ($storeId !== null && $status !== null) {
            $items = $this->swapRequests->findByStatus((int) $storeId, $status);
        } elseif ($storeId !== null) {
            $items = $this->swapRequests->findByStore((int) $storeId);
        } elseif ($requesterId !== null) {
            $items = $this->swapRequests->findByRequester((int) $requesterId);
        } elseif ($involvedId !== null) {
            // Demandes où ce compte est demandeur ou destinataire (une seule fois chacune).
            $items = [];
            foreach (array_merge($this->swapRequests->findByRequester((int) $involvedId), $this->swapRequests->findByTarget((int) $involvedId)) as $s) {
                $items[(int) $s['id']] = $s;
            }
            $items = array_values($items);
        } else {
            $items = [];
        }

        // Éléments couverts par la permission, PLUS ceux de l'appelant : un employé sans swaps.view lit les siens avec
        // ?user_id=<lui> (règle self de la route). Avant, restrictToScope() vidait toujours sa liste.
        $authUser  = $this->authUser($request);
        $authId    = (int) ($authUser['id'] ?? 0);
        $scopedIds = array_column($this->permissions->restrictToScope($authUser, 'swaps.view', $items), 'id');
        $items     = array_values(array_filter(
            $items,
            fn(array $i): bool => in_array($i['id'] ?? null, $scopedIds, true) || (int) ($i['requester_id'] ?? 0) === $authId || (int) ($i['target_id'] ?? 0) === $authId,
        ));

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/shift-swap-requests/{id} */
    public function show(Request $request): Response
    {
        $item = $this->requireSwap($request, 'swaps.view');
        return Response::json($item);
    }

    /** POST /api/v1/shift-swap-requests */
    public function store(Request $request): Response
    {
        $authUser = $this->authUser($request);
        $body     = $request->json() ?? [];

        $storeId = (int) ($body['store_id'] ?? 0);
        if ($storeId <= 0) {
            throw new ValidationException(['store_id' => __('error_api_store_id_required')]);
        }
        // Le middleware ne voit le magasin que si le client l'a fourni : on revérifie sur celui qu'on va écrire.
        if (!$this->permissions->can($authUser, 'swaps.create', $storeId)) {
            throw new ForbiddenException(__('error_permission_insufficient', ['key' => 'swaps.create']));
        }

        $data = array_intersect_key($body, array_flip(self::CREATE_FIELDS)) + [
            'store_id'     => $storeId,
            'requester_id' => (int) ($body['requester_id'] ?? ($authUser['id'] ?? 0)),
            'status'       => 'pending',
            'created_at'   => date('Y-m-d H:i:s'),
        ];
        $saved = $this->swapRequests->save($data);
        $this->auditLogger->log($request, 'shift_swap_request.created', 'shift_swap_request', resourceId: (int) ($saved['id'] ?? 0) ?: null, details: $data, storeId: $storeId);
        return Response::json($saved, 201);
    }

    /** PUT /api/v1/shift-swap-requests/{id} */
    public function update(Request $request): Response
    {
        $old = $this->requireSwap($request, 'swaps.update');
        $id  = (int) $old['id'];
        $data = array_intersect_key($request->json() ?? [], array_flip(self::UPDATE_FIELDS)) + ['id' => $id];
        // Une approbation est tracée avec son auteur et sa date.
        if (($data['status'] ?? null) === 'approved') {
            $data['approved_by_id'] = (int) ($this->authUser($request)['id'] ?? 0);
            $data['approved_at']    = date('Y-m-d H:i:s');
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        $saved = $this->swapRequests->save($data);
        $this->auditLogger->logUpdate($request, 'shift_swap_request.updated', 'shift_swap_request', resourceId: $id, oldData: $old, newData: $saved, extraContext: $data, storeId: (int) ($old['store_id'] ?? 0) ?: null);
        return Response::json($saved);
    }

    /** DELETE /api/v1/shift-swap-requests/{id} */
    public function destroy(Request $request): Response
    {
        $item = $this->requireSwap($request, 'swaps.delete');
        $id   = (int) $item['id'];
        $this->swapRequests->delete($id);
        $this->auditLogger->log($request, 'shift_swap_request.deleted', 'shift_swap_request', resourceId: $id);
        return Response::empty();
    }

    private function authUser(Request $request): array
    {
        return $request->getAttribute('auth_user') ?? [];
    }

    /** Charge la demande par id et vérifie $permissionKey sur son store réel. */
    private function requireSwap(Request $request, string $permissionKey): array
    {
        return $this->permissions->requireOwnedResource(
            $this->authUser($request),
            fn(int $id) => $this->swapRequests->findById($id),
            (int) $request->param('id'),
            $permissionKey,
            notFoundMessage: 'Demande d\'échange introuvable.',
        );
    }
}
