<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\DailyReport\Controllers\Api;

use kintai\Contract\Http\Paginator;
use kintai\Contract\Exceptions\NotFoundException;
use kintai\Bundles\Installed\DailyReport\Repositories\DailyReportRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportPermissionService;

/**
 * Régression (audit du 03/10/2026) : store() et update() fusionnaient le JSON brut du client. Un `id`
 * dans le corps d'un POST écrasait le rapport de quelqu'un d'autre (save() fait un upsert sur l'id, ce
 * qui contournait canEdit()), `author_id` était pris du client (un rapport pouvait être attribué à un
 * tiers), et update() laissait modifier `store_id`, `status`, la validation ou le PDF. Seuls les champs
 * métier de la liste blanche sont acceptés ; les transitions passent par submit()/validate().
 */
final class DailyReportController
{
    /** Champs de contenu qu'un client peut écrire (store_id, author_id, status et les champs de workflow sont imposés ou réservés). */
    private const CONTENT_FIELDS = ['report_date', 'sales_total', 'customer_count', 'labor_cost', 'waste_total', 'notes', 'data'];

    public function __construct(
        private readonly DailyReportRepositoryInterface $reports,
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly DailyReportPermissionService $permissions,
    ) {}

    /**
     * GET /api/v1/daily-reports
     * Filtres : store_id, date, status, from, to, author_id
     */
    public function index(Request $request): Response
    {
        [$page, $limit] = Paginator::params($request);
        $storeId  = $request->query('store_id');
        $date     = $request->query('date');
        $status   = $request->query('status');
        $from     = $request->query('from');
        $to       = $request->query('to');
        $authorId = $request->query('author_id');

        if ($storeId !== null && $from !== null && $to !== null) {
            $items = $this->reports->findByStoreAndDateRange((int) $storeId, $from, $to);
        } elseif ($storeId !== null && $date !== null) {
            $report = $this->reports->findByStoreAndDate((int) $storeId, $date);
            $items  = $report !== null ? [$report] : [];
        } elseif ($storeId !== null && $status !== null) {
            $items = $this->reports->findByStoreAndStatus((int) $storeId, $status);
        } elseif ($storeId !== null) {
            $items = $this->reports->findByStore((int) $storeId);
        } elseif ($authorId !== null) {
            $items = $this->reports->findByAuthor((int) $authorId);
        } else {
            $items = $this->reports->findAllActive();
        }

        return Response::json(Paginator::paginate($this->visibleTo($request, $items), $page, $limit));
    }

    /**
     * Ne garde que les rapports que l'appelant peut ouvrir un par un (même règle que show()) : sans store_id, ou avec
     * author_id, la liste renvoyait les rapports de TOUS les magasins (ventes, coût salarial) à n'importe quel compte
     * ayant daily_reports.view dans un seul magasin, et un simple membre voyait ceux de ses collègues.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function visibleTo(Request $request, array $items): array
    {
        $authUser = $request->getAttribute('auth_user') ?? [];
        $userId   = (int) ($authUser['id'] ?? 0);
        $contexts = [];

        return array_values(array_filter($items, function (array $report) use ($authUser, $userId, &$contexts): bool {
            $storeId = (int) ($report['store_id'] ?? 0);
            if (!array_key_exists($storeId, $contexts)) {
                $store = $this->stores->findById($storeId);
                $contexts[$storeId] = $store === null ? null : [$store, $this->storeUsers->findMembership($storeId, $userId)];
            }
            if ($contexts[$storeId] === null) {
                return false;
            }
            [$store, $membership] = $contexts[$storeId];
            return $this->permissions->canViewReport($authUser, $store, $report, $membership);
        }));
    }

    /** GET /api/v1/daily-reports/{id} */
    public function show(Request $request): Response
    {
        $report = $this->requireReport($request);
        [$store, $membership] = $this->storeContext($request, (int) $report['store_id']);

        $authUser = $request->getAttribute('auth_user') ?? [];
        if (!$this->permissions->canViewReport($authUser, $store, $report, $membership)) {
            return $this->forbidden();
        }

        return Response::json($report);
    }

