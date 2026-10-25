<?php

declare(strict_types=1);

use kintai\Contract\Http\Middleware\AuthMiddleware;
use kintai\Contract\Http\Middleware\ApiAuthMiddleware;
use kintai\Contract\Http\Middleware\ApiPermissionMiddleware;
use kintai\Contract\Http\Middleware\PermissionMiddleware;
use kintai\Bundles\Installed\ShiftClaim\Controllers\Web\EmployeeShiftClaimController;
use kintai\Bundles\Installed\ShiftClaim\Controllers\Web\AdminShiftClaimController;
use kintai\Bundles\Installed\ShiftClaim\Controllers\Api\ShiftClaimController as ApiShiftClaimController;

/** @var kintai\Contract\Http\Router $router */
/** @var kintai\Contract\Container $container */

// =============================================================================
// ShiftClaim — Routes Web (employé)
// =============================================================================

$router->group('/employee', function ($r) {
    $r->get('/open-shifts',                [EmployeeShiftClaimController::class, 'openShifts'],         name: 'employee.open_shifts');
    $r->post('/open-shifts/{id}/claim',    [EmployeeShiftClaimController::class, 'claimShift'],          name: 'employee.open_shifts.claim');
    $r->post('/open-shifts/{id}/withdraw', [EmployeeShiftClaimController::class, 'withdrawShiftClaim'],  name: 'employee.open_shifts.withdraw');
}, middleware: [AuthMiddleware::class]);

// =============================================================================
// ShiftClaim — Routes Web (admin)
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/open-shifts',               [AdminShiftClaimController::class, 'openShifts'],           name: 'admin.open_shifts', permission: 'open_shifts.view');
    $r->get('/open-shifts/publish',       [AdminShiftClaimController::class, 'selectShiftToPublish'], name: 'admin.open_shifts.select', permission: 'open_shifts.publish');
    $r->post('/shifts/{id}/publish',      [AdminShiftClaimController::class, 'publishShift'],         name: 'admin.shifts.publish', permission: 'open_shifts.publish');
    $r->post('/shifts/{id}/unpublish',    [AdminShiftClaimController::class, 'unpublishShift'],       name: 'admin.shifts.unpublish', permission: 'open_shifts.publish');
    $r->post('/open-shifts/{id}/approve', [AdminShiftClaimController::class, 'approveShiftClaim'],    name: 'admin.open_shifts.approve', permission: 'open_shifts.approve');
    $r->post('/open-shifts/{id}/reject',  [AdminShiftClaimController::class, 'rejectShiftClaim'],     name: 'admin.open_shifts.reject', permission: 'open_shifts.approve');
}, middleware: [AuthMiddleware::class, PermissionMiddleware::class]);

// =============================================================================
// ShiftClaim — Routes API
// =============================================================================

$router->group('/api/v1', function ($r) {
    $r->get('/shift-claims',         [ApiShiftClaimController::class, 'index'],   name: 'api.v1.shift_claims.index', permission: ['perm' => 'open_shifts.view', 'self' => 'user_id']);
    $r->post('/shift-claims',        [ApiShiftClaimController::class, 'store'],   name: 'api.v1.shift_claims.store', permission: ['perm' => 'open_shifts.approve', 'self' => 'user_id']);
    $r->get('/shift-claims/{id}',    [ApiShiftClaimController::class, 'show'],    name: 'api.v1.shift_claims.show', permission: 'open_shifts.view');
    $r->put('/shift-claims/{id}',    [ApiShiftClaimController::class, 'update'],  name: 'api.v1.shift_claims.update', permission: 'open_shifts.approve');
    $r->delete('/shift-claims/{id}', [ApiShiftClaimController::class, 'destroy'], name: 'api.v1.shift_claims.destroy', permission: 'open_shifts.approve');
}, middleware: [ApiAuthMiddleware::class, ApiPermissionMiddleware::class]);
