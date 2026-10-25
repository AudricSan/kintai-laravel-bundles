<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Timeclock\Controllers\Api;

use kintai\Contract\Http\Paginator;
use kintai\Contract\Auth\PermissionService;
use kintai\Contract\Exceptions\ConflictException;
use kintai\Contract\Exceptions\NotFoundException;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Bundles\Installed\Timeclock\Repositories\TimeclockRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;

/**
 * Régression (audit RBAC du 11/09/2026) : mêmes correctifs que
 * ShiftSwapRequestController — voir son docblock pour le détail de la faille.
 */
final class TimeclockController
{
    public function __construct(
        private readonly TimeclockRepositoryInterface $timeclocks,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly AuditLogger $auditLogger,
        private readonly PermissionService $permissions,
    ) {}

    /** GET /api/v1/timeclocks?store_id=X&user_id=Y&date=Z&page=1&limit=20 */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $storeId = $request->query('store_id');
        $userId  = $request->query('user_id');
        $date    = $request->query('date');

        if ($storeId !== null && $date !== null) {
            $items = $this->timeclocks->findByStoreAndDate((int) $storeId, $date);
        } elseif ($userId !== null && $date !== null) {
            $items = $this->timeclocks->findByUserAndDate((int) $userId, $date);
        } elseif ($storeId !== null) {
            $items = $this->timeclocks->findByStore((int) $storeId);
        } elseif ($userId !== null) {
            $items = $this->timeclocks->findByUser((int) $userId);
        } else {
            $items = [];
        }

        // Éléments couverts par la permission, PLUS ceux de l'appelant : un employé sans timeclock.view lit les siens avec
        // ?user_id=<lui> (règle self de la route). Avant, restrictToScope() vidait toujours sa liste.
        $authUser  = $this->authUser($request);
        $authId    = (int) ($authUser['id'] ?? 0);
        $scopedIds = array_column($this->permissions->restrictToScope($authUser, 'timeclock.view', $items), 'id');
        $items     = array_values(array_filter(
            $items,
            fn(array $i): bool => in_array($i['id'] ?? null, $scopedIds, true) || (int) ($i['user_id'] ?? 0) === $authId,
        ));

        return Response::json(Paginator::paginate($items, $page, $limit));
    }

    /** GET /api/v1/timeclocks/{id} */
    public function show(Request $request): Response
    {
        $record = $this->requireTimeclock($request, 'timeclock.view');
        return Response::json($record);
    }

    /** POST /api/v1/timeclocks/clock-in */
    public function clockIn(Request $request): Response
    {
        $data   = $request->json() ?? [];
        $userId = (int) ($data['user_id'] ?? 0);

        if ($this->timeclocks->findActiveByUser($userId) !== null) {
            throw new ConflictException(__('timeclock_error_clockin_already_active'));
        }

        $storeId = (int) ($data['store_id'] ?? 0);
        if ($storeId === 0) {
            $memberships = $this->storeUsers->findByUser($userId);
            $storeId     = $memberships ? (int) $memberships[0]['store_id'] : 0;
        }
        // Audit du 03/10/2026 : le store_id du corps n'était jamais comparé aux magasins de l'employé.
        if ($storeId > 0 && $this->storeUsers->findMembership($storeId, $userId) === null) {
            throw new \kintai\Contract\Exceptions\ForbiddenException(__('error_access_denied'));
        }

        $now    = date('Y-m-d H:i:s');
        $record = $this->timeclocks->save([
            'user_id'        => $userId,
            'store_id'       => $storeId,
            'shift_date'     => date('Y-m-d'),
            'clock_in_time'  => $now,
            'clock_out_time' => null,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        $this->auditLogger->log($request, 'timeclock.clock_in', 'timeclock', resourceId: (int) ($record['id'] ?? 0) ?: null, details: ['user_id' => $userId], storeId: $storeId);
        return Response::json($record, 201);
    }

    /** POST /api/v1/timeclocks/clock-out */
    public function clockOut(Request $request): Response
    {
        $data   = $request->json() ?? [];
        $userId = (int) ($data['user_id'] ?? 0);

        $active = $this->timeclocks->findActiveByUser($userId);
        if ($active === null) {
            throw new NotFoundException(__('timeclock_error_no_active_clockin'));
        }

        $now          = date('Y-m-d H:i:s');
        $clockInTime  = new \DateTimeImmutable($active['clock_in_time']);
        $clockOutTime = new \DateTimeImmutable($now);
        $duration     = (int) round(($clockOutTime->getTimestamp() - $clockInTime->getTimestamp()) / 60);

        $record = $this->timeclocks->save(array_merge($active, [
            'clock_out_time'   => $now,
            'duration_minutes' => $duration,
            'updated_at'       => $now,
        ]));
        $this->auditLogger->logUpdate($request, 'timeclock.clock_out', 'timeclock', resourceId: (int) ($record['id'] ?? 0) ?: null, oldData: $active, newData: $record, extraContext: ['user_id' => $userId], storeId: isset($active['store_id']) ? (int) $active['store_id'] : null);
        return Response::json($record);
    }

    /** PUT /api/v1/timeclocks/{id} */
    public function update(Request $request): Response
    {
        $old = $this->requireTimeclock($request, 'timeclock.update');
        $id  = (int) $old['id'];

        // Audit du 03/10/2026 : l'employé et le magasin du pointage ne changent pas (déplacer l'entrée vers un magasin tiers contournait requireTimeclock()).
        $body = $request->json() ?? [];
        unset($body['user_id'], $body['store_id'], $body['created_at']);
        $data   = array_merge($body, ['id' => $id, 'updated_at' => date('Y-m-d H:i:s')]);
        $record = $this->timeclocks->save($data);

        if (!empty($data['clock_in_time']) && !empty($data['clock_out_time'])) {
            $in       = new \DateTimeImmutable($record['clock_in_time']);
            $out      = new \DateTimeImmutable($record['clock_out_time']);
            $duration = (int) round(($out->getTimestamp() - $in->getTimestamp()) / 60);
            $record   = $this->timeclocks->save(array_merge($record, ['duration_minutes' => $duration]));
        }

        $this->auditLogger->logUpdate($request, 'timeclock.updated', 'timeclock', resourceId: $id, oldData: $old, newData: $record, extraContext: $body, storeId: (int) ($old['store_id'] ?? 0) ?: null);
        return Response::json($record);
    }

    /** DELETE /api/v1/timeclocks/{id} */
    public function destroy(Request $request): Response
    {
        $record = $this->requireTimeclock($request, 'timeclock.delete');
        $id     = (int) $record['id'];
        $this->timeclocks->delete($id);
        $this->auditLogger->log($request, 'timeclock.deleted', 'timeclock', resourceId: $id);
        return Response::empty();
    }

    private function authUser(Request $request): array
    {
        return $request->getAttribute('auth_user') ?? [];
    }

    /** Charge l'entrée par id et vérifie $permissionKey sur son store réel. */
    private function requireTimeclock(Request $request, string $permissionKey): array
    {
        return $this->permissions->requireOwnedResource(
            $this->authUser($request),
            fn(int $id) => $this->timeclocks->findById($id),
            (int) $request->param('id'),
            $permissionKey,
            notFoundMessage: __('timeclock_error_entry_not_found'),
        );
    }
}
