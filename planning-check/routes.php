<?php

declare(strict_types=1);

use kintai\Bundles\Installed\PlanningCheck\Controllers\Web\PlanningCheckController;
use kintai\Contract\Http\Middleware\AuthMiddleware;
use kintai\Contract\Http\Middleware\PermissionMiddleware;

/** @var kintai\Contract\Http\Router $router */
/** @var kintai\Contract\Container $container */

// =============================================================================
// Validation du planning de demain — Routes Web (admin). Chaque route est rattachée au RBAC (permissions déclarées par
// PlanningCheckBundle::permissions()) et, sur un magasin, à l'appartenance (« membership »).
// =============================================================================

$router->get('/admin/planning-check', [PlanningCheckController::class, 'index'], middleware: [AuthMiddleware::class, PermissionMiddleware::class], name: 'admin.planning_check.index', permission: 'planning_check.view');

$router->group('/admin', function ($r) {
    $r->get('/stores/{id}/planning-check',                       [PlanningCheckController::class, 'history'],      name: 'admin.planning_check.history',  permission: ['perm' => 'planning_check.view', 'membership' => true]);
    $r->get('/stores/{id}/planning-check/settings',              [PlanningCheckController::class, 'settings'],     name: 'admin.planning_check.settings', permission: 'planning_check.settings');
    $r->post('/stores/{id}/planning-check/settings',             [PlanningCheckController::class, 'saveSettings'], name: 'admin.planning_check.settings.save', permission: 'planning_check.settings');
    $r->post('/stores/{id}/planning-check/test',                 [PlanningCheckController::class, 'sendTest'],     name: 'admin.planning_check.test',     permission: 'planning_check.settings');
    $r->post('/stores/{id}/planning-check/submit',               [PlanningCheckController::class, 'submit'],       name: 'admin.planning_check.submit',   permission: ['perm' => 'planning_check.submit', 'membership' => true]);
    $r->post('/stores/{id}/planning-check/edit',                 [PlanningCheckController::class, 'edit'],         name: 'admin.planning_check.edit',     permission: ['perm' => 'planning_check.submit', 'membership' => true]);
    $r->post('/stores/{id}/planning-check/resume',              [PlanningCheckController::class, 'resume'],       name: 'admin.planning_check.resume',   permission: ['perm' => 'planning_check.submit', 'membership' => true]);
    $r->post('/stores/{id}/planning-check/recheck',              [PlanningCheckController::class, 'recheck'],      name: 'admin.planning_check.recheck',  permission: ['perm' => 'planning_check.submit', 'membership' => true]);
    $r->post('/stores/{id}/planning-check/send-now',            [PlanningCheckController::class, 'sendNow'],      name: 'admin.planning_check.send_now', permission: 'planning_check.approve');
    $r->post('/stores/{id}/planning-check/{cid}/approve',       [PlanningCheckController::class, 'approve'],      name: 'admin.planning_check.approve',  permission: 'planning_check.approve');
    $r->post('/stores/{id}/planning-check/{cid}/reject',         [PlanningCheckController::class, 'reject'],       name: 'admin.planning_check.reject',   permission: 'planning_check.approve');
    $r->post('/stores/{id}/planning-check/{cid}/resend',         [PlanningCheckController::class, 'resend'],       name: 'admin.planning_check.resend',   permission: 'planning_check.approve');
    $r->get('/stores/{id}/planning-check/{cid}/pdf',             [PlanningCheckController::class, 'pdf'],          name: 'admin.planning_check.pdf',      permission: ['perm' => 'planning_check.view', 'membership' => true]);
}, middleware: [AuthMiddleware::class, PermissionMiddleware::class]);
