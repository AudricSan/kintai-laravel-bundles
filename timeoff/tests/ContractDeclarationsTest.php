<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\TimeOff;

use kintai\Bundles\Installed\TimeOff\Providers\PendingTimeoffProvider;
use kintai\Bundles\Installed\TimeOff\Providers\TimeoffAbsenceProvider;
use kintai\Bundles\Installed\TimeOff\TimeOffBundle;
use kintai\Contract\Extension\DashboardStat;
use kintai\Contract\Extension\DashboardWidget;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\StoreFeature;
use PHPUnit\Framework\TestCase;

/**
 * Ce que le bundle déclare au Core. Le Core n'a plus de repli pour timeoff (étape 4 du plan du contrat, extraction) :
 * ces déclarations sont la seule source de son entrée de menu, de sa catégorie de permissions, de sa fonctionnalité
 * par magasin, des demandes en attente, des absences lues par le Core, des widgets et de la carte des tableaux de bord
 * et de ses notifications, d'où des valeurs vérifiées une à une.
 */
final class ContractDeclarationsTest extends TestCase
{
    private function bundle(): TimeOffBundle
    {
        return (new \ReflectionClass(TimeOffBundle::class))->newInstanceWithoutConstructor();
    }

    public function testNavigation(): void
    {
        $items = $this->bundle()->navigation();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(NavItem::class, $items[0]);
        $this->assertSame(['timeoff', NavItem::REQUESTS, 'timeoff', 'admin.timeoff', 'employee.timeoff', 'leaf', 'timeoff_my_timeoff'],
            [$items[0]->key, $items[0]->section, $items[0]->label, $items[0]->managerRoute, $items[0]->employeeRoute, $items[0]->icon, $items[0]->employeeLabel]);
        $this->assertSame(['/admin/timeoff', '/employee/timeoff'], $items[0]->activePaths);
        $this->assertSame('timeoff', $items[0]->storeFeature);
        $this->assertSame(10, $items[0]->order);
        $this->assertTrue($items[0]->mobile);
    }

    public function testPermissions(): void
    {
        $this->assertSame(['timeoff' => ['view', 'create', 'update', 'approve', 'delete']], $this->bundle()->permissions());
    }

    public function testStoreFeature(): void
    {
        $features = $this->bundle()->storeFeatures();

        $this->assertCount(1, $features);
        $this->assertInstanceOf(StoreFeature::class, $features[0]);
        $this->assertSame(['timeoff', 'timeoff_feature_timeoff', 'timeoff_feature_desc', '🏖', 30],
            [$features[0]->key, $features[0]->label, $features[0]->description, $features[0]->icon, $features[0]->order]);
    }

    public function testProviders(): void
    {
        $pending = $this->bundle()->pendingRequestsProviders();
        $this->assertCount(1, $pending);
        $this->assertInstanceOf(PendingTimeoffProvider::class, $pending[0]);
        $this->assertSame(['timeoff', 'timeoff_pending_timeoff', 'timeoff_no_pending_request', 'timeoff.view', 'timeoff', 10],
            [$pending[0]->key(), $pending[0]->label(), $pending[0]->emptyLabel(), $pending[0]->permission(), $pending[0]->storeFeature(), $pending[0]->order()]);

        $absences = $this->bundle()->absenceProviders();
        $this->assertCount(1, $absences);
        $this->assertInstanceOf(TimeoffAbsenceProvider::class, $absences[0]);
    }

