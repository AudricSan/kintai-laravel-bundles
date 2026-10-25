<?php

declare(strict_types=1);

namespace kintai\Bundles\Installed\DailyReport\Controllers\Web;

use kintai\Contract\Auth\PermissionService;
use kintai\Contract\Exceptions\ForbiddenException;
use kintai\Contract\Exceptions\NotFoundException;
use kintai\Bundles\Installed\DailyReport\Repositories\DailyReportRepositoryInterface;
use kintai\Contract\Data\ShiftRepositoryInterface;
use kintai\Contract\Data\ShiftTypeRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Data\StoreUserRepositoryInterface;
use kintai\Contract\Data\UserRepositoryInterface;
use kintai\Contract\Http\Request;
use kintai\Contract\Http\Response;
use kintai\Contract\Services\AuditLogger;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportMailService;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportPdfService;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportPermissionService;
use kintai\Contract\Services\NotificationService;
use kintai\Contract\Services\TranslationService;
use kintai\Contract\Services\UserSelection;
use kintai\Contract\View\ViewRenderer;

final class DailyReportController
{
    public function __construct(
        private readonly ViewRenderer $view,
        private readonly DailyReportRepositoryInterface $reports,
        private readonly StoreRepositoryInterface $stores,
        private readonly StoreUserRepositoryInterface $storeUsers,
        private readonly UserRepositoryInterface $users,
        private readonly DailyReportPermissionService $permissions,
        private readonly DailyReportPdfService $pdfService,
        private readonly DailyReportMailService $mailService,
        private readonly AuditLogger $auditLogger,
        private readonly TranslationService $translations,
        private readonly ShiftRepositoryInterface $shifts,
        private readonly ShiftTypeRepositoryInterface $shiftTypes,
        private readonly NotificationService $notifs,
        private readonly PermissionService $permissionService,
        private readonly UserSelection $userSelection,
    ) {}

    // -------------------------------------------------------------------------
    // Liste des rapports d'un store
    // -------------------------------------------------------------------------

    public function index(Request $request): Response
    {
        $storeId    = (int) $request->param('id');
        $authUser   = $request->getAttribute('auth_user');
        $store      = $this->requireStore($storeId);
        $membership = $this->storeUsers->findMembership($storeId, (int) $authUser['id']);

        $this->assertStoreAccess($request, $storeId);

        if (!$this->permissions->canViewList($authUser, $store, $membership)) {
            throw new ForbiddenException(__('daily_report_error_access_denied'));
        }

        $status    = $request->query('status', '');
        $fromDate  = $request->query('from', '');
        $toDate    = $request->query('to', '');

        if ($fromDate && $toDate) {
            $rows = $this->reports->findByStoreAndDateRange($storeId, $fromDate, $toDate);
        } elseif ($status) {
            $rows = $this->reports->findByStoreAndStatus($storeId, $status);
        } else {
            $rows = $this->reports->findByStore($storeId);
        }

        // Enrichir avec le nom de l'auteur
        $userCache = [];
        foreach ($rows as &$row) {
            $uid = (int) $row['author_id'];
            if (!isset($userCache[$uid])) {
                $userCache[$uid] = $this->users->findById($uid);
            }
            $row['author'] = $userCache[$uid];
        }
        unset($row);

        $settings = $this->permissions->getSettings($store);

        // Ajouter des lignes "aucun rapport" pour les jours manquants
        $today        = date('Y-m-d');
        $currentTime  = date('H:i');
        $noReportTime = $settings['no_report_time'] ?? '18:00';
        $showToday    = $currentTime >= $noReportTime;
        $rangeEnd     = $toDate ? min($toDate, $today) : ($showToday ? $today : date('Y-m-d', strtotime('-1 day')));
        $rangeStart   = $fromDate ?: date('Y-m-01');
        if ($rangeStart <= $rangeEnd) {
            $existingDates = array_flip(array_column($rows, 'report_date'));
            $cursor        = new \DateTime($rangeStart);
            $endDt         = new \DateTime($rangeEnd);
            while ($cursor <= $endDt) {
                $d = $cursor->format('Y-m-d');
                if (!isset($existingDates[$d])) {
                    $rows[] = ['report_date' => $d, 'is_placeholder' => true];
                }
                $cursor->modify('+1 day');
            }
            usort($rows, fn($a, $b) => strcmp($b['report_date'], $a['report_date']));
        }

        $html = $this->view->render('daily-report::daily-reports', [
            'store'       => $store,
            'reports'     => $rows,
            'status'      => $status,
            'fromDate'    => $fromDate,
            'toDate'      => $toDate,
            'settings'    => $settings,
            'authUser'    => $authUser,
            'membership'  => $membership,
            'permissions' => $this->permissions,
        ], 'layout.app');

        return Response::html($html);
    }

    // -------------------------------------------------------------------------
    // Vue globale owner : tous les rapports de tous les stores gérés
    // -------------------------------------------------------------------------

