<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Timeclock;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use kintai\Core\Container;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Router;

/**
 * Régression : les routes /employee/timeclock*, /admin/timeclocks/* et
 * /api/v1/timeclocks/* du bundle Timeclock doivent rester protégées après
 * leur extraction du core.
 */
final class TimeclockRoutesTest extends TestCase
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
            ['GET', '/employee/timeclock'],
            ['POST', '/employee/timeclock/clock-in'],
            ['POST', '/employee/timeclock/clock-out'],
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
            ['GET', '/admin/timeclocks'],
            ['POST', '/admin/timeclocks/10/edit'],
            ['POST', '/admin/timeclocks/10/delete'],
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
            ['POST', '/api/v1/timeclocks/clock-in'],
            ['POST', '/api/v1/timeclocks/clock-out'],
            ['GET', '/api/v1/timeclocks'],
            ['GET', '/api/v1/timeclocks/10'],
            ['PUT', '/api/v1/timeclocks/10'],
            ['DELETE', '/api/v1/timeclocks/10'],
        ];
    }
}
