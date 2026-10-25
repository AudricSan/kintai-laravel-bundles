<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\SalaryReport\Controllers\Web;

use kintai\Contract\Auth\PermissionService;
use kintai\Contract\Exceptions\ValidationException;
use kintai\Bundles\Installed\SalaryReport\Repositories\SalaryReportRepositoryInterface;
use kintai\Contract\Data\ShiftRepositoryInterface;
use kintai\Contract\Data\ShiftTypeRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Data\UserShiftTypeRateRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;
use kintai\Contract\Services\Sales;
use kintai\Contract\Services\ShiftWageCalculator;
use kintai\Contract\Services\StoreStatsServiceInterface;
use kintai\Contract\Http\Concerns\HasAdminAccess;
use kintai\Contract\Http\Concerns\HasStaffReportCrud;
use kintai\Contract\View\ViewRenderer;

final class AdminSalaryReportController
{
    use HasAdminAccess;
    use HasStaffReportCrud;

    private const REPORT_TYPE = [
        'entity'    => 'salary_report',
        'slug'      => 'salary',
        'view'      => 'salary-report::reports-salary',
        'not_found' => 'salary_report_error_not_found',
        'fields'    => [
            'target_month'          => 'str',
            'store_name'            => 'str',
            'employee_name'         => 'str',
            'person_in_charge'      => 'str',
            'total_payment'         => 'float',
            'total_deductions'      => 'float',
            'income_tax_base'       => 'float',
            'withholding_tax'       => 'float',
            'residence_tax'         => 'float',
            'other_deductions'      => 'float',
            'net_payment'           => 'float',
            'active_employees'      => 'int',
            'hand_delivered_salary' => 'float',
            'bank_transfer_salary'  => 'float',
            'staff_man_hours'       => 'float',
            'staff_total_payment'   => 'float',
            'staff_avg_hourly_wage' => 'float',
            'employee_work_hours'   => 'str',
            'new_hires'             => 'int',
            'resigned_staff'        => 'int',
            'hire_registrations'    => 'str',
            'remarks'               => 'str',
        ],
    ];

    public function __construct(
        private readonly ViewRenderer $view,
        private readonly StoreRepositoryInterface $stores,
        private readonly UserRepositoryInterface $users,
        private readonly SalaryReportRepositoryInterface $salaryReports,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly Sales $sales,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly UserShiftTypeRateRepositoryInterface $userRates,
        private readonly AuditLogger $auditLogger,
        private readonly StoreStatsServiceInterface $storeStatsService,
        private readonly PermissionService $permissions,
    ) {}

    public function allSalaryReports(Request $request): Response
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
        $filterPerson = trim($request->query('person', ''));
        if ($filterPerson !== '') {
            $filters['person_in_charge'] = $filterPerson;
        }

        $reports = $this->salaryReports->findAll($queryStoreIds, $filters);

        $membersByStore = [];
        foreach ($allStores as $s) {
            $sid = (int) $s['id'];
            $members = $this->storeMembersForReportForm($sid);
            if ($members !== []) {
                $membersByStore[$sid] = $members;
            }
        }

