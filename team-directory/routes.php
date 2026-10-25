<?php

declare(strict_types=1);

use kintai\Contract\Http\Middleware\AuthMiddleware;
use kintai\Bundles\Installed\TeamDirectory\Controllers\Web\TeamDirectoryController;

/** @var kintai\Contract\Http\Router $router */
/** @var kintai\Contract\Container $container */

// =============================================================================
// Team Directory — Routes Web (tout utilisateur connecté, pas de RBAC :
// libre-service au même titre que /profile)
// =============================================================================

$router->group('/team', function ($r) {
    $r->get('',      [TeamDirectoryController::class, 'index'], name: 'team.index');
    $r->get('/{id}', [TeamDirectoryController::class, 'show'],  name: 'team.show');
}, middleware: [AuthMiddleware::class]);
