<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\TimeOff\Controllers\Web;

use kintai\Contract\Auth\PermissionService;
use kintai\Contract\Exceptions\ForbiddenException;
use kintai\Contract\Exceptions\ValidationException;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Bundles\Installed\TimeOff\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;
use kintai\Contract\Services\NotificationService;
use kintai\Contract\Http\Concerns\HasBaseUrl;
use kintai\Contract\Http\Concerns\HasStoreFeatureCheck;
use kintai\Contract\View\ViewRenderer;

final class EmployeeTimeoffController
{
    use HasBaseUrl;
    use HasStoreFeatureCheck;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly TimeoffRequestRepositoryInterface $timeoffRequests,
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly AuditLogger $auditLogger,
        private readonly UserRepositoryInterface $users,
        private readonly NotificationService $notifs,
        private readonly PermissionService $permissions,
    ) {}

    public function timeoff(Request $request): Response
    {
        if (($resp = $this->assertFeature($request, 'timeoff')) !== null) {
            return $resp;
        }
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $requests = $this->timeoffRequests->findByUser($userId);
        usort($requests, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        $stores = [];
        foreach ($this->memberStoreIds($userId) as $sid) {
            $store = $this->stores->findById($sid);
            if ($store !== null) {
                $stores[] = ['id' => $sid, 'name' => $store['name'] ?? ('#' . $sid)];
            }
        }

        // L'employé voit ses demandes : les notifications de décision correspondantes sont lues.
        if ($userId > 0 && $requests !== []) {
            $this->notifs->markReadFor($userId, 'timeoff', array_map(fn($r) => (int) ($r['id'] ?? 0), $requests));
        }

        return Response::html($this->view->render('timeoff::employee-timeoff', [
            'title'    => 'Mes congés',
            'highlight_id' => (int) $request->query('highlight', 0),
            'requests' => $requests,
            'stores'   => $stores,
        ], 'layout.app'));
    }

    public function storeTimeoff(Request $request): Response
    {
        if (($resp = $this->assertFeature($request, 'timeoff')) !== null) {
            return $resp;
        }
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $storeId = $this->resolveStoreId($request->post('store_id'), $userId);

        $validTypes = ['vacation', 'sick', 'personal', 'unpaid', 'other'];
        $type       = $request->post('type', 'vacation');
        if (!in_array($type, $validTypes, true)) {
            $type = 'vacation';
        }
        [$startDate, $endDate] = $this->validDates($request->post('start_date', ''), $request->post('end_date', ''));
        $reason = $request->post('reason', '') ?: null;

        $savedTimeoff = $this->timeoffRequests->save([
            'store_id'   => $storeId,
            'user_id'    => $userId,
            'type'       => $type,
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'reason'     => $reason,
            'status'     => 'pending',
        ]);

        $this->auditLogger->log($request, 'timeoff.created', 'timeoff_request', (int) ($savedTimeoff['id'] ?? 0), [
            'type' => $type, 'start' => $startDate, 'end' => $endDate,
        ], $storeId ?: null);

        // Régression comblée : contrairement aux autres bundles (daily-report, feedback,
        // shift-claim, notebook), la soumission d'une demande de congé ne prévenait
        // jamais les managers — ils ne l'apprenaient qu'en consultant /admin/timeoff.
        if ($storeId > 0) {
            $this->notifyManagers($storeId, (int) ($savedTimeoff['id'] ?? 0), $userId, $type, $startDate, $endDate);
        }

        return Response::redirect($this->base() . '/employee/timeoff?success=created');
    }

    /** @return int[] Magasins dont l'employé est membre (le premier sert de défaut). */
    private function memberStoreIds(int $userId): array
    {
        return array_values(array_unique(array_map(
            fn(array $m): int => (int) $m['store_id'],
            $this->storeUsers->findByUser($userId)
        )));
    }

    /**
     * Magasin de la demande : celui choisi par l'employé s'il en est membre, sinon son premier
     * magasin. Un employé sans magasin ne peut pas déposer (avant : demande créée avec store_id = 0,
     * invisible de tous les gérants).
     *
     * @throws ValidationException
     */
    private function resolveStoreId(mixed $requested, int $userId): int
    {
        $storeIds = $this->memberStoreIds($userId);
        if ($storeIds === []) {
            throw new ValidationException(['store_id' => __('timeoff_error_no_store')]);
        }
        $requested = is_numeric($requested) ? (int) $requested : 0;
        if ($requested <= 0) {
            return $storeIds[0];
        }
        if (!in_array($requested, $storeIds, true)) {
            throw new ValidationException(['store_id' => __('timeoff_error_store_not_member')]);
        }
        return $requested;
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

    /** Notifie les membres du store détenant timeoff.approve qu'une demande attend une décision. */
    private function notifyManagers(int $storeId, int $referenceId, int $authorId, string $type, string $startDate, string $endDate): void
    {
        $recipients = [];
        foreach ($this->storeUsers->findByStore($storeId) as $m) {
            $uid = (int) $m['user_id'];
            $candidate = $this->users->findById($uid);
            if ($candidate !== null && $this->permissions->can($candidate, 'timeoff.approve', $storeId)) {
                $recipients[] = $uid;
            }
        }
        if ($recipients === []) {
            return;
        }

        $author     = $this->users->findById($authorId);
        $authorName = trim(($author['last_name'] ?? '') . ' ' . ($author['first_name'] ?? ''));
        $authorName = $authorName !== '' ? $authorName : ('#' . $authorId);

        $this->notifs->notifyMany(
            $recipients,
            'timeoff_submitted',
            'notif_timeoff_submitted_body',
            ['author' => $authorName, 'type' => __($type), 'start' => $startDate, 'end' => $endDate],
            $referenceId,
            '/admin/timeoff?highlight=' . $referenceId
        );
    }

    public function cancelTimeoff(Request $request): Response
    {
        $user   = $request->getAttribute('auth_user');
        $userId = (int) ($user['id'] ?? 0);

        $req = $this->timeoffRequests->findById((int) $request->param('id'));
        if ($req === null || (int) $req['user_id'] !== $userId) {
            throw new ForbiddenException(__('timeoff_error_request_not_found'));
        }
        if (($req['status'] ?? '') !== 'pending') {
            return Response::redirect($this->base() . '/employee/timeoff?error=not_pending');
        }

        $cancelledReq = array_merge($req, ['status' => 'cancelled']);
        $this->timeoffRequests->save($cancelledReq);
        $this->auditLogger->logUpdate($request, 'timeoff.cancelled', 'timeoff_request', (int) $req['id'], $req, $cancelledReq, []);
        return Response::redirect($this->base() . '/employee/timeoff?success=cancelled');
    }
}