        return Response::html($this->view->render('salary-report::reports-salary', [
            'title'       => __('salary_report_sr_title'),
            'stores'      => $allStores,
            'store_members_by_store' => $membersByStore,
            'filter_store_id' => $filterStoreId,
            'filter_year' => $filterYear,
            'filter_month' => $filterMonth,
            'filter_person' => $filterPerson,
            'reports'     => $reports,
        ], 'layout.app'));
    }

    public function salaryReports(Request $request): Response
    {
        return $this->listReports($request, __('salary_report_sr_title'));
    }

    // -------------------------------------------------------------------------
    // Export de la liste "tous les magasins" (item 5)
    // -------------------------------------------------------------------------

    private function exportFilters(Request $request): array
    {
        $filters = [];
        $filterYear = $request->query('year', '');
        if ($filterYear !== '') {
            $filters['year'] = $filterYear;
        }
        $filterMonth = $request->query('month', '');
        if ($filterMonth !== '') {
            $filters['month'] = $filterMonth;
        }
        $filterPerson = trim($request->query('person', ''));
        if ($filterPerson !== '') {
            $filters['person_in_charge'] = $filterPerson;
        }
        return $filters;
    }

    public function exportSalaryReportsJson(Request $request): Response
    {
        [, $queryStoreIds] = $this->storesAndFilter($request);
        $reports = $this->salaryReports->findAll($queryStoreIds, $this->exportFilters($request));

        $this->auditLogger->log($request, 'export.salary_reports_json', 'salary_report', 0, [
            'count' => count($reports),
        ]);

        return Response::jsonDownload(['data' => $reports], 'salary_reports_' . date('Ymd') . '.json');
    }

    /**
     * Aperçu HTML du PDF (route .../export/pdf) : pas de téléchargement
     * automatique — voir HasStaffReportCrud::reportPdf() pour le même
     * principe appliqué aux rapports individuels.
     */
    public function exportSalaryReportsPdf(Request $request): Response
    {
        [$allStores, $queryStoreIds] = $this->storesAndFilter($request);
        $reports = $this->salaryReports->findAll($queryStoreIds, $this->exportFilters($request));
        $storeNames = array_column($allStores, 'name', 'id');

        $html = $this->view->render('salary-report::reports-salary-export-pdf', [
            'reports'      => $reports,
            'store_names'  => $storeNames,
            'generated_at' => date('Y-m-d H:i'),
            'downloadUrl'  => $this->base() . '/admin/reports/salary/export/pdf/download' . $this->exportQueryString($request),
        ]);

        return Response::html($html);
    }

    public function exportSalaryReportsPdfDownload(Request $request): Response
    {
        [$allStores, $queryStoreIds] = $this->storesAndFilter($request);
        $reports = $this->salaryReports->findAll($queryStoreIds, $this->exportFilters($request));
        $storeNames = array_column($allStores, 'name', 'id');

        $html = $this->view->render('salary-report::reports-salary-export-pdf', [
            'reports'      => $reports,
            'store_names'  => $storeNames,
            'generated_at' => date('Y-m-d H:i'),
        ]);

        $this->auditLogger->log($request, 'export.salary_reports_pdf', 'salary_report', 0, [
            'count' => count($reports),
        ]);

        return $this->renderPdf($html, 'salary_reports_' . date('Ymd') . '.pdf');
    }

    private function exportQueryString(Request $request): string
    {
        $query = array_filter([
            'store_id' => (int) $request->query('store_id', 0) ?: null,
            'year'     => $request->query('year', '') ?: null,
            'month'    => $request->query('month', '') ?: null,
            'person'   => $request->query('person', '') ?: null,
        ]);
        return $query ? '?' . http_build_query($query) : '';
    }

    public function createSalaryReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $store = $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        $authUser = $request->getAttribute('auth_user');
        $targetMonth = date('Y-m');

        $userId = (int) ($request->query('user_id') ?? 0);
        $employee = $userId > 0 ? $this->users->findById($userId) : null;
        if ($employee === null) {
            $userId = 0;
        }

        $from = $targetMonth . '-01';
        $to = date('Y-m-t', strtotime($from));
        $preset = $this->calculateSalaryPreset($store, $from, $to, $authUser, $userId ?: null);
        $preset['target_month'] = $targetMonth;
        if ($employee !== null) {
            $preset['user_id'] = $userId;
            $preset['employee_name'] = trim(($employee['last_name'] ?? '') . ' ' . ($employee['first_name'] ?? ''))
                ?: ($employee['display_name'] ?? '');
        }

        $managers = $this->getManagersForReportForm($storeId, $userId);
        $authName = trim(($authUser['last_name'] ?? '') . ' ' . ($authUser['first_name'] ?? '')) ?: ($authUser['display_name'] ?? '');
        $isManager = !empty(array_filter($managers, fn($m) => (int) $m['id'] === (int) ($authUser['id'] ?? 0)));
        if ($isManager && empty($preset['person_in_charge'])) {
            $preset['person_in_charge'] = $authName;
        }

        return Response::html($this->view->render('salary-report::reports-salary-form', [
            'title'   => __('salary_report_sr_new') . ' — ' . ($store['name'] ?? ''),
            'store'   => $store,
            'mode'    => 'create',
            'report'  => $preset,
            'managers' => $managers,
        ], 'layout.app'));
    }

    /**
     * Recalcule à la volée le résumé financier pour une plage de dates donnée
     * (appelé en AJAX depuis le formulaire quand le mois/la plage change —
     * voir public/assets/js/modules/salary-report-form.js). Ne persiste rien.
     */
    public function calculateSalaryReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $store = $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        $from = (string) $request->query('from', '');
        $to = (string) $request->query('to', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) {
            return Response::json(['error' => 'invalid_range'], 422);
        }

        $userId = (int) ($request->query('user_id') ?? 0) ?: null;
        $authUser = $request->getAttribute('auth_user');

        $preset = $this->calculateSalaryPreset($store, $from, $to, $authUser, $userId);
        $preset['target_month'] = substr($from, 0, 7);

        $detail = $userId !== null
            ? $this->storeStatsService->buildPayslipData($storeId, $userId, $from, $to)
            : $this->calculateStoreDetail($storeId, $from, $to);

        return Response::json(['preset' => $preset, 'detail' => $detail]);
    }

    public function storeSalaryReport(Request $request): Response
    {
        $storeId = (int) $request->param('id');
        $this->findStoreOrFail($storeId);
        $this->assertStoreAccess($request, $storeId);

        $targetMonth = $request->post('target_month', '');
        $userId = (int) $request->post('user_id', 0);
        if ($userId > 0 && $this->storeUsers->findMembership($storeId, $userId) === null) {
            throw new ValidationException(['user_id' => __('salary_report_error_salary_user_not_in_store')]);
        }

        $existing = $this->salaryReports->findByStoreAndMonth($storeId, $targetMonth, $userId ?: null);
        if ($existing !== null) {
            return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/reports/salary/' . $existing['id'] . '/edit?error=already_exists');
        }

        $data = array_merge([
            'store_id'     => $storeId,
            'user_id'      => $userId ?: null,
            'target_month' => $targetMonth,
        ], $this->postData($request, self::REPORT_TYPE['fields']), [
            'created_by' => $request->getAttribute('auth_user')['id'] ?? 0,
        ]);

        $saved = $this->salaryReports->save($data);

        $this->auditLogger->log($request, 'salary_report.created', 'salary_report', (int) ($saved['id'] ?? 0), [
            'store_id'     => $storeId,
            'target_month' => $targetMonth,
        ]);

        return $this->redirectToList($storeId, 'salary', 'created');
    }

    public function showSalaryReport(Request $request): Response
    {
        return $this->showReport($request);
    }

    public function editSalaryReport(Request $request): Response
    {
        return $this->editReport($request);
    }

    public function updateSalaryReport(Request $request): Response
    {
        return $this->updateReport($request);
    }

    public function deleteSalaryReport(Request $request): Response
    {
        return $this->deleteReport($request);
    }

    public function salaryReportPdf(Request $request): Response
    {
        return $this->reportPdf($request);
    }

    public function salaryReportPdfDownload(Request $request): Response
    {
        return $this->reportPdfDownload($request);
    }

    protected function reportRepo(): object
    {
        return $this->salaryReports;
    }

    protected function reportConfig(): array
    {
        return self::REPORT_TYPE;
    }

    protected function reportShowTitle(array $report): string
    {
        return __('salary_report_sr_title') . ' — ' . ($report['target_month'] ?? '');
    }

    protected function reportEditTitle(): string
    {
        return __('salary_report_sr_edit');
    }

    protected function reportEditExtras(int $storeId, array $report): array
    {
        return [
            'managers' => $this->getManagersForReportForm($storeId, (int) ($report['user_id'] ?? 0)),
        ];
    }

    /** Employés du store, pour le menu "créer un rapport pour cet employé" de la liste. */
    protected function reportListExtras(int $storeId): array
    {
        return [
            'store_members' => $this->storeMembersForReportForm($storeId),
        ];
    }

    /**
     * Pour un rapport lié à un employé, ajoute le détail jour par jour des shifts
     * et des retenues — exactement les données que produisait l'ancienne fiche de
     * paie autonome (StoreStatsService::buildPayslipData), maintenant affichées
     * directement dans le rapport de salaire (show + PDF) au lieu d'une page séparée.
     */
    protected function reportShowExtras(int $storeId, array $report): array
    {
        $userId = (int) ($report['user_id'] ?? 0);
        $targetMonth = (string) ($report['target_month'] ?? '');
        if ($userId <= 0 || $targetMonth === '') {
            return [];
        }

        $from = $targetMonth . '-01';
        $to = date('Y-m-t', strtotime($from));

        return $this->storeStatsService->buildPayslipData($storeId, $userId, $from, $to);
    }

    /**
     * Calcule les valeurs pré-remplies pour un rapport de salaire à partir
     * des rapports journaliers et des shifts existants.
     */
    /**
     * @param int|null $userId Rapport pour un seul employé (item 7) au lieu du magasin entier :
     *                         les heures/salaire ne portent que sur ses propres shifts, et le
     *                         total des ventes du magasin (sans rapport avec un seul employé)
     *                         n'est pas pré-rempli.
     */
    private function calculateSalaryPreset(array $store, string $from, string $to, array $authUser, ?int $userId = null): array
    {
        $storeId = (int) $store['id'];
        $preset = [
            'person_in_charge'  => $authUser['display_name'] ?? '',
        ];

        $totalPayment = 0;
        if ($userId === null) {
            // Total des ventes des jours validés/soumis, lu auprès des fournisseurs de ventes (contrat 1.14, rapports
            // journaliers) : déjà ramené à des valeurs du jour, même pour un magasin qui saisit en cumulé. Sans
            // fournisseur (daily-report absent), aucune vente : le total reste à 0.
            foreach ($this->sales->dailySales($storeId, $from, $to, ['validated', 'submitted']) as $day) {
                $totalPayment += (float) $day['sales_total'];
            }
        }

        // Heures et salaires depuis les shifts (du seul employé si $userId est fourni)
        [$totalMinutes, $totalShiftSalary, $employeeMinutes, $employeeCost] =
            $this->aggregateShifts($storeId, $from, $to, $userId);

        $staffManHours = $totalMinutes > 0 ? round($totalMinutes / 60, 2) : 0;
        $activeEmployees = count($employeeMinutes);
        $avgHourlyWage = $staffManHours > 0 ? round($totalShiftSalary / $staffManHours, 2) : 0;

        // Texte récapitulatif des heures par employé
        $lines = [];
        ksort($employeeMinutes);
        foreach ($employeeMinutes as $uid => $minutes) {
            $user = $this->users->findById($uid);
            if ($user !== null) {
                $name = trim(($user['last_name'] ?? '') . ' ' . ($user['first_name'] ?? '')) ?: ($user['display_name'] ?? '#' . $uid);
                $lines[] = $name . ': ' . round($minutes / 60, 1) . __('hours_unit');
            }
        }

        $preset['total_payment'] = round($totalPayment, 2);
        $preset['staff_man_hours'] = $staffManHours;
        $preset['staff_total_payment'] = round($totalShiftSalary, 2);
        $preset['staff_avg_hourly_wage'] = $avgHourlyWage;
        $preset['active_employees'] = $activeEmployees;
        $preset['employee_work_hours'] = implode("\n", $lines);

        $preset = array_merge($preset, $this->calculateDeductionsPreset(
            $storeId,
            $totalShiftSalary,
            $userId,
            $employeeCost,
        ));

        return $preset;
    }

    /**
     * Agrège les shifts d'un store sur une plage de dates : minutes/coût total,
     * et le détail par employé — factorisé entre calculateSalaryPreset() (montants
     * pré-remplis) et calculateStoreDetail() (détail affiché dans la modale de calcul).
     *
     * Coût calculé à la volée via ShiftWageCalculator::costOf() (même source que
     * StoreStatsService::buildPayslipData()/EmployeeStatsService), et non plus lu depuis
     * shifts.estimated_salary : cette colonne n'est renseignée que par l'import Excel et
     * reste NULL pour tout shift créé/édité à la main, ce qui faisait ressortir un total
     * proche de 0 ici alors que le détail du rapport affichait le bon montant.
     *
     * @return array{0: int, 1: float, 2: array<int, int>, 3: array<int, float>} [totalMinutes, totalCost, employeeMinutes, employeeCost]
     */
    private function aggregateShifts(int $storeId, string $from, string $to, ?int $userId): array
    {
        $allShifts = array_filter($this->shifts->findByStore($storeId), fn($s) => empty($s['deleted_at']));
        $rangeShifts = array_filter($allShifts, fn($s) =>
            ($s['shift_date'] ?? '') >= $from && ($s['shift_date'] ?? '') <= $to
            && ($userId === null || (int) ($s['user_id'] ?? 0) === $userId)
        );

        $typesMap = array_column($this->shiftTypes->findByStore($storeId), null, 'id');
        $wageCalc = new ShiftWageCalculator();
        $rateCache = [];

        $totalMinutes = 0;
        $totalCost = 0.0;
        $employeeMinutes = [];
        $employeeCost = [];
        $typeBreakdown = [];
        foreach ($rangeShifts as $s) {
            $uid = (int) ($s['user_id'] ?? 0);
            if ($uid > 0 && !isset($rateCache[$uid])) {
                $rateCache[$uid] = [];
                foreach ($this->userRates->findByUser($uid) as $r) {
                    $rateCache[$uid][(int) $r['shift_type_id']] = (float) $r['hourly_rate'];
                }
            }

            $wage = $wageCalc->costOf($s, $typesMap, $rateCache[$uid] ?? []);
            $totalMinutes += $wage['net_minutes'];
            $totalCost += $wage['amount'];
            if ($uid > 0) {
                $employeeMinutes[$uid] = ($employeeMinutes[$uid] ?? 0) + $wage['net_minutes'];
                $employeeCost[$uid] = ($employeeCost[$uid] ?? 0) + $wage['amount'];
            }

            foreach ($wage['breakdown'] as $b) {
                $key = $b['shift_type_id'] ?? 0;
                if (!isset($typeBreakdown[$key])) {
                    $typeBreakdown[$key] = ['name' => $b['name'] ?: '—', 'minutes' => 0, 'amount' => 0.0];
                }
                $typeBreakdown[$key]['minutes'] += $b['minutes'];
                $typeBreakdown[$key]['amount']  += $b['amount'];
            }
        }
        $typeBreakdown = array_values($typeBreakdown);
        usort($typeBreakdown, fn($a, $b) => strcmp($a['name'], $b['name']));

        return [$totalMinutes, $totalCost, $employeeMinutes, $employeeCost, $typeBreakdown];
    }

    /**
     * Détail affiché dans la modale "voir le détail des calculs" pour un rapport
     * magasin entier (pas de $userId) : heures/coût par employé + taux de
     * retenues appliqués — l'équivalent de StoreStatsService::buildPayslipData()
     * mais agrégé sur plusieurs employés au lieu d'un seul.
     */
    private function calculateStoreDetail(int $storeId, string $from, string $to): array
    {
        [, , $employeeMinutes, $employeeCost, $typeBreakdown] = $this->aggregateShifts($storeId, $from, $to, null);

        $employees = [];
        foreach ($employeeMinutes as $uid => $minutes) {
            $user = $this->users->findById($uid);
            $name = $user !== null
                ? (trim(($user['last_name'] ?? '') . ' ' . ($user['first_name'] ?? '')) ?: ($user['display_name'] ?? '#' . $uid))
                : '#' . $uid;
            $employees[] = [
                'user_id' => $uid,
                'name'    => $name,
                'hours'   => round($minutes / 60, 2),
                'cost'    => round($employeeCost[$uid] ?? 0, 2),
            ];
        }
        usort($employees, fn($a, $b) => strcmp($a['name'], $b['name']));

        return [
            'mode'               => 'store',
            'from'               => $from,
            'to'                 => $to,
            'employees'          => $employees,
            'type_breakdown'     => $typeBreakdown,
            'deduction_settings' => $this->stores->getDeductionSettings($storeId),
        ];
    }

    /**
     * Pré-remplit les champs de retenues (assurance santé, pension, chômage,
     * impôt à la source, taxe de résidence) à partir des paramètres de
     * retenues du store — même logique que StoreStatsService::buildPayslipData(),
     * appliquée soit à un seul employé, soit sommée sur tous les employés ayant
     * eu des shifts ce mois-ci (rapport magasin entier).
     *
     * @param array<int, float> $employeeCost Coût brut par employé (user_id => somme estimated_salary), pour le mode magasin entier.
     */
    private function calculateDeductionsPreset(int $storeId, float $totalShiftSalary, ?int $userId, array $employeeCost): array
    {
        $deductionSettings = $this->stores->getDeductionSettings($storeId);
        if (empty($deductionSettings['enabled'])) {
            return [];
        }

        $costsByEmployee = $userId !== null ? [$userId => $totalShiftSalary] : $employeeCost;

        $health = 0.0;
        $pension = 0.0;
        $employment = 0.0;
        $incomeTax = 0.0;
        $residentTax = 0.0;

        foreach ($costsByEmployee as $uid => $cost) {
            if ($cost <= 0) {
                continue;
            }
            $membership = $this->storeUsers->findMembership($storeId, $uid);
            $membershipId = (int) ($membership['id'] ?? 0);
            if ($membershipId <= 0 || !$this->storeUsers->getSubjectToDeductions($membershipId)) {
                continue;
            }
            $health      += round($cost * (float) ($deductionSettings['health_insurance_rate'] ?? 0) / 100, 2);
            $pension     += round($cost * (float) ($deductionSettings['pension_rate'] ?? 0) / 100, 2);
            $employment  += round($cost * (float) ($deductionSettings['employment_insurance_rate'] ?? 0) / 100, 2);
            $incomeTax   += round($cost * (float) ($deductionSettings['income_tax_rate'] ?? 0) / 100, 2);
            $residentTax += (float) ($deductionSettings['resident_tax_monthly'] ?? 0);
        }

        $otherDeductions = round($health + $pension + $employment, 2);
        $withholdingTax = round($incomeTax, 2);
        $residenceTax = round($residentTax, 2);
        $totalDeductions = round($otherDeductions + $withholdingTax + $residenceTax, 2);

        if ($totalDeductions <= 0) {
            return [];
        }

        $netPayment = round($totalShiftSalary - $totalDeductions, 2);

        return [
            'income_tax_base'      => round($totalShiftSalary, 2),
            'other_deductions'     => $otherDeductions,
            'withholding_tax'      => $withholdingTax,
            'residence_tax'        => $residenceTax,
            'total_deductions'     => $totalDeductions,
            'net_payment'          => $netPayment,
            // Par défaut le net est versé par virement ; "salaire en main propre"
            // reste à 0 et n'est renseigné que si une partie est réellement payée en espèces.
            'bank_transfer_salary' => $netPayment,
        ];
    }
}
