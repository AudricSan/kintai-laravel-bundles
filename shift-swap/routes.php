<?php

declare(strict_types=1);

use kintai\Contract\Http\Middleware\AuthMiddleware;
use kintai\Contract\Http\Middleware\ApiAuthMiddleware;
use kintai\Contract\Http\Middleware\ApiPermissionMiddleware;
use kintai\Contract\Http\Middleware\PermissionMiddleware;
use kintai\Bundles\Installed\ShiftSwap\Controllers\Web\EmployeeSwapController;
use kintai\Bundles\Installed\ShiftSwap\Controllers\Web\AdminSwapController;
use kintai\Bundles\Installed\ShiftSwap\Controllers\Api\ShiftSwapRequestController as ApiShiftSwapRequestController;

/** @var kintai\Contract\Http\Router $router */
/** @var kintai\Contract\Container $container */

// =============================================================================
// ShiftSwap — Routes Web (employé)
// =============================================================================

$router->group('/employee', function ($r) {
    $r->get('/swaps',              [EmployeeSwapController::class, 'swaps'],       name: 'employee.swaps');
    $r->get('/swaps/create',       [EmployeeSwapController::class, 'createSwap'],  name: 'employee.swaps.create');
    $r->post('/swaps/create',      [EmployeeSwapController::class, 'storeSwap'],   name: 'employee.swaps.store');
    $r->post('/swaps/{id}/accept', [EmployeeSwapController::class, 'acceptSwap'],  name: 'employee.swaps.accept');
    $r->post('/swaps/{id}/refuse', [EmployeeSwapController::class, 'refuseSwap'],  name: 'employee.swaps.refuse');
    $r->post('/swaps/{id}/cancel', [EmployeeSwapController::class, 'cancelSwap'],  name: 'employee.swaps.cancel');
}, middleware: [AuthMiddleware::class]);

// =============================================================================
// ShiftSwap — Routes Web (admin)
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/swap-requests',               [AdminSwapController::class, 'swapRequests'], name: 'admin.swap_requests', permission: 'swaps.view');
    $r->get('/swap-requests/create',        [AdminSwapController::class, 'createSwap'],   name: 'admin.swap_requests.create', permission: 'swaps.create');
    $r->post('/swap-requests/create',       [AdminSwapController::class, 'storeSwap'],    name: 'admin.swap_requests.store', permission: 'swaps.create');
    $r->post('/swap-requests/{id}/approve', [AdminSwapController::class, 'approveSwap'],  name: 'admin.swap.approve', permission: 'swaps.approve');
    $r->post('/swap-requests/{id}/refuse',  [AdminSwapController::class, 'refuseSwap'],   name: 'admin.swap.refuse', permission: 'swaps.approve');
    $r->post('/swap-requests/{id}/delete',  [AdminSwapController::class, 'deleteSwap'],   name: 'admin.swap.delete', permission: 'swaps.delete');
}, middleware: [AuthMiddleware::class, PermissionMiddleware::class]);

// =============================================================================
// ShiftSwap — Routes API
// =============================================================================

$router->group('/api/v1', function ($r) {
    $r->get('/shift-swap-requests',         [ApiShiftSwapRequestController::class, 'index'],   name: 'api.v1.swap.index', permission: ['perm' => 'swaps.view', 'self' => 'user_id']);
    $r->post('/shift-swap-requests',        [ApiShiftSwapRequestController::class, 'store'],   name: 'api.v1.swap.store', permission: 'swaps.create');
    $r->get('/shift-swap-requests/{id}',    [ApiShiftSwapRequestController::class, 'show'],    name: 'api.v1.swap.show', permission: 'swaps.view');
    $r->put('/shift-swap-requests/{id}',    [ApiShiftSwapRequestController::class, 'update'],  name: 'api.v1.swap.update', permission: 'swaps.update');
    $r->delete('/shift-swap-requests/{id}', [ApiShiftSwapRequestController::class, 'destroy'], name: 'api.v1.swap.destroy', permission: 'swaps.delete');
}, middleware: [ApiAuthMiddleware::class, ApiPermissionMiddleware::class]);
