<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftClaim;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use kintai\Core\Container;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Router;

/**
 * Régression : les routes /admin/open-shifts, /employee/open-shifts et
 * /api/v1/shift-claims du bundle ShiftClaim doivent rester protégées après
 * leur extraction du core.
 *
 * Depuis son extraction du monorepo (voir docs/architecture.md "Modular
 * Bundles"), le code réel du bundle vit hors de ce dépôt
 * (kintai-bundle-shift-claim) ; tests/Fixtures/bundles/shift-claim-1.0.0/
 * en est une copie fidèle, maintenue en même temps que le dépôt externe.
 */
final class ShiftClaimRoutesTest extends TestCase
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
            ['GET', '/employee/open-shifts'],
            ['POST', '/employee/open-shifts/1/claim'],
            ['POST', '/employee/open-shifts/1/withdraw'],
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
            ['GET', '/admin/open-shifts'],
            ['GET', '/admin/open-shifts/publish'],
            ['POST', '/admin/shifts/1/publish'],
            ['POST', '/admin/shifts/1/unpublish'],
            ['POST', '/admin/open-shifts/1/approve'],
            ['POST', '/admin/open-shifts/1/reject'],
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
            ['GET', '/api/v1/shift-claims'],
            ['POST', '/api/v1/shift-claims'],
            ['GET', '/api/v1/shift-claims/1'],
            ['PUT', '/api/v1/shift-claims/1'],
            ['DELETE', '/api/v1/shift-claims/1'],
        ];
    }
}
