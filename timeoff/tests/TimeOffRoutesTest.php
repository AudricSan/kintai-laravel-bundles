<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\TimeOff;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use kintai\Core\Container;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Router;

/**
 * Régression : les routes /admin/timeoff, /employee/timeoff et /api/v1/timeoff-requests
 * du bundle TimeOff doivent rester protégées après leur extraction du core.
 */
final class TimeOffRoutesTest extends TestCase
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
            ['GET', '/employee/timeoff'],
            ['POST', '/employee/timeoff'],
            ['POST', '/employee/timeoff/1/cancel'],
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
            ['GET', '/admin/timeoff'],
            ['GET', '/admin/timeoff/create'],
            ['POST', '/admin/timeoff/create'],
            ['POST', '/admin/timeoff/1/approve'],
            ['POST', '/admin/timeoff/1/refuse'],
            ['POST', '/admin/timeoff/1/delete'],
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
            ['GET', '/api/v1/timeoff-requests'],
            ['POST', '/api/v1/timeoff-requests'],
            ['GET', '/api/v1/timeoff-requests/1'],
            ['PUT', '/api/v1/timeoff-requests/1'],
            ['DELETE', '/api/v1/timeoff-requests/1'],
        ];
    }
}
