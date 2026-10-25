<?php

declare(strict_types=1);

use kintai\Contract\Http\Middleware\AuthMiddleware;
use kintai\Contract\Http\Middleware\ApiAuthMiddleware;
use kintai\Contract\Http\Middleware\PermissionMiddleware;
use kintai\Bundles\Installed\Messaging\Controllers\Web\MessageController;
use kintai\Bundles\Installed\Messaging\Controllers\Web\MessageStreamController;
use kintai\Bundles\Installed\Messaging\Controllers\Api\MessageController as ApiMessageController;

/** @var kintai\Contract\Http\Router $router */
/** @var kintai\Contract\Container $container */

// =============================================================================
// Messaging — SSE Stream
// =============================================================================

$router->get('/messages/{id}/stream', [MessageStreamController::class, 'stream'], middleware: [AuthMiddleware::class], name: 'messages.stream');

// =============================================================================
// Messaging — Routes Web Admin
// =============================================================================

$router->group('/admin', function ($r) {
    $r->get('/messages/compose',                    [MessageController::class, 'messagesCompose'],       name: 'admin.messages.compose', permission: 'messaging.send');
    $r->post('/messages',                           [MessageController::class, 'messagesSend'],          name: 'admin.messages.send', permission: 'messaging.send');
    $r->get('/messages',                            [MessageController::class, 'messages'],               name: 'admin.messages', permission: 'messaging.view');
    $r->get('/messages/{id}',                       [MessageController::class, 'messagesThread'],         name: 'admin.messages.thread', permission: 'messaging.view');
    $r->post('/messages/{id}',                      [MessageController::class, 'messagesReply'],          name: 'admin.messages.reply', permission: 'messaging.send');
    $r->post('/messages/{id}/delete',               [MessageController::class, 'messagesDeleteThread'],   name: 'admin.messages.delete_thread', permission: 'messaging.delete');
    $r->post('/messages/{id}/message/{mid}/delete', [MessageController::class, 'messagesDeleteMessage'],  name: 'admin.messages.delete_message', permission: 'messaging.delete');
}, middleware: [AuthMiddleware::class, PermissionMiddleware::class]);

// =============================================================================
// Messaging — Routes Web Employé
// =============================================================================

$router->group('/employee', function ($r) {
    $r->get('/messages/compose',                    [MessageController::class, 'messagesCompose'],       name: 'employee.messages.compose');
    $r->post('/messages',                           [MessageController::class, 'messagesSend'],          name: 'employee.messages.send');
    $r->get('/messages',                            [MessageController::class, 'messages'],               name: 'employee.messages');
    $r->get('/messages/{id}',                       [MessageController::class, 'messagesThread'],         name: 'employee.messages.thread');
    $r->post('/messages/{id}',                      [MessageController::class, 'messagesReply'],          name: 'employee.messages.reply');
    $r->post('/messages/{id}/delete',               [MessageController::class, 'messagesDeleteThread'],   name: 'employee.messages.delete_thread');
    $r->post('/messages/{id}/message/{mid}/delete', [MessageController::class, 'messagesDeleteMessage'],  name: 'employee.messages.delete_message');
}, middleware: [AuthMiddleware::class]);

// =============================================================================
// Messaging — Routes API
// =============================================================================

$router->group('/api/v1', function ($r) {
    $r->get('/messages/threads',                              [ApiMessageController::class, 'listThreads'],     name: 'api.v1.messages.threads.index');
    $r->post('/messages/threads',                             [ApiMessageController::class, 'createThread'],    name: 'api.v1.messages.threads.store');
    $r->get('/messages/threads/{id}/messages',                [ApiMessageController::class, 'listMessages'],    name: 'api.v1.messages.list');
    $r->post('/messages/threads/{id}/messages',               [ApiMessageController::class, 'addMessage'],      name: 'api.v1.messages.store');
    $r->get('/messages/threads/{id}/participants',            [ApiMessageController::class, 'listParticipants'], name: 'api.v1.messages.participants.index');
    $r->post('/messages/threads/{id}/participants',           [ApiMessageController::class, 'addParticipant'],   name: 'api.v1.messages.participants.store');
    $r->get('/messages/threads/{id}/participants/{user_id}',  [ApiMessageController::class, 'getParticipant'],   name: 'api.v1.messages.participants.show');
    $r->get('/messages/threads/{id}',                         [ApiMessageController::class, 'getThread'],        name: 'api.v1.messages.threads.show');
    $r->delete('/messages/threads/{id}',                      [ApiMessageController::class, 'deleteThread'],     name: 'api.v1.messages.threads.destroy');
    $r->delete('/messages/{id}',                              [ApiMessageController::class, 'deleteMessage'],    name: 'api.v1.messages.destroy');
}, middleware: [ApiAuthMiddleware::class]);