    /** Mêmes clés que les widgets que le Core affichait : les choix des utilisateurs sont conservés. */
    public function testDashboardWidgets(): void
    {
        $widgets = $this->bundle()->dashboardWidgets();

        $this->assertCount(2, $widgets);
        [$manager, $employee] = $widgets;
        $this->assertInstanceOf(DashboardWidget::class, $manager);
        $this->assertSame(['pending_timeoff', DashboardWidget::MANAGER, 'timeoff_widget_pending_timeoff', 'timeoff::widgets.dashboard-manager', 'timeoff.view', 'timeoff'],
            [$manager->key, $manager->audience, $manager->label, $manager->view, $manager->permission, $manager->storeFeature]);
        $this->assertSame(['pending_timeoff', DashboardWidget::EMPLOYEE, 'timeoff_my_pending_timeoff', 'timeoff::widgets.dashboard-employee', null, 'timeoff'],
            [$employee->key, $employee->audience, $employee->label, $employee->view, $employee->permission, $employee->storeFeature]);
        $this->assertFileExists(dirname(__DIR__) . '/Views/widgets/dashboard-manager.php');
        $this->assertFileExists(dirname(__DIR__) . '/Views/widgets/dashboard-employee.php');
    }

    public function testDashboardStat(): void
    {
        $stats = $this->bundle()->dashboardStats();

        $this->assertCount(1, $stats);
        $this->assertInstanceOf(DashboardStat::class, $stats[0]);
        $this->assertSame(['pending_timeoff', 'timeoff_pending_timeoff', '🌴', 'warning', 'timeoff', 10],
            [$stats[0]->key, $stats[0]->label, $stats[0]->icon, $stats[0]->tone, $stats[0]->storeFeature, $stats[0]->order]);
    }

    public function testNotificationTypes(): void
    {
        $declared = $this->bundle()->notificationTypes();
        $this->assertSame(['timeoff_approved', 'timeoff_refused', 'timeoff_submitted'], array_keys($declared));

        // Chaque déclaration est valide pour le Core (format enrichi du contrat 1.23).
        $types = [];
        foreach ($declared as $type => $declaration) {
            $types[$type] = \kintai\Contract\Extension\NotificationType::fromDeclaration($type, $declaration);
            $this->assertSame('requests', $types[$type]->category);
            $this->assertSame('timeoff', $types[$type]->referenceType);
            $this->assertTrue($types[$type]->push);
        }
        $this->assertTrue($types['timeoff_approved']->email, 'les décisions arrivent aussi par e-mail');
        $this->assertTrue($types['timeoff_refused']->email);
        $this->assertFalse($types['timeoff_submitted']->email);
    }

    /** Le bundle n'apporte rien d'autre au Core. */
    public function testNoOtherContribution(): void
    {
        $bundle = $this->bundle();

        $this->assertSame([], $bundle->actions());
        $this->assertSame([], $bundle->viewVariables());
        $this->assertSame([], $bundle->profileSections());
        $this->assertSame([], $bundle->cronJobs());
        $this->assertSame([], $bundle->eventListeners());
        $this->assertSame([], $bundle->attendanceProviders());
        $this->assertSame([], $bundle->salesProviders());
        $this->assertSame([], $bundle->swapProviders());
        $this->assertSame([], $bundle->shiftFormSections());
    }

    /** Libellés des déclarations, des notifications, du journal d'activité et des widgets, fournis par le bundle. */
    public function testDeclaredLabelsAreTranslatedByTheBundle(): void
    {
        $keys = ['timeoff', 'timeoff_my_timeoff', 'perm_category_timeoff', 'timeoff_feature_timeoff', 'timeoff_feature_desc', 'timeoff_pending_timeoff',
            'timeoff_no_pending_request', 'timeoff_widget_pending_timeoff', 'timeoff_my_pending_timeoff', 'notif_timeoff_approved', 'notif_timeoff_refused',
            'notif_timeoff_submitted', 'audit_timeoff_created', 'audit_timeoff_cancelled', 'audit_timeoff_approved', 'audit_timeoff_refused'];
        foreach (['fr', 'en', 'ja'] as $locale) {
            $lang = json_decode((string) file_get_contents(dirname(__DIR__) . "/lang/{$locale}.json"), true);
            foreach ($keys as $key) {
                $this->assertNotEmpty($lang[$key] ?? null, "{$locale} : {$key}");
            }
        }
    }
}
