<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Timeclock;

use kintai\Bundles\Installed\Timeclock\Providers\TimeclockAttendanceProvider;
use kintai\Bundles\Installed\Timeclock\TimeclockBundle;
use kintai\Contract\Extension\DashboardWidget;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\StoreFeature;
use PHPUnit\Framework\TestCase;

/**
 * Ce que le bundle déclare au Core. Le Core n'a plus de repli pour timeclock (étape 4 du plan du contrat, extraction) :
 * ces déclarations sont la seule source de son entrée de menu, de sa catégorie de permissions, de sa fonctionnalité
 * par magasin, des pointages lus par le Core et des widgets de pointage des tableaux de bord, d'où des valeurs
 * vérifiées une à une.
 */
final class ContractDeclarationsTest extends TestCase
{
    private function bundle(): TimeclockBundle
    {
        return (new \ReflectionClass(TimeclockBundle::class))->newInstanceWithoutConstructor();
    }

    public function testNavigation(): void
    {
        $items = $this->bundle()->navigation();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(NavItem::class, $items[0]);
        $this->assertSame(['timeclocks', NavItem::PLANNING, 'timeclock_timeclocks', 'admin.timeclocks', 'employee.timeclock', 'clock', 'timeclock'],
            [$items[0]->key, $items[0]->section, $items[0]->label, $items[0]->managerRoute, $items[0]->employeeRoute, $items[0]->icon, $items[0]->employeeLabel]);
        $this->assertSame(['/admin/timeclocks', '/employee/timeclock'], $items[0]->activePaths);
        $this->assertSame('timeclock', $items[0]->storeFeature);
        $this->assertSame(40, $items[0]->order);
        $this->assertTrue($items[0]->mobile);
    }

    public function testPermissions(): void
    {
        $this->assertSame(['timeclock' => ['view', 'update', 'delete']], $this->bundle()->permissions());
    }

    public function testStoreFeature(): void
    {
        $features = $this->bundle()->storeFeatures();

        $this->assertCount(1, $features);
        $this->assertInstanceOf(StoreFeature::class, $features[0]);
        $this->assertSame(['timeclock', 'timeclock_feature_timeclock', 'timeclock_feature_desc', '🕐', 20],
            [$features[0]->key, $features[0]->label, $features[0]->description, $features[0]->icon, $features[0]->order]);
    }

    public function testAttendanceProvider(): void
    {
        $providers = $this->bundle()->attendanceProviders();

        $this->assertCount(1, $providers);
        $this->assertInstanceOf(TimeclockAttendanceProvider::class, $providers[0]);
    }

    /** Mêmes clés que les widgets que le Core affichait : les choix des utilisateurs sont conservés. */
    public function testDashboardWidgets(): void
    {
        $widgets = $this->bundle()->dashboardWidgets();

        $this->assertCount(2, $widgets);
        [$manager, $employee] = $widgets;
        $this->assertInstanceOf(DashboardWidget::class, $manager);
        $this->assertSame(['timeclocks_today', DashboardWidget::MANAGER, 'timeclock_widget_timeclocks_today', 'timeclock::widgets.dashboard-manager', 'timeclock.view', 'timeclock'],
            [$manager->key, $manager->audience, $manager->label, $manager->view, $manager->permission, $manager->storeFeature]);
        $this->assertSame(['timeclock', DashboardWidget::EMPLOYEE, 'timeclock', 'timeclock::widgets.dashboard-employee', null, 'timeclock'],
            [$employee->key, $employee->audience, $employee->label, $employee->view, $employee->permission, $employee->storeFeature]);
        $this->assertFileExists(dirname(__DIR__) . '/Views/widgets/dashboard-manager.php');
        $this->assertFileExists(dirname(__DIR__) . '/Views/widgets/dashboard-employee.php');
    }

    /** Le bundle n'apporte rien d'autre au Core. */
    public function testNoOtherContribution(): void
    {
        $bundle = $this->bundle();

        $this->assertSame([], $bundle->actions());
        $this->assertSame([], $bundle->pendingRequestsProviders());
        $this->assertSame([], $bundle->viewVariables());
        $this->assertSame([], $bundle->profileSections());
        $this->assertSame([], $bundle->cronJobs());
        $this->assertSame([], $bundle->eventListeners());
        $this->assertSame([], $bundle->absenceProviders());
        $this->assertSame([], $bundle->salesProviders());
        $this->assertSame([], $bundle->swapProviders());
        $this->assertSame([], $bundle->dashboardStats());
        $this->assertSame([], $bundle->shiftFormSections());
        $this->assertSame([], $bundle->notificationTypes());
    }

    /** Libellés des déclarations, du journal d'activité et des widgets, fournis par le bundle. */
    public function testDeclaredLabelsAreTranslatedByTheBundle(): void
    {
        $keys = ['timeclock_timeclocks', 'timeclock', 'perm_category_timeclock', 'timeclock_feature_timeclock', 'timeclock_feature_desc',
            'timeclock_widget_timeclocks_today', 'timeclock_no_active_timeclock', 'timeclock_clocked_in', 'timeclock_clocked_in_since', 'timeclock_not_clocked_in', 'timeclock_clock_in',
            'timeclock_clock_out', 'audit_timeclock_clock_in', 'audit_timeclock_clock_out', 'audit_timeclock_edited', 'audit_timeclock_deleted'];
        foreach (['fr', 'en', 'ja'] as $locale) {
            $lang = json_decode((string) file_get_contents(dirname(__DIR__) . "/lang/{$locale}.json"), true);
            foreach ($keys as $key) {
                $this->assertNotEmpty($lang[$key] ?? null, "{$locale} : {$key}");
            }
        }
    }
}
