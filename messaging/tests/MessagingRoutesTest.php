<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Messaging;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use kintai\Core\Container;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Core\Router;

/**
 * Régression : Messaging était le seul bundle des onze jamais raccordé au
 * RBAC — ses routes /admin/messages/* ne passaient que par le filtre grossier
 * "gestionnaire d'au moins un store" (l'ancien AdminMiddleware), donc
 * n'importe quel manager pouvait tout lire/envoyer/supprimer indépendamment
 * de son rôle assigné.
 *
 * Depuis son extraction du monorepo (voir docs/architecture.md "Modular
 * Bundles"), le code réel du bundle vit hors de ce dépôt
 * (kintai-bundle-messaging) ; tests/Fixtures/bundles/messaging-1.0.0/ en
 * est une copie fidèle, maintenue en même temps que le dépôt externe.
 */
final class MessagingRoutesTest extends TestCase
{
    private function loadRoutes(): Router
    {
        $router = new Router();
        $container = new Container();
        require dirname(__DIR__, 1) . '/routes.php';

        return $router;
    }

    #[DataProvider('employeeRoutesProvider')]
    public function testEmployeeRoutesRequireAuthOnly(string $method, string $path): void
    {
        [$route] = $this->loadRoutes()->dispatch($method, $path);

        $this->assertContains(AuthMiddleware::class, $route->middleware);
    }

    public static function employeeRoutesProvider(): array
    {
        return [
            ['GET', '/employee/messages/compose'],
            ['POST', '/employee/messages'],
            ['GET', '/employee/messages'],
            ['GET', '/employee/messages/10'],
            ['POST', '/employee/messages/10'],
            ['POST', '/employee/messages/10/delete'],
            ['POST', '/employee/messages/10/message/5/delete'],
        ];
    }

    #[DataProvider('adminRoutesProvider')]
    public function testAdminRoutesRequirePermissionMiddleware(string $method, string $path): void
    {
        [$route] = $this->loadRoutes()->dispatch($method, $path);

        $this->assertContains(AuthMiddleware::class, $route->middleware);
        $this->assertContains(PermissionMiddleware::class, $route->middleware);
    }

    public static function adminRoutesProvider(): array
    {
        return [
            ['GET', '/admin/messages/compose'],
            ['POST', '/admin/messages'],
            ['GET', '/admin/messages'],
            ['GET', '/admin/messages/10'],
            ['POST', '/admin/messages/10'],
            ['POST', '/admin/messages/10/delete'],
            ['POST', '/admin/messages/10/message/5/delete'],
        ];
    }

    #[DataProvider('apiRoutesProvider')]
    public function testApiRoutesRequireApiAuth(string $method, string $path): void
    {
        [$route] = $this->loadRoutes()->dispatch($method, $path);

        $this->assertContains(ApiAuthMiddleware::class, $route->middleware);
    }

    public static function apiRoutesProvider(): array
    {
        return [
            ['GET', '/api/v1/messages/threads'],
            ['POST', '/api/v1/messages/threads'],
            ['GET', '/api/v1/messages/threads/10/messages'],
            ['POST', '/api/v1/messages/threads/10/messages'],
            ['GET', '/api/v1/messages/threads/10/participants'],
            ['POST', '/api/v1/messages/threads/10/participants'],
            ['GET', '/api/v1/messages/threads/10/participants/3'],
            ['GET', '/api/v1/messages/threads/10'],
            ['DELETE', '/api/v1/messages/threads/10'],
            ['DELETE', '/api/v1/messages/10'],
        ];
    }
}