    public function indexAll(Request $request): Response
    {
        $authUser = $request->getAttribute('auth_user');

        // Stores accessibles — is_admin (Owner) et une permission daily_reports.view
        // accordée en portée globale (managed_store_ids === null, ex. rôle store-scope
        // avec cette clé marquée "Toutes les boutiques") signifient la même chose ici :
        // aucune restriction. Un simple "?? []" écraserait ce second cas à tort : la
        // liste passerait par la convention "aucun filtre" de findAllActive() (donc les
        // rapports resteraient visibles par accident), mais $allStores resterait vide et
        // le sélecteur de magasin n'afficherait plus rien pour cet utilisateur.
        $managedIds     = $request->getAttribute('managed_store_ids');
        $isUnrestricted = !empty($authUser['is_admin']) || $managedIds === null;
        if ($isUnrestricted) {
            $allStores      = $this->stores->findActive();
            $storeIdsFilter = [];
        } else {
            $allStores  = array_values(array_filter(
                array_map(fn($id) => $this->stores->findById($id), $managedIds),
            ));
            $storeIdsFilter = $managedIds;
        }

        // Paramètres de filtre
        $filterStoreId = (int) $request->query('store_id', '0');
        $filterStatus  = $request->query('status', '');
        $filterMonth   = (int) $request->query('month', '0');
        $filterYear    = (int) $request->query('year', '0');

        // Appliquer le filtre par store (si l'utilisateur a accès à ce store)
        $queryStoreIds = $storeIdsFilter;
        if ($filterStoreId > 0) {
            if (!$isUnrestricted && !in_array($filterStoreId, $storeIdsFilter, true)) {
                throw new ForbiddenException(__('daily_report_error_access_denied'));
            }
            $queryStoreIds = [$filterStoreId];
        }

        $reports = $this->reports->findAllActive($queryStoreIds);

        // Filtres en mémoire
        if ($filterStatus !== '') {
            $reports = array_values(array_filter($reports, fn($r) => $r['status'] === $filterStatus));
        }
        if ($filterYear > 0) {
            $reports = array_values(array_filter($reports, fn($r) => (int) substr($r['report_date'], 0, 4) === $filterYear));
        }
        if ($filterMonth > 0) {
            $reports = array_values(array_filter($reports, fn($r) => (int) substr($r['report_date'], 5, 2) === $filterMonth));
        }

        // Enrichir et regrouper par store
        $userCache  = [];
        $storeCache = [];
        $grouped    = [];
        foreach ($reports as $report) {
            $uid = (int) $report['author_id'];
            if (!isset($userCache[$uid])) {
                $userCache[$uid] = $this->users->findById($uid);
            }
            $report['author'] = $userCache[$uid];

            $sid = (int) $report['store_id'];
            if (!isset($storeCache[$sid])) {
                $storeCache[$sid] = $this->stores->findById($sid);
            }
            $report['store_obj'] = $storeCache[$sid];

            if (!isset($grouped[$sid])) {
                $grouped[$sid] = ['store' => $storeCache[$sid], 'reports' => []];
            }
            $grouped[$sid]['reports'][] = $report;
        }

        // Inclure tous les magasins accessibles même s'ils n'ont aucun rapport correspondant,
        // pour que les boutons créer/paramétrer restent accessibles.
        foreach ($allStores as $store) {
            $sid = (int) $store['id'];
            if (!isset($grouped[$sid])) {
                $grouped[$sid] = ['store' => $store, 'reports' => []];
            }
        }

        // Années disponibles (pour le sélecteur)
        $allForYears = $this->reports->findAllActive($storeIdsFilter);
        $years = array_unique(array_map(fn($r) => (int) substr($r['report_date'], 0, 4), $allForYears));
        rsort($years);

        // Données pour la vue V2 : tous les stores, filtrés uniquement par année
        $reportsV2 = $this->reports->findAllActive($storeIdsFilter);
        if ($filterYear > 0) {
            $reportsV2 = array_values(array_filter($reportsV2, fn($r) => (int) substr($r['report_date'], 0, 4) === $filterYear));
        }
        foreach ($reportsV2 as &$rv2) {
            $uid = (int) $rv2['author_id'];
            if (!isset($userCache[$uid])) {
                $userCache[$uid] = $this->users->findById($uid);
            }
            $rv2['author'] = $userCache[$uid];
            $sid = (int) $rv2['store_id'];
            if (!isset($storeCache[$sid])) {
                $storeCache[$sid] = $this->stores->findById($sid);
            }
            $rv2['store_obj'] = $storeCache[$sid];
        }
        unset($rv2);

        $html = $this->view->render('daily-report::daily-reports-all', [
            'grouped'       => $grouped,
            'reportsV2'     => $reportsV2,
            'stores'        => $allStores,
            'years'         => $years,
            'filterStoreId' => $filterStoreId,
            'filterStatus'  => $filterStatus,
            'filterMonth'   => $filterMonth,
            'filterYear'    => $filterYear,
            'authUser'      => $authUser,
        ], 'layout.app');

        return Response::html($html);
    }

