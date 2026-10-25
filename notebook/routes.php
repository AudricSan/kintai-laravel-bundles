<?php

declare(strict_types=1);

use kintai\Contract\Http\Middleware\AuthMiddleware;
use kintai\Contract\Http\Middleware\ApiAuthMiddleware;
use kintai\Contract\Http\Middleware\ApiPermissionMiddleware;
use kintai\Contract\Http\Middleware\PermissionMiddleware;
use kintai\Bundles\Installed\Notebook\Controllers\Web\NotebookController;
use kintai\Bundles\Installed\Notebook\Controllers\Api\NotebookController as ApiNotebookController;

/** @var kintai\Contract\Http\Router $router */
/** @var kintai\Contract\Container $container */

// =============================================================================
// Notebook — Routes Web
// =============================================================================
// Un seul groupe : l'accès est purement RBAC (notebook.view/create/manage),
// pas basé sur le rôle admin/employé — contrairement à Feedback/Messaging qui
// séparent /admin et /employee, la lecture et l'écriture d'une note sont
// accessibles à qui détient la permission, où qu'il/elle soit dans l'app.

$router->group('/notebook', function ($r) {
    $r->get('',                [NotebookController::class, 'index'],     name: 'notebook.index',      permission: 'notebook.view');
    $r->post('',               [NotebookController::class, 'store'],     name: 'notebook.store',      permission: 'notebook.create');
    $r->post('/{id}/update',  [NotebookController::class, 'update'],    name: 'notebook.update',     permission: 'notebook.create');
    $r->post('/{id}/delete',  [NotebookController::class, 'destroy'],   name: 'notebook.delete',     permission: 'notebook.create');
    $r->post('/{id}/pin',     [NotebookController::class, 'togglePin'], name: 'notebook.pin',         permission: 'notebook.manage');
}, middleware: [AuthMiddleware::class, PermissionMiddleware::class]);

// =============================================================================
// Notebook — Routes API
// =============================================================================

$router->group('/api/v1', function ($r) {
    $r->get('/notebook-entries',         [ApiNotebookController::class, 'index'],   name: 'api.v1.notebook_entries.index',   permission: 'notebook.view');
    $r->post('/notebook-entries',        [ApiNotebookController::class, 'store'],   name: 'api.v1.notebook_entries.store',   permission: 'notebook.create');
    $r->get('/notebook-entries/{id}',    [ApiNotebookController::class, 'show'],    name: 'api.v1.notebook_entries.show',    permission: 'notebook.view');
    $r->put('/notebook-entries/{id}',    [ApiNotebookController::class, 'update'],  name: 'api.v1.notebook_entries.update',  permission: 'notebook.create');
    $r->delete('/notebook-entries/{id}', [ApiNotebookController::class, 'destroy'], name: 'api.v1.notebook_entries.destroy', permission: 'notebook.create');
}, middleware: [ApiAuthMiddleware::class, ApiPermissionMiddleware::class]);
