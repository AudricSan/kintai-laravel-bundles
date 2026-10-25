<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\HiringReport;

use kintai\Contract\Event\EmployeeCreated;
use kintai\Contract\Extension\NavItem;
use kintai\Bundles\Installed\HiringReport\HiringReportBundle;
use PHPUnit\Framework\TestCase;

/**
 * Ce que le bundle déclare au Core. Le Core n'a plus de repli pour hiring-report (étape 4 du plan du contrat,
 * extraction) : ces déclarations sont la seule source de son entrée de menu, de sa catégorie de permissions et de la
 * création automatique des rapports, d'où des valeurs vérifiées une à une.
 */
final class ContractDeclarationsTest extends TestCase
{
    private function bundle(): HiringReportBundle
    {
        return (new \ReflectionClass(HiringReportBundle::class))->newInstanceWithoutConstructor();
    }

    public function testNavigation(): void
    {
        $items = $this->bundle()->navigation();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(NavItem::class, $items[0]);
        $this->assertSame('hiring_report', $items[0]->key);
        $this->assertSame(NavItem::REPORTS, $items[0]->section);
        $this->assertSame('hiring_report_hiring_reports', $items[0]->label);
        $this->assertSame('admin.reports.hiring', $items[0]->managerRoute);
        $this->assertSame('admin.reports.hiring', $items[0]->employeeRoute);
        $this->assertSame('person', $items[0]->icon);
        $this->assertSame(['*/reports/hiring'], $items[0]->activePaths);
        $this->assertNull($items[0]->storeFeature);
        $this->assertSame(10, $items[0]->order);
    }

    public function testPermissions(): void
    {
        $this->assertSame(['hiring_reports' => ['view', 'create', 'update', 'delete']], $this->bundle()->permissions());
    }

    /** Le bundle écoute la création d'un employé, et rien d'autre. */
    public function testEventListeners(): void
    {
        $listeners = $this->bundle()->eventListeners();

        $this->assertSame([EmployeeCreated::class], array_keys($listeners));
        $this->assertIsCallable($listeners[EmployeeCreated::class]);
    }

    /** Le bundle n'apporte rien d'autre au Core. */
    public function testNoOtherContribution(): void
    {
        $bundle = $this->bundle();

        $this->assertSame([], $bundle->storeFeatures());
        $this->assertSame([], $bundle->dashboardWidgets());
        $this->assertSame([], $bundle->viewVariables());
        $this->assertSame([], $bundle->actions());
        $this->assertSame([], $bundle->profileSections());
        $this->assertSame([], $bundle->notificationTypes());
        $this->assertSame([], $bundle->cronJobs());
        $this->assertSame([], $bundle->pendingRequestsProviders());
        $this->assertSame([], $bundle->absenceProviders());
        $this->assertSame([], $bundle->attendanceProviders());
    }

    /** Libellés des déclarations et des pages, fournis par le bundle. */
    public function testDeclaredLabelsAreTranslatedByTheBundle(): void
    {
        $keys = ['hiring_report_hiring_reports', 'perm_category_hiring_reports', 'hiring_report_error_not_found', 'hiring_report_store_picker_choose', 'hiring_report_all_months', 'hiring_report_all_years'];
        foreach (['fr', 'en', 'ja'] as $locale) {
            $lang = json_decode((string) file_get_contents(dirname(__DIR__) . "/lang/{$locale}.json"), true);
            foreach ($keys as $key) {
                $this->assertNotEmpty($lang[$key] ?? null, "{$locale} : {$key}");
            }
        }
    }
}
