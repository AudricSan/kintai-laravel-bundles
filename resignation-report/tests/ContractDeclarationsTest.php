<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ResignationReport;

use kintai\Contract\Extension\Action;
use kintai\Contract\Extension\NavItem;
use kintai\Bundles\Installed\ResignationReport\ResignationReportBundle;
use PHPUnit\Framework\TestCase;

/**
 * Ce que le bundle déclare au Core. Le Core n'a plus de repli pour resignation-report (étape 4 du plan du contrat,
 * extraction) : ces déclarations sont la seule source de son entrée de menu, de sa catégorie de permissions et de ses
 * liens « Licencier », d'où des valeurs vérifiées une à une.
 */
final class ContractDeclarationsTest extends TestCase
{
    private function bundle(): ResignationReportBundle
    {
        return (new \ReflectionClass(ResignationReportBundle::class))->newInstanceWithoutConstructor();
    }

    public function testNavigation(): void
    {
        $items = $this->bundle()->navigation();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(NavItem::class, $items[0]);
        $this->assertSame('resignation_report', $items[0]->key);
        $this->assertSame(NavItem::REPORTS, $items[0]->section);
        $this->assertSame('resignation_report', $items[0]->label);
        $this->assertSame('admin.reports.resignation', $items[0]->managerRoute);
        $this->assertSame('admin.reports.resignation', $items[0]->employeeRoute);
        $this->assertSame('exit', $items[0]->icon);
        $this->assertSame(['*/reports/resignation'], $items[0]->activePaths);
        $this->assertNull($items[0]->storeFeature);
        $this->assertSame(40, $items[0]->order);
    }

    public function testPermissions(): void
    {
        $this->assertSame(['resignation_reports' => ['view', 'create', 'update', 'delete']], $this->bundle()->permissions());
    }

    /** Lien « Licencier » sur la liste des employés et sur la fiche employé d'un magasin. */
    public function testActions(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $actions = $this->bundle()->actions();

        $this->assertCount(2, $actions);
        [$row, $store] = $actions;
        foreach ([$row, $store] as $action) {
            $this->assertInstanceOf(Action::class, $action);
            $this->assertSame('resign', $action->key);
            $this->assertSame('resignation_report_resign', $action->label);
            $this->assertSame(Action::DANGER, $action->style);
            $this->assertSame('resignation_reports.create', $action->permission);
            $this->assertSame('/admin/stores/5/reports/resignation/create?user_id=3', ($action->url)(['user_id' => 3, 'store_id' => 5]));
        }
        $this->assertSame(Action::EMPLOYEE_ROW, $row->slot);
        $this->assertSame('✕', $row->icon);
        $this->assertSame(20, $row->order);
        $this->assertSame(Action::EMPLOYEE_STORE, $store->slot);
        $this->assertSame('', $store->icon);
        $this->assertSame(10, $store->order);
    }

    public function testCronJobs(): void
    {
        $this->assertSame([\kintai\Bundles\Installed\ResignationReport\Cron\ResignationDeactivateJob::class], $this->bundle()->cronJobs());
    }

    /** Le bundle n'apporte rien d'autre au Core. */
    public function testNoOtherContribution(): void
    {
        $bundle = $this->bundle();

        $this->assertSame([], $bundle->storeFeatures());
        $this->assertSame([], $bundle->dashboardWidgets());
        $this->assertSame([], $bundle->viewVariables());
        $this->assertSame([], $bundle->profileSections());
        $this->assertSame([], $bundle->notificationTypes());
        $this->assertSame([], $bundle->pendingRequestsProviders());
        $this->assertSame([], $bundle->absenceProviders());
        $this->assertSame([], $bundle->attendanceProviders());
    }

    /** Libellés des déclarations, fournis par le bundle. */
    public function testDeclaredLabelsAreTranslatedByTheBundle(): void
    {
        $keys = ['resignation_report', 'perm_category_resignation_reports', 'resignation_report_resign', 'resignation_report_error_not_found', 'resignation_report_store_picker_choose',
            'cron_job_resignation_deactivate', 'resignation_report_error_resignation_self', 'resignation_report_error_resignation_manager', 'resignation_report_resignation_deactivation_hint'];
        foreach (['fr', 'en', 'ja'] as $locale) {
            $lang = json_decode((string) file_get_contents(dirname(__DIR__) . "/lang/{$locale}.json"), true);
            foreach ($keys as $key) {
                $this->assertNotEmpty($lang[$key] ?? null, "{$locale} : {$key}");
            }
        }
    }
}