    /** POST /api/v1/daily-reports */
    public function store(Request $request): Response
    {
        $body    = $request->json() ?? [];
        $storeId = (int) ($body['store_id'] ?? 0);
        [$store, $membership] = $this->storeContext($request, $storeId);

        $authUser = $request->getAttribute('auth_user') ?? [];
        if (!$this->permissions->canCreate($authUser, $store, $membership)) {
            return $this->forbidden();
        }

        $data = array_intersect_key($body, array_flip(self::CONTENT_FIELDS)) + [
            'store_id'   => $storeId,
            'status'     => 'draft',
            'author_id'  => (int) ($authUser['id'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        return Response::json($this->reports->save($data), 201);
    }

    /** PUT /api/v1/daily-reports/{id} */
    public function update(Request $request): Response
    {
        $report = $this->requireReport($request);
        [$store, $membership] = $this->storeContext($request, (int) $report['store_id']);

        $authUser = $request->getAttribute('auth_user') ?? [];
        if (!$this->permissions->canEdit($authUser, $store, $report, $membership)) {
            return $this->forbidden();
        }

        return Response::json($this->reports->save(array_intersect_key($request->json() ?? [], array_flip(self::CONTENT_FIELDS)) + [
            'id'         => (int) $report['id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]));
    }

    /** DELETE /api/v1/daily-reports/{id} */
    public function destroy(Request $request): Response
    {
        $report = $this->requireReport($request);
        [$store, $membership] = $this->storeContext($request, (int) $report['store_id']);

        $authUser = $request->getAttribute('auth_user') ?? [];
        if (!$this->permissions->canDelete($authUser, $store, $report, $membership)) {
            return $this->forbidden();
        }

        $this->reports->delete((int) $report['id']);
        return Response::empty();
    }

    /** POST /api/v1/daily-reports/{id}/submit */
    public function submit(Request $request): Response
    {
        $report = $this->requireReport($request);
        [$store, $membership] = $this->storeContext($request, (int) $report['store_id']);

        $authUser = $request->getAttribute('auth_user') ?? [];
        if (!$this->permissions->canSubmit($authUser, $store, $report, $membership)) {
            return $this->forbidden();
        }

        return Response::json($this->reports->save(array_merge($report, [
            'status'       => 'submitted',
            'submitted_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ])));
    }

    /** POST /api/v1/daily-reports/{id}/validate */
    public function validate(Request $request): Response
    {
        $report = $this->requireReport($request);
        [$store, $membership] = $this->storeContext($request, (int) $report['store_id']);

        $authUser = $request->getAttribute('auth_user') ?? [];
        if (!$this->permissions->canValidate($authUser, $store, $report, $membership)) {
            return $this->forbidden();
        }

        $now = date('Y-m-d H:i:s');

        return Response::json($this->reports->save(array_merge($report, [
            'status'       => 'validated',
            'validated_by' => (int) ($authUser['id'] ?? 0),
            'validated_at' => $now,
            'updated_at'   => $now,
        ])));
    }

    private function requireReport(Request $request): array
    {
        $report = $this->reports->findById((int) $request->param('id'));
        if ($report === null) {
            throw new NotFoundException(__('daily_report_error_report_not_found'));
        }
        return $report;
    }

    /** @return array{0: array, 1: array|null} [$store, $membership] */
    private function storeContext(Request $request, int $storeId): array
    {
        $store = $this->stores->findById($storeId);
        if ($store === null) {
            throw new NotFoundException(__('error_store_not_found'));
        }
        $authUser   = $request->getAttribute('auth_user') ?? [];
        $membership = $this->storeUsers->findMembership($storeId, (int) ($authUser['id'] ?? 0));
        return [$store, $membership];
    }

    private function forbidden(): Response
    {
        return Response::json([
            'error' => __('daily_report_error_permission_insufficient'),
            'code'  => 'FORBIDDEN',
        ], 403);
    }
}