    // -------------------------------------------------------------------------
    // Formulaire de création
    // -------------------------------------------------------------------------

    public function create(Request $request): Response
    {
        $storeId    = (int) $request->param('id');
        $authUser   = $request->getAttribute('auth_user');
        $store      = $this->requireStore($storeId);
        $membership = $this->storeUsers->findMembership($storeId, (int) $authUser['id']);

        $this->assertStoreAccess($request, $storeId);

        if (!$this->permissions->canCreate($authUser, $store, $membership)) {
            throw new ForbiddenException(__('daily_report_error_not_authorized_create'));
        }

        $today    = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $existing = $this->reports->findByStoreAndDate($storeId, $today);

        $settings = $this->permissions->getSettings($store);

        $html = $this->view->render('daily-report::daily-report-form', [
            'store'      => $store,
            'report'     => null,
            'existing'   => $existing,
            'date'       => $today,
            'errors'     => [],
            'authUser'   => $authUser,
            'membership' => $membership,
            'shiftRows'  => $this->getFormShiftRows($storeId, $tomorrow),
            'settings'   => $settings,
        ], 'layout.app');

        return Response::html($html);
    }

    // -------------------------------------------------------------------------
    // Enregistrement d'un nouveau rapport
    // -------------------------------------------------------------------------

