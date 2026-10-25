<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\SalaryReport;

use kintai\Contract\Extension\Action;
use kintai\Contract\Extension\NavItem;
use kintai\Bundles\Installed\SalaryReport\SalaryReportBundle;
use PHPUnit\Framework\TestCase;

/**
 * Ce que le bundle déclare au Core. Le Core n'a plus de repli pour salary-report (étape 4 du plan du contrat,
 * extraction) : ces déclarations sont la seule source de son entrée de menu et de ses liens « Rapport de salaire »,
 * d'où des valeurs vérifiées une à une.
 */
final class ContractDeclarationsTest extends TestCase
{
    private function bundle(): SalaryReportBundle
    {
        return (new \ReflectionClass(SalaryReportBundle::class))->newInstanceWithoutConstructor();
    }

    public function testNavigation(): void
    {
        $items = $this->bundle()->navigation();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(NavItem::class, $items[0]);
        $this->assertSame('salary_report', $items[0]->key);
        $this->assertSame(NavItem::REPORTS, $items[0]->section);
        $this->assertSame('salary_report', $items[0]->label);
        $this->assertSame('admin.reports.salary', $items[0]->managerRoute);
        $this->assertSame('admin.reports.salary', $items[0]->employeeRoute);
        $this->assertSame('money', $items[0]->icon);
        $this->assertSame(['*/reports/salary'], $items[0]->activePaths);
        $this->assertNull($items[0]->storeFeature);
        $this->assertSame(50, $items[0]->order);
    }

    /** Liens « Rapport de salaire » : liste des employés, fiche employé, rapport employés du magasin (en-tête et lignes). */
    public function testActions(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $actions = $this->bundle()->actions();

        $this->assertCount(4, $actions);
        $expected = [
            [Action::EMPLOYEE_ROW, '💰', Action::DEFAULT, 'payroll.generate', 10, '/admin/stores/5/reports/salary/create?user_id=3'],
            [Action::EMPLOYEE_STORE, '', Action::DEFAULT, 'payroll.generate', 20, '/admin/stores/5/reports/salary/create?user_id=3'],
            [Action::STORE_REPORT, '💰', Action::PRIMARY, null, 10, '/admin/stores/5/reports/salary/create'],
            [Action::STORE_REPORT_ROW, '💰', Action::DEFAULT, null, 10, '/admin/stores/5/reports/salary/create?user_id=3'],
        ];
        foreach ($actions as $i => $action) {
            $this->assertInstanceOf(Action::class, $action);
            $this->assertSame('salary_report', $action->key);
            $this->assertSame('salary_report', $action->label);
            [$slot, $icon, $style, $permission, $order, $url] = $expected[$i];
            $this->assertSame($slot, $action->slot, "slot #{$i}");
            $this->assertSame($icon, $action->icon, "icon #{$i}");
            $this->assertSame($style, $action->style, "style #{$i}");
            $this->assertSame($permission, $action->permission, "permission #{$i}");
            $this->assertSame($order, $action->order, "order #{$i}");
            $this->assertSame($url, ($action->url)(['user_id' => 3, 'store_id' => 5, 'store_name' => 'S', 'period' => 30]), "url #{$i}");
        }
    }

    /** Le bundle n'apporte rien d'autre au Core (la catégorie de permissions « payroll » est celle du Core). */
    public function testNoOtherContribution(): void
    {
        $bundle = $this->bundle();

        $this->assertSame([], $bundle->permissions());
        $this->assertSame([], $bundle->storeFeatures());
        $this->assertSame([], $bundle->dashboardWidgets());
        $this->assertSame([], $bundle->viewVariables());
        $this->assertSame([], $bundle->profileSections());
        $this->assertSame([], $bundle->notificationTypes());
        $this->assertSame([], $bundle->cronJobs());
        $this->assertSame([], $bundle->eventListeners());
        $this->assertSame([], $bundle->pendingRequestsProviders());
        $this->assertSame([], $bundle->absenceProviders());
        $this->assertSame([], $bundle->attendanceProviders());
        $this->assertSame([], $bundle->salesProviders());
    }

    /** Libellés des déclarations et des pages, fournis par le bundle. */
    public function testDeclaredLabelsAreTranslatedByTheBundle(): void
    {
        $keys = ['salary_report', 'salary_report_error_not_found', 'salary_report_all_months', 'salary_report_all_years', 'salary_report_gross_pay', 'salary_report_net_pay', 'salary_report_total_deductions', 'salary_report_payslip_summary', 'salary_report_payslip_no_shift', 'salary_report_col_day', 'salary_report_col_rate_h'];
        foreach (['fr', 'en', 'ja'] as $locale) {
            $lang = json_decode((string) file_get_contents(dirname(__DIR__) . "/lang/{$locale}.json"), true);
            foreach ($keys as $key) {
                $this->assertNotEmpty($lang[$key] ?? null, "{$locale} : {$key}");
            }
        }
    }
}
