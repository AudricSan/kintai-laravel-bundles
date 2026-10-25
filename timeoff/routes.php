<?php

declare(strict_types=1);

use kintai\Contract\Http\Middleware\AuthMiddleware;
use kintai\Contract\Http\Middleware\ApiAuthMiddleware;
use kintai\Contract\Http\Middleware\ApiPermissionMiddleware;
use kintai\Contract\Http\Middleware\PermissionMiddleware;
use kintai\Bundles\Installed\TimeOff\Controllers\Web\EmployeeTimeoffController;
use kintai\Bundles\Installed\TimeOff\Controllers\Web\AdminTimeoffController;
use kintai\Bundles\Installed\TimeOff\Controllers\Api\TimeoffRequestController as ApiTimeoffRequestController;

/** @var kintai\Contract\Http\Router $router */
/** @var kintai\Contract\Container $container */

// =============================================================================
// TimeOff — Routes Web (employé)
// =============================================================================

$router->group('/employee', function ($r) {
    $r->get('/timeoff',              [EmployeeTimeoffController::class, 'timeoff'],       name: 'employee.timeoff');
    $r->post('/timeoff',             [EmployeeTimeoffController::class, 'storeTimeoff'],  name: 'employee.timeoff.store');
    $r->post('/timeoff/{id}/cancel', [EmployeeTimeoffController::class, 'cancelTimeoff'], name: 'employee.timeoff.cancel');
}, middleware: [AuthMiddleware::class]);

// =============================================================================
// TimeOff — Routes Web (admin)
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/timeoff',               [AdminTimeoffController::class, 'timeoff'],               name: 'admin.timeoff', permission: 'timeoff.view');
    $r->get('/timeoff/create',        [AdminTimeoffController::class, 'createTimeoff'],          name: 'admin.timeoff.create', permission: 'timeoff.create');
    $r->post('/timeoff/create',       [AdminTimeoffController::class, 'storeTimeoffForEmployee'], name: 'admin.timeoff.store', permission: 'timeoff.create');
    $r->post('/timeoff/{id}/approve', [AdminTimeoffController::class, 'approveTimeoff'],         name: 'admin.timeoff.approve', permission: 'timeoff.approve');
    $r->post('/timeoff/{id}/refuse',  [AdminTimeoffController::class, 'refuseTimeoff'],          name: 'admin.timeoff.refuse', permission: 'timeoff.approve');
    $r->post('/timeoff/{id}/delete',  [AdminTimeoffController::class, 'deleteTimeoff'],          name: 'admin.timeoff.delete', permission: 'timeoff.delete');
}, middleware: [AuthMiddleware::class, PermissionMiddleware::class]);

// =============================================================================
// TimeOff — Routes API
// =============================================================================

$router->group('/api/v1', function ($r) {
    $r->get('/timeoff-requests',         [ApiTimeoffRequestController::class, 'index'],   name: 'api.v1.timeoff.index', permission: ['perm' => 'timeoff.view', 'self' => 'user_id']);
    $r->post('/timeoff-requests',        [ApiTimeoffRequestController::class, 'store'],   name: 'api.v1.timeoff.store', permission: ['perm' => 'timeoff.create', 'self' => 'user_id']);
    $r->get('/timeoff-requests/{id}',    [ApiTimeoffRequestController::class, 'show'],    name: 'api.v1.timeoff.show', permission: 'timeoff.view');
    $r->put('/timeoff-requests/{id}',    [ApiTimeoffRequestController::class, 'update'],  name: 'api.v1.timeoff.update', permission: 'timeoff.update');
    $r->delete('/timeoff-requests/{id}', [ApiTimeoffRequestController::class, 'destroy'], name: 'api.v1.timeoff.destroy', permission: 'timeoff.delete');
}, middleware: [ApiAuthMiddleware::class, ApiPermissionMiddleware::class]);
