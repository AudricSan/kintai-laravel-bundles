<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\HiringReport\Controllers\Web;

use kintai\Contract\Exceptions\ValidationException;
use kintai\Bundles\Installed\HiringReport\Repositories\HiringReportRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;
use kintai\Contract\Http\Concerns\HasAdminAccess;
use kintai\Contract\Http\Concerns\HasStaffReportCrud;
use kintai\Contract\View\ViewRenderer;

final class AdminHiringReportController
{
    use HasAdminAccess;
    use HasStaffReportCrud;

    private const REPORT_TYPE = [
        'entity'    => 'hiring_report',
        'slug'      => 'hiring',
        'view'      => 'hiring-report::reports-hiring',
        'not_found' => 'hiring_report_error_not_found',
        'fields'    => [
            'employee_number'     => 'str',
            'employee_name'       => 'str',
            'furigana_last_name'  => 'str',
            'furigana_first_name' => 'str',
            'gender'              => 'str',
            'tax_classification'  => 'str',
            'birth_date'          => 'str',
            'hire_date'           => 'str',
            'education'           => 'str',
            'postal_code'         => 'str',
            'address'             => 'str',
            'phone'               => 'str',
            'mobile_phone'        => 'str',
            'email'               => 'str',
            'guarantor_name'      => 'str',
            'guarantor_phone'     => 'str',
            'store_name'          => 'str',
            'hired_by'            => 'str',
            'notes'               => 'str',
        ],
    ];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly StoreRepositoryInterface $stores,
        private readonly UserRepositoryInterface $users,
        private readonly HiringReportRepositoryInterface $hiringReports,
        private readonly AuditLogger $auditLogger,
        private readonly StoreUserRepositoryInterface $storeUsers,
    ) {}

    public function hiringReports(Request $request): Response
    {
        return $this->listReports($request, '採用報告書');
    }

    /**
     * Liste "tous les magasins" : facile d'accès pour repérer les nouveaux
     * employés sans devoir ouvrir chaque store un par un (voir la sidebar).
     */
    public function allHiringReports(Request $request): Response
    {
        [$allStores, $queryStoreIds, $filterStoreId] = $this->storesAndFilter($request);

        $filters = [];
        if ($filterStoreId > 0) {
            $filters['store_id'] = $filterStoreId;
        }
        $filterYear = $request->query('year', '');
        if ($filterYear !== '') {
            $filters['year'] = $filterYear;
        }
        $filterMonth = $request->query('month', '');
        if ($filterMonth !== '') {
            $filters['month'] = $filterMonth;
        }

        $reports = $this->hiringReports->findAll($queryStoreIds, $filters);

        return Response::html($this->view->render('hiring-report::reports-hiring', [
            'title'        => __('hiring_report_hiring_reports'),
            'stores'       => $allStores,
            'reports'      => $reports,
            'filter_store_id' => $filterStoreId,
            'filter_year'  => $filterYear,
            'filter_month' => $filterMonth,
        ], 'layout.app'));
    }

    public function createHiringReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $store = $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        return Response::html($this->view->render('hiring-report::reports-hiring-form', [
            'title' => '新規採用報告書 — ' . ($store['name'] ?? ''),
            'store' => $store,
            'users' => $this->storeMembersForReportForm($storeId),
            'mode'  => 'create',
            'report' => [],
        ], 'layout.app'));
    }

    public function storeHiringReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        $userId = (int) $request->post('user_id', 0);
        if ($userId > 0 && $this->storeUsers->findMembership($storeId, $userId) === null) {
            throw new ValidationException(['user_id' => __('hiring_report_error_hiring_user_not_in_store')]);
        }

        $data = array_merge([
            'store_id' => $storeId,
            'user_id'  => $userId > 0 ? $userId : null,
        ], $this->postData($request, self::REPORT_TYPE['fields']), [
            'created_by' => $request->getAttribute('auth_user')['id'] ?? 0,
        ]);

        $saved = $this->hiringReports->save($data);

        $this->auditLogger->log($request, 'hiring_report.created', 'hiring_report', (int) ($saved['id'] ?? 0), [
            'store_id' => $storeId,
            'employee_name' => $data['employee_name'],
        ]);

        return $this->redirectToList($storeId, 'hiring', 'created');
    }

    public function showHiringReport(Request $request): Response
    {
        return $this->showReport($request);
    }

    public function editHiringReport(Request $request): Response
    {
        return $this->editReport($request);
    }

    public function updateHiringReport(Request $request): Response
    {
        return $this->updateReport($request);
    }

    public function deleteHiringReport(Request $request): Response
    {
        return $this->deleteReport($request);
    }

    public function hiringReportPdf(Request $request): Response
    {
        return $this->reportPdf($request);
    }

    public function hiringReportPdfDownload(Request $request): Response
    {
        return $this->reportPdfDownload($request);
    }

    protected function reportRepo(): object
    {
        return $this->hiringReports;
    }

    protected function reportConfig(): array
    {
        return self::REPORT_TYPE;
    }

    protected function reportShowTitle(array $report): string
    {
        return '採用報告書 — ' . ($report['employee_name'] ?? '');
    }

    protected function reportEditTitle(): string
    {
        return '編集 — 採用報告書';
    }

    protected function reportEditExtras(int $storeId, array $report): array
    {
        return ['users' => $this->storeMembersForReportForm($storeId, (int) ($report['user_id'] ?? 0))];
    }
}
