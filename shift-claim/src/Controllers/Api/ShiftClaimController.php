<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\ShiftClaim\Controllers\Api;

use kintai\Contract\Http\Paginator;
use kintai\Contract\Auth\PermissionService;
use kintai\Contract\Exceptions\ForbiddenException;
use kintai\Contract\Exceptions\ValidationException;
use kintai\Bundles\Installed\ShiftClaim\Repositories\ShiftClaimRepositoryInterface;
use kintai\Bundles\Installed\ShiftClaim\Services\ShiftClaimApproval;
use kintai\Contract\Data\ShiftRepositoryInterface;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;

/**
 * Régression (audit RBAC du 11/09/2026) : mêmes correctifs que
 * ShiftSwapRequestController — voir son docblock pour le détail de la faille.
 * index() sans filtre renvoyait en plus TOUTES les candidatures de TOUS les
 * stores à n'importe quel porteur de token (findAll() sans restriction).
 *
 * Régression (audit du 03/10/2026) : store() et update() fusionnaient le JSON brut du client. La
 * route de création est en libre-service (`self => user_id`), donc un employé pouvait poster
 * `status: approved`, `resolved_by`, un `store_id` qui n'est pas le sien, ou un `id` pour écraser la
 * candidature d'un collègue (save() fait un upsert sur l'id). Seuls les champs de la liste blanche
 * sont acceptés ; le statut initial est `pending`, et seul update() (open_shifts.approve sur le
 * magasin réel) résout une candidature.
 */
final class ShiftClaimController
{
    /** Champs qu'un client peut renseigner à la création (user_id, store_id, status et claimed_at sont imposés ou contrôlés). */
    private const CREATE_FIELDS = ['shift_id', 'note'];

    /** Champs qu'un gestionnaire peut modifier ; le shift, le candidat et le magasin ne changent jamais. */
    private const UPDATE_FIELDS = ['status', 'note'];

    public function __construct(
        private readonly ShiftClaimRepositoryInterface $claims,
        private readonly PermissionService $permissions,
        private readonly StoreUserRepositoryInterface $storeUsers,
        // Résolus par le conteneur ; facultatifs pour les tests qui ne touchent ni à la création ni à l'approbation.
        private readonly ?ShiftRepositoryInterface $shifts = null,
        private readonly ?ShiftClaimApproval $approval = null,
    ) {}

    /** GET /api/v1/shift-claims?store_id=X&user_id=Y&shift_id=Z&status=W&page=1&limit=20 */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $storeId = $request->query('store_id');
        $userId  = $request->query('user_id');
        $shiftId = $request->query('shift_id');
        $status  = $request->query('status');

        if ($shiftId !== null) {
            $items = $this->claims->findByShift((int) $shiftId);
        } elseif ($userId !== null) {
            $items = $this->claims->findByUser((int) $userId);
        } elseif ($storeId !== null && $status === 'pending') {
            $items = $this->claims->findPendingByStore((int) $storeId);
        } elseif ($storeId !== null) {
            $items = $this->claims->findByStore((int) $storeId);
        } else {
            $items = $this->claims->findAll();
        }

        // Éléments couverts par la permission, PLUS ceux de l'appelant : un employé sans open_shifts.view lit les siens avec
        // ?user_id=<lui> (règle self de la route). Avant, restrictToScope() vidait toujours sa liste.
        $authUser  = $this->authUser($request);
        $authId    = (int) ($authUser['id'] ?? 0);
        $scopedIds = array_column($this->permissions->restrictToScope($authUser, 'open_shifts.view', $items), 'id');
        $items     = array_values(array_filter(
            $items,
            fn(array $i): bool => in_array($i['id'] ?? null, $scopedIds, true) || (int) ($i['user_id'] ?? 0) === $authId,
        ));

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/shift-claims/{id} */
    public function show(Request $request): Response
    {
        $claim = $this->requireClaim($request, 'open_shifts.view');
        return Response::json($claim);
    }

    /** POST /api/v1/shift-claims */
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

