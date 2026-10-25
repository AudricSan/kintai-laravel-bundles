<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\DailyReport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use kintai\Core\Container;
use kintai\Core\Middleware\ApiAuthMiddleware;
use kintai\Core\Middleware\ApiPermissionMiddleware;
use kintai\Core\Middleware\AuthMiddleware;
use kintai\Core\Middleware\PermissionMiddleware;
use kintai\Core\Router;

/**
 * Régression : les routes du bundle DailyReport doivent rester protégées par
 * PermissionMiddleware/ApiPermissionMiddleware après leur ajout au système
 * déclaratif de permissions. Le groupe en libre-service ne doit déclarer
 * aucune permission stricte (règle 'membership' uniquement) : PermissionMiddleware
 * (qui a absorbé l'ancien AdminMiddleware en RBAC-V2) vérifie le repli
 * 'membership' AVANT sa porte grossière "au moins une permission RBAC quelque
 * part" — sinon un simple employé qui n'est manager d'aucun store ne pourrait
 * jamais soumettre son propre rapport.
 *
 * Depuis son extraction du monorepo (voir docs/architecture.md "Modular
 * Bundles"), le code réel du bundle vit hors de ce dépôt
 * (kintai-bundle-daily-report) ; tests/Fixtures/bundles/daily-report-1.0.0/
 * en est une copie fidèle, maintenue en même temps que le dépôt externe.
 */
final class DailyReportRoutesTest extends TestCase
{
    private function loadRoutes(): Router
    {
        $router = new Router();
        $container = new Container();
        require dirname(__DIR__, 1) . '/routes.php';

        return $router;
    }

    #[DataProvider('selfServiceRoutesProvider')]
    public function testSelfServiceRoutesAreAuthenticatedAndPermissionChecked(string $method, string $path): void
    {
        [$route] = $this->loadRoutes()->dispatch($method, $path);

        $this->assertContains(AuthMiddleware::class, $route->middleware);
        $this->assertContains(PermissionMiddleware::class, $route->middleware);
        $this->assertIsArray($route->permission, "$path doit déclarer une règle 'membership' (accès en libre-service par appartenance au store).");
        $this->assertTrue($route->permission['membership'] ?? false, "$path doit rester en libre-service (règle 'membership') pour qu'un simple employé puisse soumettre son propre rapport.");
    }

    public static function selfServiceRoutesProvider(): array
    {
        return [
            ['GET', '/admin/stores/1/daily-reports'],
            ['GET', '/admin/stores/1/daily-reports/create'],
            ['POST', '/admin/stores/1/daily-reports/create'],
            ['GET', '/admin/stores/1/daily-reports/10'],
            ['GET', '/admin/stores/1/daily-reports/10/edit'],
            ['POST', '/admin/stores/1/daily-reports/10/edit'],
            ['POST', '/admin/stores/1/daily-reports/10/submit'],
            ['GET', '/admin/stores/1/daily-reports/10/pdf'],
            ['GET', '/admin/stores/1/daily-reports/10/pdf/download'],
        ];
    }

    public function testIndexAllRouteRequiresPermission(): void
    {
        [$route] = $this->loadRoutes()->dispatch('GET', '/admin/daily-reports');

        $this->assertContains(AuthMiddleware::class, $route->middleware);
        $this->assertContains(PermissionMiddleware::class, $route->middleware);
        $this->assertSame('daily_reports.view', $route->permission);
    }

    #[DataProvider('apiRoutesProvider')]
    public function testApiRoutesRequireTokenAuthAndPermission(string $method, string $path): void
    {
        [$route] = $this->loadRoutes()->dispatch($method, $path);

        $this->assertContains(ApiAuthMiddleware::class, $route->middleware);
        $this->assertContains(ApiPermissionMiddleware::class, $route->middleware);
    }

    public static function apiRoutesProvider(): array
    {
        return [
            ['GET', '/api/v1/daily-reports'],
            ['POST', '/api/v1/daily-reports'],
            ['GET', '/api/v1/daily-reports/10'],
            ['PUT', '/api/v1/daily-reports/10'],
            ['DELETE', '/api/v1/daily-reports/10'],
            ['POST', '/api/v1/daily-reports/10/submit'],
            ['POST', '/api/v1/daily-reports/10/validate'],
        ];
    }
}
