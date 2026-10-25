<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftSwap;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use kintai\Core\Container;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Router;

/**
 * Régression : les routes /admin/swap-requests, /employee/swaps et
 * /api/v1/shift-swap-requests du bundle ShiftSwap doivent rester protégées
 * après leur extraction du core.
 */
final class ShiftSwapRoutesTest extends TestCase
{
    private function loadRoutes(): Router
    {
        $router = new Router();
        $container = new Container();
        require dirname(__DIR__, 1) . '/routes.php';

        return $router;
    }

    #[DataProvider('employeeRoutesProvider')]
    public function testEmployeeRoutesRequireAuth(string $method, string $path): void
    {
        [$route] = $this->loadRoutes()->dispatch($method, $path);

        $this->assertContains(AuthMiddleware::class, $route->middleware);
    }

    public static function employeeRoutesProvider(): array
    {
        return [
            ['GET', '/employee/swaps'],
            ['GET', '/employee/swaps/create'],
            ['POST', '/employee/swaps/create'],
            ['POST', '/employee/swaps/1/accept'],
            ['POST', '/employee/swaps/1/refuse'],
            ['POST', '/employee/swaps/1/cancel'],
        ];
    }

    #[DataProvider('adminRoutesProvider')]
    public function testAdminRoutesRequireAdminAuth(string $method, string $path): void
    {
        [$route] = $this->loadRoutes()->dispatch($method, $path);

        $this->assertContains(AuthMiddleware::class, $route->middleware);
        $this->assertContains(PermissionMiddleware::class, $route->middleware);
    }

    public static function adminRoutesProvider(): array
    {
        return [
            ['GET', '/admin/swap-requests'],
            ['GET', '/admin/swap-requests/create'],
            ['POST', '/admin/swap-requests/create'],
            ['POST', '/admin/swap-requests/1/approve'],
            ['POST', '/admin/swap-requests/1/refuse'],
            ['POST', '/admin/swap-requests/1/delete'],
        ];
    }

    #[DataProvider('apiRoutesProvider')]
    public function testApiRoutesRequireBearerToken(string $method, string $path): void
    {
        [$route] = $this->loadRoutes()->dispatch($method, $path);

        $this->assertContains(ApiAuthMiddleware::class, $route->middleware);
    }

    public static function apiRoutesProvider(): array
    {
        return [
            ['GET', '/api/v1/shift-swap-requests'],
            ['POST', '/api/v1/shift-swap-requests'],
            ['GET', '/api/v1/shift-swap-requests/1'],
            ['PUT', '/api/v1/shift-swap-requests/1'],
            ['DELETE', '/api/v1/shift-swap-requests/1'],
        ];
    }
}