        // Pour soi-même : membre du magasin suffit. Pour un tiers : open_shifts.approve sur ce magasin.
        $forSelf = $userId === $authId && $this->storeUsers->findMembership($storeId, $authId) !== null;
        if (!$forSelf && !$this->permissions->can($authUser, 'open_shifts.approve', $storeId)) {
            throw new ForbiddenException(__('error_permission_insufficient', ['key' => 'open_shifts.approve']));
        }

        // Mêmes règles que la candidature web (audit n°8) : avant, n'importe quel shift_id était accepté, publié ou
        // non, d'un autre magasin, déjà attribué au candidat, ou en double.
        $shiftId = (int) ($body['shift_id'] ?? 0);
        $shift   = $shiftId > 0 ? $this->shifts()->findById($shiftId) : null;
        if ($shift === null || !(int) ($shift['is_open'] ?? 0) || (int) ($shift['store_id'] ?? 0) !== $storeId) {
            throw new ValidationException(['shift_id' => __('shift_claim_error_open_shift_unavailable')]);
        }
        if ((int) ($shift['user_id'] ?? 0) === $userId) {
            throw new ValidationException(['shift_id' => __('shift_claim_error_open_shift_own')]);
        }
        $existing = $this->claims->findByUserAndShift($userId, $shiftId);
        if ($existing !== null && in_array($existing['status'] ?? '', ['pending', 'approved'], true)) {
            throw new ValidationException(['shift_id' => __('shift_claim_already_claimed')]);
        }

        $data = array_intersect_key($body, array_flip(self::CREATE_FIELDS)) + [
            'user_id'    => $userId,
            'store_id'   => $storeId,
            'status'     => 'pending',
            'claimed_at' => date('Y-m-d H:i:s'),
        ];
        return Response::json($this->claims->save($data), 201);
    }

    /** PUT /api/v1/shift-claims/{id} */
    public function update(Request $request): Response
    {
        $claim = $this->requireClaim($request, 'open_shifts.approve');
        $id    = (int) $claim['id'];
        $data = array_intersect_key($request->json() ?? [], array_flip(self::UPDATE_FIELDS)) + ['id' => $id];

        // Approuver = attribuer le shift et fermer la bourse, comme le web (avant : statut changé, shift intact).
        if (($data['status'] ?? null) === 'approved') {
            $shift = $this->shifts()->findById((int) ($claim['shift_id'] ?? 0));
            [$result, $saved] = $shift === null
                ? [ShiftClaimApproval::ALREADY_RESOLVED, null]
                : $this->approval()->approve($claim, $shift, (int) ($this->authUser($request)['id'] ?? 0));
            if ($result !== ShiftClaimApproval::APPROVED) {
                return Response::json(['error' => __('shift_claim_error_open_shift_unavailable'), 'code' => 'ALREADY_RESOLVED'], 409);
            }
            return Response::json($this->claims->findById($id) ?? $saved);
        }
        // Une résolution est tracée avec son auteur et sa date.
        if (isset($data['status']) && $data['status'] !== 'pending') {
            $data['resolved_by'] = (int) ($this->authUser($request)['id'] ?? 0);
            $data['resolved_at'] = date('Y-m-d H:i:s');
        }
        return Response::json($this->claims->save($data));
    }

    /** DELETE /api/v1/shift-claims/{id} */
    public function destroy(Request $request): Response
    {
        $claim = $this->requireClaim($request, 'open_shifts.approve');
        $this->claims->delete((int) $claim['id']);
        return Response::empty();
    }

    private function shifts(): ShiftRepositoryInterface
    {
        return $this->shifts ?? throw new \LogicException('ShiftRepositoryInterface requis.');
    }

    private function approval(): ShiftClaimApproval
    {
        return $this->approval ?? throw new \LogicException('ShiftClaimApproval requis.');
    }

    private function authUser(Request $request): array
    {
        return $request->getAttribute('auth_user') ?? [];
    }

    /** Charge la candidature par id et vérifie $permissionKey sur le store réel du shift. */
    private function requireClaim(Request $request, string $permissionKey): array
    {
        return $this->permissions->requireOwnedResource(
            $this->authUser($request),
            fn(int $id) => $this->claims->findById($id),
            (int) $request->param('id'),
            $permissionKey,
            notFoundMessage: __('shift_claim_error_claim_not_found'),
        );
    }
}