    public function store(Request $request): Response
    {
        $storeId    = (int) $request->param('id');
        $authUser   = $request->getAttribute('auth_user');
        $store      = $this->requireStore($storeId);
        $membership = $this->storeUsers->findMembership($storeId, (int) $authUser['id']);

        $this->assertStoreAccess($request, $storeId);

        if (!$this->permissions->canCreate($authUser, $store, $membership)) {
            throw new ForbiddenException(__('error_access_denied'));
        }

        $data   = $request->allPost();
        $errors = $this->validateReportData($data);

        $settings = $this->permissions->getSettings($store);

        if (!empty($errors)) {
            $formDate = $data['report_date'] ?? date('Y-m-d');
            $html = $this->view->render('daily-report::daily-report-form', [
                'store'      => $store,
                'report'     => null,
                'existing'   => null,
                'date'       => $formDate,
                'errors'     => $errors,
                'old'        => $data,
                'authUser'   => $authUser,
                'membership' => $membership,
                'shiftRows'  => $this->getFormShiftRows($storeId, $this->getShiftDateForReport($formDate)),
                'settings'   => $settings,
            ], 'layout.app');
            return Response::html($html, 422);
        }

        $reportDate = $data['report_date'] ?? date('Y-m-d');

        // Interdire les dates passées pour tous sauf le super admin
        if (empty($authUser['is_admin']) && $reportDate < date('Y-m-d')) {
            $errors['report_date'] = __('daily_report_error_past_date');
            $html = $this->view->render('daily-report::daily-report-form', [
                'store'      => $store,
                'report'     => null,
                'existing'   => null,
                'date'       => $reportDate,
                'errors'     => $errors,
                'old'        => $data,
                'authUser'   => $authUser,
                'membership' => $membership,
                'settings'   => $settings,
            ], 'layout.app');
            return Response::html($html, 422);
        }

        // Unicité : un seul rapport actif par store par jour
        if ($this->reports->findByStoreAndDate($storeId, $reportDate) !== null) {
            $errors['report_date'] = 'Un rapport existe déjà pour cette date.';
            $html = $this->view->render('daily-report::daily-report-form', [
                'store'      => $store,
                'report'     => null,
                'existing'   => null,
                'date'       => $reportDate,
                'errors'     => $errors,
                'old'        => $data,
                'authUser'   => $authUser,
                'membership' => $membership,
                'settings'   => $settings,
            ], 'layout.app');
            return Response::html($html, 422);
        }

        $report = $this->reports->save([
            'store_id'       => $storeId,
            'author_id'      => (int) $authUser['id'],
            'report_date'    => $reportDate,
            'sales_total'    => (float) ($data['sales_total'] ?? 0),
            'customer_count' => (int) ($data['customer_count'] ?? 0),
            'labor_cost'     => (float) ($data['labor_cost'] ?? 0),
            'waste_total'    => (float) ($data['waste_total'] ?? 0),
            'notes'          => $data['notes'] ?? null,
            'status'         => 'draft',
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->auditLogger->log($request, 'daily_report.created', 'daily_report', (int) $report['id'], [
            'store_id'    => $storeId,
            'report_date' => $reportDate,
        ], $storeId);

        return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/daily-reports/' . $report['id']);
    }

    // -------------------------------------------------------------------------
    // Détail d'un rapport
    // -------------------------------------------------------------------------

    public function show(Request $request): Response
    {
        $storeId    = (int) $request->param('id');
        $reportId   = (int) $request->param('rid');
        $authUser   = $request->getAttribute('auth_user');
        $store      = $this->requireStore($storeId);
        $report     = $this->requireReport($reportId, $storeId);
        $membership = $this->storeUsers->findMembership($storeId, (int) $authUser['id']);

        $this->assertStoreAccess($request, $storeId);

        if (!$this->permissions->canViewReport($authUser, $store, $report, $membership)) {
            throw new ForbiddenException(__('daily_report_error_access_denied'));
        }

        // Le rapport est ouvert par quelqu'un qui a le droit de le voir : ses notifications sont lues.
        $this->notifs->markReadFor((int) $authUser['id'], 'daily_report', $reportId);

        $author   = $this->users->findById((int) $report['author_id']);
        $validator = $report['validated_by'] ? $this->users->findById((int) $report['validated_by']) : null;
        $settings  = $this->permissions->getSettings($store);

        $html = $this->view->render('daily-report::daily-report-show', [
            'store'       => $store,
            'report'      => $report,
            'author'      => $author,
            'validator'   => $validator,
            'settings'    => $settings,
            'authUser'    => $authUser,
            'membership'  => $membership,
            'permissions' => $this->permissions,
            'shiftRows'   => $this->getFormShiftRows($storeId, $this->getShiftDateForReport($report['report_date'])),
            'canEdit'     => $this->permissions->canEdit($authUser, $store, $report, $membership),
            'canSubmit'   => $this->permissions->canSubmit($authUser, $store, $report, $membership),
            'canValidate' => $this->permissions->canValidate($authUser, $store, $report, $membership),
            'canSendMail' => $this->permissions->canSendMail($authUser, $store, $report, $membership),
            'canDelete'   => $this->permissions->canDelete($authUser, $store, $report, $membership),
        ], 'layout.app');

        return Response::html($html);
    }

    // -------------------------------------------------------------------------
    // Formulaire d'édition
    // -------------------------------------------------------------------------

    public function edit(Request $request): Response
    {
        $storeId    = (int) $request->param('id');
        $reportId   = (int) $request->param('rid');
        $authUser   = $request->getAttribute('auth_user');
        $store      = $this->requireStore($storeId);
        $report     = $this->requireReport($reportId, $storeId);
        $membership = $this->storeUsers->findMembership($storeId, (int) $authUser['id']);

        $this->assertStoreAccess($request, $storeId);

        if (!$this->permissions->canEdit($authUser, $store, $report, $membership)) {
            throw new ForbiddenException(__('daily_report_error_report_locked'));
        }

        $settings = $this->permissions->getSettings($store);

        $html = $this->view->render('daily-report::daily-report-form', [
            'store'      => $store,
            'report'     => $report,
            'existing'   => null,
            'date'       => $report['report_date'],
            'errors'     => [],
            'authUser'   => $authUser,
            'membership' => $membership,
            'shiftRows'  => $this->getFormShiftRows($storeId, $this->getShiftDateForReport($report['report_date'])),
            'settings'   => $settings,
        ], 'layout.app');

        return Response::html($html);
    }

    // -------------------------------------------------------------------------
    // Mise à jour
    // -------------------------------------------------------------------------

    public function update(Request $request): Response
    {
        $storeId    = (int) $request->param('id');
        $reportId   = (int) $request->param('rid');
        $authUser   = $request->getAttribute('auth_user');
        $store      = $this->requireStore($storeId);
        $report     = $this->requireReport($reportId, $storeId);
        $membership = $this->storeUsers->findMembership($storeId, (int) $authUser['id']);

        $this->assertStoreAccess($request, $storeId);

        if (!$this->permissions->canEdit($authUser, $store, $report, $membership)) {
            throw new ForbiddenException(__('daily_report_error_report_locked'));
        }

        $data   = $request->allPost();
        $errors = $this->validateReportData($data);

        $settings = $this->permissions->getSettings($store);

        if (!empty($errors)) {
            $html = $this->view->render('daily-report::daily-report-form', [
                'store'      => $store,
                'report'     => $report,
                'existing'   => null,
                'date'       => $report['report_date'],
                'errors'     => $errors,
                'old'        => $data,
                'authUser'   => $authUser,
                'membership' => $membership,
                'shiftRows'  => $this->getFormShiftRows($storeId, $report['report_date']),
                'settings'   => $settings,
            ], 'layout.app');
            return Response::html($html, 422);
        }

        $updated = $this->reports->save(array_merge($report, [
            'sales_total'    => (float) ($data['sales_total'] ?? 0),
            'customer_count' => (int) ($data['customer_count'] ?? 0),
            'labor_cost'     => (float) ($data['labor_cost'] ?? 0),
            'waste_total'    => (float) ($data['waste_total'] ?? 0),
            'notes'          => $data['notes'] ?? null,
            'updated_at'     => date('Y-m-d H:i:s'),
        ]));

        $this->auditLogger->logUpdate($request, 'daily_report.updated', 'daily_report', $reportId, $report, $updated, [
            'store_id'    => $storeId,
            'report_date' => $report['report_date'],
        ], $storeId);

        return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/daily-reports/' . $reportId);
    }

    // -------------------------------------------------------------------------
    // Workflow : soumettre
    // -------------------------------------------------------------------------

    public function submit(Request $request): Response
    {
        $storeId    = (int) $request->param('id');
        $reportId   = (int) $request->param('rid');
        $authUser   = $request->getAttribute('auth_user');
        $store      = $this->requireStore($storeId);
        $report     = $this->requireReport($reportId, $storeId);
        $membership = $this->storeUsers->findMembership($storeId, (int) $authUser['id']);

        $this->assertStoreAccess($request, $storeId);

        if (!$this->permissions->canSubmit($authUser, $store, $report, $membership)) {
            throw new ForbiddenException(__('daily_report_error_not_authorized_submit'));
        }

        $submitted = $this->reports->save(array_merge($report, [
            'status'       => 'submitted',
            'submitted_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]));

        $this->auditLogger->logUpdate($request, 'daily_report.submitted', 'daily_report', $reportId, $report, $submitted, [
            'store_id'    => $storeId,
            'report_date' => $report['report_date'],
        ], $storeId);

        $this->notifyManagers(
            $storeId,
            'daily_reports.approve',
            'daily_report_submitted',
            'notif_daily_report_submitted_body',
            $reportId,
            ['store' => $store['name'] ?? '', 'date' => $report['report_date'] ?? ''],
            '/admin/stores/' . $storeId . '/daily-reports/' . $reportId
        );

        return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/daily-reports/' . $reportId);
    }

    // -------------------------------------------------------------------------
    // Workflow : valider
    // -------------------------------------------------------------------------

    public function validate(Request $request): Response
    {
        $storeId    = (int) $request->param('id');
        $reportId   = (int) $request->param('rid');
        $authUser   = $request->getAttribute('auth_user');
        $store      = $this->requireStore($storeId);
        $report     = $this->requireReport($reportId, $storeId);
        $membership = $this->storeUsers->findMembership($storeId, (int) $authUser['id']);

        $this->assertStoreAccess($request, $storeId);

        if (!$this->permissions->canValidate($authUser, $store, $report, $membership)) {
            throw new ForbiddenException(__('daily_report_error_not_authorized_validate'));
        }

        $now     = date('Y-m-d H:i:s');
        $updated = array_merge($report, [
            'status'       => 'validated',
            'validated_by' => (int) $authUser['id'],
            'validated_at' => $now,
            'updated_at'   => $now,
        ]);

        $validated = $this->reports->save($updated);

        $this->auditLogger->logUpdate($request, 'daily_report.validated', 'daily_report', $reportId, $report, $validated, [
            'store_id'    => $report['store_id'],
            'report_date' => $report['report_date'],
        ], $storeId);

        $authorId = (int) ($report['author_id'] ?? 0);
        if ($authorId > 0 && $authorId !== (int) $authUser['id']) {
            $this->notifs->notify(
                $authorId,
                'daily_report_validated',
                'notif_daily_report_validated_body',
                ['store' => $store['name'] ?? '', 'date' => $report['report_date'] ?? ''],
                $reportId,
                '/admin/stores/' . $storeId . '/daily-reports/' . $reportId
            );
        }

        // Envoi automatique si configuré
        $settings = $this->permissions->getSettings($store);
        if (!empty($settings['auto_send_on_validate']) && !empty($settings['mail_recipients'])) {
            $author   = $this->users->findById((int) $report['author_id']) ?? [];
            $pdfBytes = $this->pdfService->generateBytes($updated, $store, $author, $this->translations->getLocale());
            if ($this->mailService->send($updated, $store, $pdfBytes, $author, $this->translations->getLocale())) {
                $this->reports->save(array_merge($updated, ['mail_sent_at' => $now]));
            } else {
                error_log('[Kintai][DailyReport] Envoi automatique échoué — rapport #' . $reportId . ', store #' . $storeId);
            }
        }

        return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/daily-reports/' . $reportId);
    }

    // -------------------------------------------------------------------------
    // Envoi manuel du mail
    // -------------------------------------------------------------------------

    public function sendMail(Request $request): Response
    {
        $storeId    = (int) $request->param('id');
        $reportId   = (int) $request->param('rid');
        $authUser   = $request->getAttribute('auth_user');
        $store      = $this->requireStore($storeId);
        $report     = $this->requireReport($reportId, $storeId);
        $membership = $this->storeUsers->findMembership($storeId, (int) $authUser['id']);

        $this->assertStoreAccess($request, $storeId);

        if (!$this->permissions->canSendMail($authUser, $store, $report, $membership)) {
            throw new ForbiddenException(__('daily_report_error_not_authorized_send'));
        }

        $mailAuthor = $this->users->findById((int) $report['author_id']) ?? [];
        $pdfBytes   = $this->pdfService->generateBytes($report, $store, $mailAuthor, $this->translations->getLocale());
        $sent       = $this->mailService->send($report, $store, $pdfBytes, $mailAuthor, $this->translations->getLocale());

        if ($sent) {
            $this->reports->save(array_merge($report, [
                'mail_sent_at' => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]));
            $this->auditLogger->log($request, 'daily_report.mail_sent', 'daily_report', $reportId, [
                'store_id'    => $storeId,
                'report_date' => $report['report_date'],
            ], $storeId);
        } else {
            error_log('[Kintai][DailyReport] Envoi manuel échoué — rapport #' . $reportId . ', store #' . $storeId);
        }

        $redirect = $this->base() . '/admin/stores/' . $storeId . '/daily-reports/' . $reportId;
        return Response::redirect($redirect . ($sent ? '?mail=1' : '?mail=0'));
    }

    // -------------------------------------------------------------------------
    // Téléchargement PDF
    // -------------------------------------------------------------------------

    /**
     * Aperçu HTML du PDF (route .../pdf) : pas de téléchargement automatique,
     * la même vue daily-report-pdf.php est rendue directement dans le
     * navigateur avec une barre d'outils (impression / téléchargement réel /
     * fermer), comme les autres rapports RH. Le fichier PDF n'est généré
     * (via mPDF) que si l'utilisateur clique "Télécharger" (downloadPdf()).
     */
    public function previewPdf(Request $request): Response
    {
        $storeId    = (int) $request->param('id');
        $reportId   = (int) $request->param('rid');
        $authUser   = $request->getAttribute('auth_user');
        $store      = $this->requireStore($storeId);
        $report     = $this->requireReport($reportId, $storeId);
        $membership = $this->storeUsers->findMembership($storeId, (int) $authUser['id']);

        $this->assertStoreAccess($request, $storeId);

        if (!$this->permissions->canViewReport($authUser, $store, $report, $membership)) {
            throw new ForbiddenException(__('error_access_denied'));
        }

        $pdfAuthor   = $this->users->findById((int) $report['author_id']) ?? [];
        $downloadUrl = $this->base() . '/admin/stores/' . $storeId . '/daily-reports/' . $reportId . '/pdf/download';
        $html        = $this->pdfService->generateHtml($report, $store, $pdfAuthor, $this->translations->getLocale(), $downloadUrl);

        return Response::html($html);
    }

    public function downloadPdf(Request $request): Response
    {
        $storeId    = (int) $request->param('id');
        $reportId   = (int) $request->param('rid');
        $authUser   = $request->getAttribute('auth_user');
        $store      = $this->requireStore($storeId);
        $report     = $this->requireReport($reportId, $storeId);
        $membership = $this->storeUsers->findMembership($storeId, (int) $authUser['id']);

        $this->assertStoreAccess($request, $storeId);

        if (!$this->permissions->canViewReport($authUser, $store, $report, $membership)) {
            throw new ForbiddenException(__('error_access_denied'));
        }

        $pdfAuthor = $this->users->findById((int) $report['author_id']) ?? [];
        $bytes     = $this->pdfService->generateBytes($report, $store, $pdfAuthor, $this->translations->getLocale());

        if ($bytes === null) {
            throw new NotFoundException(__('daily_report_error_pdf_generation_failed'));
        }

        $this->auditLogger->log($request, 'export.daily_report_pdf', 'daily_report', $reportId, [
            'store_id'    => $storeId,
            'report_date' => $report['report_date'],
            'format'      => 'pdf',
        ], $storeId);

        $filename = 'rapport_' . str_replace('-', '', $report['report_date']) . '_store' . $storeId . '.pdf';
        return Response::pdf($bytes, $filename);
    }

    // -------------------------------------------------------------------------
    // Suppression (soft delete)
    // -------------------------------------------------------------------------

    public function destroy(Request $request): Response
    {
        $storeId    = (int) $request->param('id');
        $reportId   = (int) $request->param('rid');
        $authUser   = $request->getAttribute('auth_user');
        $store      = $this->requireStore($storeId);
        $report     = $this->requireReport($reportId, $storeId);
        $membership = $this->storeUsers->findMembership($storeId, (int) $authUser['id']);

        $this->assertStoreAccess($request, $storeId);

        if (!$this->permissions->canDelete($authUser, $store, $report, $membership)) {
            throw new ForbiddenException(__('daily_report_error_not_authorized_delete'));
        }

        $this->reports->save(array_merge($report, [
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]));

        $this->auditLogger->log($request, 'daily_report.deleted', 'daily_report', $reportId, [
            'store_id'    => $storeId,
            'report_date' => $report['report_date'],
        ], $storeId);

        return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/daily-reports');
    }

    // -------------------------------------------------------------------------
    // Paramètres du store pour les rapports journaliers
    // -------------------------------------------------------------------------

    public function editSettings(Request $request): Response
    {
        $storeId    = (int) $request->param('id');
        $authUser   = $request->getAttribute('auth_user');
        $store      = $this->requireStore($storeId);
        $membership = $this->storeUsers->findMembership($storeId, (int) $authUser['id']);

        $this->assertStoreAccess($request, $storeId);

        if (!$this->permissions->canManageSettings($authUser, $store)) {
            throw new ForbiddenException(__('error_access_denied'));
        }

        $settings = $this->permissions->getSettings($store);

        return Response::html($this->renderSettings($store, $settings, $authUser, $membership, []));
    }

    /** @param array<string, mixed> $settings @param array<string, string> $errors */
    private function renderSettings(array $store, array $settings, array $authUser, ?array $membership, array $errors): string
    {
        return $this->view->render('daily-report::daily-report-settings', [
            'store'      => $store,
            'settings'   => $settings,
            'authUser'   => $authUser,
            'membership' => $membership,
            'errors'     => $errors,
            'candidates' => $this->userSelection->candidates((int) $store['id']),
        ], 'layout.app');
    }

    public function saveSettings(Request $request): Response
    {
        $storeId    = (int) $request->param('id');
        $authUser   = $request->getAttribute('auth_user');
        $store      = $this->requireStore($storeId);
        $membership = $this->storeUsers->findMembership($storeId, (int) $authUser['id']);

        $this->assertStoreAccess($request, $storeId);

        if (!$this->permissions->canManageSettings($authUser, $store)) {
            throw new ForbiddenException(__('error_access_denied'));
        }

        $data = $request->allPost();

        $rawTime = trim($data['auto_validate_time'] ?? '');
        $autoValidateTime = preg_match('/^\d{2}:\d{2}$/', $rawTime) ? $rawTime : null;

        $rawReminder  = trim($data['reminder_time'] ?? '');
        $reminderTime = preg_match('/^\d{2}:\d{2}$/', $rawReminder) ? $rawReminder : '15:00';

        $rawNoReport  = trim($data['no_report_time'] ?? '');
        $noReportTime = preg_match('/^\d{2}:\d{2}$/', $rawNoReport) ? $rawNoReport : '18:00';

        $rawColumns = $data['show_columns'] ?? [];
        $validColumns = ['employee', 'type', 'schedule', 'gross_hours', 'pause', 'net_hours'];
        $showColumns = array_values(array_intersect(
            is_array($rawColumns) ? $rawColumns : [],
            $validColumns
        ));
        if (empty($showColumns)) {
            $showColumns = $validColumns;
        }

        $current   = $this->permissions->getSettings($store);
        $selection = $this->userSelection->validate($storeId, (array) ($data['mail_recipient_ids'] ?? []));
        if ($selection['error'] !== null) {
            $errors = ['mail_recipient_ids' => __('daily_report_error_recipients_' . $selection['error'])];
            return Response::html($this->renderSettings($store, array_merge($current, $data), $authUser, $membership, $errors), 422);
        }

        $settings = [
            'enabled'               => !empty($data['enabled']),
            'mail_recipient_ids'    => $selection['ids'],
            'mail_recipients'       => $this->keptLegacyRecipients($current, (array) ($data['legacy_recipients'] ?? [])),
            'auto_send_on_validate' => !empty($data['auto_send_on_validate']),
            'auto_validate_time'    => $autoValidateTime,
            'reminder_time'         => $reminderTime,
            'no_report_time'        => $noReportTime,
            'show_columns'          => $showColumns,
            'cumulative_mode'       => $data['cumulative_mode'] ?? 'per_day',
        ];

        $savedStore = $this->stores->save(array_merge($store, [
            'daily_report_settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'updated_at'            => date('Y-m-d H:i:s'),
        ]));

        $this->auditLogger->logUpdate($request, 'daily_report.settings_updated', 'store', $storeId, $store, $savedStore, [
            'settings' => $settings,
        ], $storeId);

        return Response::redirect($this->base() . '/admin/stores/' . $storeId . '/daily-reports/settings');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getFormShiftRows(int $storeId, string $date): array
    {
        // On ne garde que les shifts dont l'utilisateur est réellement membre de ce
        // magasin : un shift mal assigné (ex. import Excel ayant matché le mauvais
        // utilisateur à cause d'un nom de famille en doublon dans un autre magasin)
        // ne doit jamais faire apparaître un staff d'un autre magasin dans le rapport.
        $rawShifts = array_filter(
            $this->shifts->findByDate($storeId, $date),
            fn($s) => empty($s['deleted_at'])
                && !empty($s['user_id'])
                && $this->storeUsers->findMembership($storeId, (int) $s['user_id']) !== null
        );

        $typesMap  = array_column($this->shiftTypes->findByStore($storeId), null, 'id');
        $userCache = [];
        $rows      = [];

        foreach ($rawShifts as $s) {
            $uid = (int) $s['user_id'];
            if (!isset($userCache[$uid])) {
                $u               = $this->users->findById($uid);
                $userCache[$uid] = $u
                    ? (trim(($u['last_name'] ?? '') . ' ' . ($u['first_name'] ?? '')) ?: ($u['email'] ?? '—'))
                    : '—';
            }
            $tid      = (int) ($s['shift_type_id'] ?? 0);
            $grossMin = (int) $s['duration_minutes'];
            $pauseMin = (int) $s['pause_minutes'];
            $rows[]   = [
                'employee'  => $userCache[$uid],
                'type'      => $typesMap[$tid]['name'] ?? '—',
                'start'     => substr($s['start_time'] ?? '', 0, 5),
                'end'       => substr($s['end_time']   ?? '', 0, 5),
                'gross_min' => $grossMin,
                'pause_min' => $pauseMin,
                'net_min'   => max(0, $grossMin - $pauseMin),
            ];
        }

        usort($rows, fn($a, $b) => strcmp($a['start'], $b['start']));

        return $rows;
    }

    private function requireStore(int $id): array
    {
        $store = $this->stores->findById($id);
        if ($store === null) {
            throw new NotFoundException(__('error_store_not_found'));
        }
        return $store;
    }

    private function requireReport(int $id, int $storeId): array
    {
        $report = $this->reports->findById($id);
        if ($report === null || (int) $report['store_id'] !== $storeId) {
            throw new NotFoundException(__('daily_report_error_report_not_found'));
        }
        return $report;
    }

    /** Notifie les membres du store détenant $permissionKey (ex. les managers pouvant valider). */
    private function notifyManagers(int $storeId, string $permissionKey, string $type, string $bodyKey, int $referenceId, array $replace = [], ?string $link = null): void
    {
        $recipients = [];
        foreach ($this->storeUsers->findByStore($storeId) as $m) {
            $uid = (int) $m['user_id'];
            $candidate = $this->users->findById($uid);
            if ($candidate !== null && $this->permissionService->can($candidate, $permissionKey, $storeId)) {
                $recipients[] = $uid;
            }
        }
        if ($recipients !== []) {
            $this->notifs->notifyMany($recipients, $type, $bodyKey, $replace, $referenceId, $link);
        }
    }

    private function assertStoreAccess(Request $request, int $storeId): void
    {
        $managedIds = $request->getAttribute('managed_store_ids');
        if ($managedIds !== null && !in_array($storeId, $managedIds, true)) {
            throw new ForbiddenException(__('error_not_store_manager'));
        }
    }

    private function validateReportData(array $data): array
    {
        $errors = [];

        if (empty($data['report_date'])) {
            $errors['report_date'] = 'La date est obligatoire.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['report_date'])) {
            $errors['report_date'] = 'Format de date invalide.';
        }

        if (isset($data['sales_total']) && !is_numeric($data['sales_total'])) {
            $errors['sales_total'] = 'Valeur numérique requise.';
        }
        if (isset($data['customer_count']) && (!is_numeric($data['customer_count']) || (int) $data['customer_count'] < 0)) {
            $errors['customer_count'] = 'Nombre de clients invalide.';
        }
        if (isset($data['labor_cost']) && !is_numeric($data['labor_cost'])) {
            $errors['labor_cost'] = 'Valeur numérique requise.';
        }
        if (isset($data['waste_total']) && !is_numeric($data['waste_total'])) {
            $errors['waste_total'] = 'Valeur numérique requise.';
        }

        return $errors;
    }

    /**
     * Adresses saisies à la main avant la sélection d'employés : on ne peut plus en ajouter, seulement retirer celles qui
     * existent déjà (une adresse absente de la liste enregistrée est ignorée).
     *
     * @param array<string, mixed> $current Réglages enregistrés
     * @param array<mixed> $submitted Adresses restées cochées dans le formulaire
     * @return string[]
     */
    private function keptLegacyRecipients(array $current, array $submitted): array
    {
        $stored = array_map('strval', (array) ($current['mail_recipients'] ?? []));
        $keep   = array_map(static fn($e): string => strtolower(trim((string) $e)), $submitted);
        return array_values(array_filter(
            $stored,
            static fn(string $e): bool => in_array(strtolower($e), $keep, true) && filter_var($e, FILTER_VALIDATE_EMAIL) !== false,
        ));
    }

    /**
     * Calcule la date des shifts à afficher pour un rapport donné.
     * Le rapport du jour J affiche les shifts du jour J+1 (lendemain).
     */
    private function getShiftDateForReport(string $reportDate): string
    {
        return date('Y-m-d', strtotime($reportDate . ' +1 day'));
    }

    private function base(): string
    {
        $sn   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base = rtrim(str_replace('\\', '/', dirname($sn)), '/');
        return ($base === '.' || $base === '/') ? '' : $base;
    }
}
