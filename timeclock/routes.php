<?php

declare(strict_types=1);

use kintai\Contract\Http\Middleware\AuthMiddleware;
use kintai\Contract\Http\Middleware\ApiAuthMiddleware;
use kintai\Contract\Http\Middleware\ApiPermissionMiddleware;
use kintai\Contract\Http\Middleware\PermissionMiddleware;
use kintai\Bundles\Installed\Timeclock\Controllers\Web\EmployeeTimeclockController;
use kintai\Bundles\Installed\Timeclock\Controllers\Web\AdminTimeclockController;
use kintai\Bundles\Installed\Timeclock\Controllers\Api\TimeclockController as ApiTimeclockController;

/** @var kintai\Contract\Http\Router $router */
/** @var kintai\Contract\Container $container */

// =============================================================================
// Timeclock — Routes Web Employé
// =============================================================================

$router->group('/employee', function ($r) {
    $r->get('/timeclock',            [EmployeeTimeclockController::class, 'timeclock'], name: 'employee.timeclock');
    $r->post('/timeclock/clock-in',  [EmployeeTimeclockController::class, 'clockIn'],   name: 'employee.timeclock.clock_in');
    $r->post('/timeclock/clock-out', [EmployeeTimeclockController::class, 'clockOut'],  name: 'employee.timeclock.clock_out');
}, middleware: [AuthMiddleware::class]);

// =============================================================================
// Timeclock — Routes Web Admin
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/timeclocks',              [AdminTimeclockController::class, 'timeclocks'],       name: 'admin.timeclocks', permission: 'timeclock.view');
    $r->post('/timeclocks/{id}/edit',   [AdminTimeclockController::class, 'timeclocksEdit'],   name: 'admin.timeclocks.edit', permission: 'timeclock.update');
    $r->post('/timeclocks/{id}/delete', [AdminTimeclockController::class, 'timeclocksDelete'], name: 'admin.timeclocks.delete', permission: 'timeclock.delete');
}, middleware: [AuthMiddleware::class, PermissionMiddleware::class]);

// =============================================================================
// Timeclock — Routes API
// =============================================================================

$router->group('/api/v1', function ($r) {
    $r->post('/timeclocks/clock-in',  [ApiTimeclockController::class, 'clockIn'],  name: 'api.v1.timeclock.clock_in', permission: ['perm' => 'timeclock.update', 'self' => 'user_id']);
    $r->post('/timeclocks/clock-out', [ApiTimeclockController::class, 'clockOut'], name: 'api.v1.timeclock.clock_out', permission: ['perm' => 'timeclock.update', 'self' => 'user_id']);
    $r->get('/timeclocks',            [ApiTimeclockController::class, 'index'],    name: 'api.v1.timeclock.index', permission: ['perm' => 'timeclock.view', 'self' => 'user_id']);
    $r->get('/timeclocks/{id}',       [ApiTimeclockController::class, 'show'],     name: 'api.v1.timeclock.show', permission: 'timeclock.view');
    $r->put('/timeclocks/{id}',       [ApiTimeclockController::class, 'update'],   name: 'api.v1.timeclock.update', permission: 'timeclock.update');
    $r->delete('/timeclocks/{id}',    [ApiTimeclockController::class, 'destroy'],  name: 'api.v1.timeclock.destroy', permission: 'timeclock.delete');
}, middleware: [ApiAuthMiddleware::class, ApiPermissionMiddleware::class]);
