<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\Timeclock\Controllers\Web;

use kintai\Contract\Exceptions\NotFoundException;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Bundles\Installed\Timeclock\Repositories\TimeclockRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;
use kintai\Contract\Http\Concerns\HasAdminAccess;
use kintai\Contract\View\ViewRenderer;

/**
 * Régression (audit RBAC du 11/09/2026) : timeclocksEdit()/timeclocksDelete()
 * n'appelaient jamais assertStoreAccess() (contrairement aux contrôleurs sœurs
 * ShiftSwap/ShiftClaim/TimeOff) — un manager restreint à un store pouvait
 * éditer/supprimer n'importe quel pointage de n'importe quel autre store
 * directement depuis /admin/timeclocks.
 */
final class AdminTimeclockController
{
    use HasAdminAccess;

    private const PER_PAGE = 20;

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly TimeclockRepositoryInterface $timeclocks,
        private readonly AuditLogger $auditLogger,
        private readonly StoreRepositoryInterface $stores,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function timeclocks(Request $request): Response
    {
        $managedIds = $this->managedIds($request);

        $usersMap  = $this->buildUsersMap();
        $storesMap = $this->buildStoresMap($managedIds);

        $filterDate    = trim((string) $request->query('date', ''));
        $filterStoreId = ($request->query('store_id') ?: null);

        // findAll() + filtrage en mémoire (comme les bundles sœurs ShiftSwap/TimeOff) plutôt que
        // findByStoreAndDate() directement sur store_id fourni par l'utilisateur : ce dernier ne
        // vérifiait pas managedIds, un manager pouvait donc lire les pointages d'un store qu'il
        // ne gère pas simplement en changeant store_id dans l'URL.
        $entries = $this->filterByStore($this->timeclocks->findAll(), $managedIds);

        if ($filterStoreId !== null) {
            $entries = array_values(array_filter($entries, fn($e) => (int) ($e['store_id'] ?? 0) === (int) $filterStoreId));
        }
        if ($filterDate !== '') {
            $entries = array_values(array_filter($entries, fn($e) => ($e['shift_date'] ?? '') === $filterDate));
        }

        usort($entries, fn($a, $b) => strcmp($b['clock_in_time'] ?? '', $a['clock_in_time'] ?? ''));

        $total      = count($entries);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page       = min($totalPages, max(1, (int) ($request->query('page') ?? 1)));
        $entries    = array_slice($entries, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        return Response::html($this->view->render('timeclock::timeclocks', [
            'title'           => __('timeclock_timeclocks'),
            'entries'         => $entries,
            'users_map'       => $usersMap,
            'stores_map'      => $storesMap,
            'filter_date'     => $filterDate,
            'filter_store_id' => $filterStoreId !== null ? (string) $filterStoreId : '',
            'total'           => $total,
            'page'            => $page,
            'total_pages'     => $totalPages,
        ], 'layout.app'));
    }

    public function timeclocksEdit(Request $request): Response
    {
        $id = (int) $request->param('id');

        $entry = $this->timeclocks->findById($id);
        if ($entry === null) {
            throw new NotFoundException(__('timeclock_error_entry_not_found'));
        }
        $this->assertStoreAccess($request, (int) ($entry['store_id'] ?? 0));

        $clockIn  = trim((string) $request->post('clock_in_time',  ''));
        $clockOut = trim((string) $request->post('clock_out_time', ''));

        $updated = array_merge($entry, [
            'clock_in_time'  => $clockIn  !== '' ? $clockIn  : $entry['clock_in_time'],
            'clock_out_time' => $clockOut !== '' ? $clockOut : null,
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        if (!empty($updated['clock_in_time']) && !empty($updated['clock_out_time'])) {
            $in       = new \DateTimeImmutable($updated['clock_in_time']);
            $out      = new \DateTimeImmutable($updated['clock_out_time']);
            $duration = (int) round(($out->getTimestamp() - $in->getTimestamp()) / 60);
            $updated['duration_minutes'] = max(0, $duration);
        } elseif (empty($updated['clock_out_time'])) {
            $updated['duration_minutes'] = null;
        }

        $this->timeclocks->save($updated);
        $this->auditLogger->logUpdate($request, 'timeclock.edited', 'timeclock', (int) $entry['id'], $entry, $updated, [], (int) ($entry['store_id'] ?? 0) ?: null);

        $date = $request->post('redirect_date', date('Y-m-d'));
        return Response::redirect($this->base() . '/admin/timeclocks?date=' . urlencode($date) . '&success=edited');
    }

    public function timeclocksDelete(Request $request): Response
    {
        $id = (int) $request->param('id');

        $entry = $this->timeclocks->findById($id);
        if ($entry === null) {
            throw new NotFoundException(__('timeclock_error_entry_not_found'));
        }

        $storeId = (int) ($entry['store_id'] ?? 0);
        $this->assertStoreAccess($request, $storeId);
        $this->auditLogger->log($request, 'timeclock.deleted', 'timeclock', $id, [
            'store_id' => $storeId ?: null,
            'date'     => $entry['clock_in_date'] ?? '',
        ], $storeId ?: null);

        $this->timeclocks->delete($id);

        $date = $request->query('date', date('Y-m-d'));
        return Response::redirect($this->base() . '/admin/timeclocks?date=' . urlencode($date) . '&success=deleted');
    }
}
