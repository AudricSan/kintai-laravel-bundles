<?php

declare(strict_types=1);

use kintai\Contract\Http\Middleware\AuthMiddleware;
use kintai\Contract\Http\Middleware\PermissionMiddleware;
use kintai\Bundles\Installed\StorePhoto\Controllers\Web\StorePhotoController;

/** @var kintai\Contract\Http\Router $router */
/** @var kintai\Contract\Container $container */

// =============================================================================
// Store Photos — Routes Web (admin)
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/photos',                         [StorePhotoController::class, 'index'],        name: 'admin.photos.index', permission: 'photos.view');
    $r->get('/photos/settings',                [StorePhotoController::class, 'settings'],     name: 'admin.photos.settings', permission: 'photos.update');
    $r->post('/photos/settings',               [StorePhotoController::class, 'saveSettings'], name: 'admin.photos.settings.save', permission: 'photos.update');
    $r->get('/photos/create',                  [StorePhotoController::class, 'create'],       name: 'admin.photos.create', permission: 'photos.create');
    $r->post('/photos/create',                 [StorePhotoController::class, 'store'],        name: 'admin.photos.store', permission: 'photos.create');
    $r->post('/photos/{id}/upload',            [StorePhotoController::class, 'uploadFile'],   name: 'admin.photos.upload_file', permission: 'photos.create');
    $r->post('/photos/image/{image_id}/rotate', [StorePhotoController::class, 'rotateImage'],  name: 'admin.photos.image.rotate', permission: 'photos.update');
    $r->get('/photos/{store_id}/{id}',         [StorePhotoController::class, 'show'],         name: 'admin.photos.show', permission: 'photos.view');
    $r->post('/photos/{store_id}/{id}/delete', [StorePhotoController::class, 'delete'],       name: 'admin.photos.delete', permission: 'photos.delete');
}, middleware: [AuthMiddleware::class, PermissionMiddleware::class]);
