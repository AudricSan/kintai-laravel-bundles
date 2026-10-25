<?php

declare(strict_types=1);

use kintai\Contract\Http\Middleware\AuthMiddleware;
use kintai\Contract\Http\Middleware\ApiAuthMiddleware;
use kintai\Contract\Http\Middleware\ApiPermissionMiddleware;
use kintai\Contract\Http\Middleware\PermissionMiddleware;
use kintai\Bundles\Installed\DailyReport\Controllers\Web\DailyReportController;
use kintai\Bundles\Installed\DailyReport\Controllers\Api\DailyReportController as ApiDailyReportController;

/** @var kintai\Contract\Http\Router $router */
/** @var kintai\Contract\Container $container */

// =============================================================================
// Daily Report — Routes Web (admin)
// =============================================================================

$router->get('/admin/daily-reports', [DailyReportController::class, 'indexAll'], middleware: [AuthMiddleware::class, PermissionMiddleware::class], name: 'admin.daily_reports.all', permission: 'daily_reports.view');

$router->group('/admin', function ($r) {
    $r->get('/stores/{id}/daily-reports',              [DailyReportController::class, 'index'],        name: 'admin.daily_reports.index', permission: ['perm' => 'daily_reports.view', 'membership' => true]);
    $r->get('/stores/{id}/daily-reports/settings',     [DailyReportController::class, 'editSettings'], name: 'admin.daily_reports.settings', permission: 'daily_reports.update');
    $r->post('/stores/{id}/daily-reports/settings',    [DailyReportController::class, 'saveSettings'], name: 'admin.daily_reports.settings.save', permission: 'daily_reports.update');
    $r->get('/stores/{id}/daily-reports/create',       [DailyReportController::class, 'create'],       name: 'admin.daily_reports.create', permission: ['perm' => 'daily_reports.create', 'membership' => true]);
    $r->post('/stores/{id}/daily-reports/create',      [DailyReportController::class, 'store'],        name: 'admin.daily_reports.store', permission: ['perm' => 'daily_reports.create', 'membership' => true]);
    $r->get('/stores/{id}/daily-reports/{rid}',              [DailyReportController::class, 'show'],        name: 'admin.daily_reports.show', permission: ['perm' => 'daily_reports.view', 'membership' => true]);
    $r->get('/stores/{id}/daily-reports/{rid}/edit',         [DailyReportController::class, 'edit'],        name: 'admin.daily_reports.edit', permission: ['perm' => 'daily_reports.update', 'membership' => true]);
    $r->post('/stores/{id}/daily-reports/{rid}/edit',        [DailyReportController::class, 'update'],      name: 'admin.daily_reports.update', permission: ['perm' => 'daily_reports.update', 'membership' => true]);
    $r->post('/stores/{id}/daily-reports/{rid}/submit',      [DailyReportController::class, 'submit'],      name: 'admin.daily_reports.submit', permission: ['perm' => 'daily_reports.submit', 'membership' => true]);
    $r->post('/stores/{id}/daily-reports/{rid}/validate',    [DailyReportController::class, 'validate'],    name: 'admin.daily_reports.validate', permission: 'daily_reports.approve');
    $r->post('/stores/{id}/daily-reports/{rid}/send-mail',   [DailyReportController::class, 'sendMail'],    name: 'admin.daily_reports.send_mail', permission: 'daily_reports.approve');
    $r->get('/stores/{id}/daily-reports/{rid}/pdf',          [DailyReportController::class, 'previewPdf'],  name: 'admin.daily_reports.pdf', permission: ['perm' => 'daily_reports.view', 'membership' => true]);
    $r->get('/stores/{id}/daily-reports/{rid}/pdf/download', [DailyReportController::class, 'downloadPdf'], name: 'admin.daily_reports.pdf_download', permission: ['perm' => 'daily_reports.view', 'membership' => true]);
    $r->post('/stores/{id}/daily-reports/{rid}/delete',      [DailyReportController::class, 'destroy'],     name: 'admin.daily_reports.delete', permission: 'daily_reports.delete');
}, middleware: [AuthMiddleware::class, PermissionMiddleware::class]);

// =============================================================================
// Daily Report — Routes API
// =============================================================================

$router->group('/api/v1', function ($r) {
    $r->post('/daily-reports/{id}/submit',   [ApiDailyReportController::class, 'submit'],   name: 'api.v1.daily_reports.submit', permission: ['perm' => 'daily_reports.submit', 'membership' => true]);
    $r->post('/daily-reports/{id}/validate', [ApiDailyReportController::class, 'validate'], name: 'api.v1.daily_reports.validate', permission: 'daily_reports.approve');
    $r->get('/daily-reports',                [ApiDailyReportController::class, 'index'],    name: 'api.v1.daily_reports.index', permission: ['perm' => 'daily_reports.view', 'membership' => true]);
    $r->post('/daily-reports',               [ApiDailyReportController::class, 'store'],    name: 'api.v1.daily_reports.store', permission: ['perm' => 'daily_reports.create', 'membership' => true]);
    $r->get('/daily-reports/{id}',           [ApiDailyReportController::class, 'show'],     name: 'api.v1.daily_reports.show', permission: ['perm' => 'daily_reports.view', 'membership' => true]);
    $r->put('/daily-reports/{id}',           [ApiDailyReportController::class, 'update'],   name: 'api.v1.daily_reports.update', permission: ['perm' => 'daily_reports.update', 'membership' => true]);
    $r->delete('/daily-reports/{id}',        [ApiDailyReportController::class, 'destroy'],  name: 'api.v1.daily_reports.destroy', permission: 'daily_reports.delete');
}, middleware: [ApiAuthMiddleware::class, ApiPermissionMiddleware::class]);
