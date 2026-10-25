<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftSwap;

use kintai\Bundles\Installed\ShiftSwap\Providers\PendingSwapsProvider;
use kintai\Bundles\Installed\ShiftSwap\Providers\SwapRequestsProvider;
use kintai\Bundles\Installed\ShiftSwap\ShiftSwapBundle;
use kintai\Contract\Extension\Action;
use kintai\Contract\Extension\DashboardStat;
use kintai\Contract\Extension\DashboardWidget;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\StoreFeature;
use PHPUnit\Framework\TestCase;

/**
 * Ce que le bundle déclare au Core. Le Core n'a plus de repli pour shift-swap (étape 4 du plan du contrat,
 * extraction) : ces déclarations sont la seule source de son entrée de menu, de sa catégorie de permissions, de sa
 * fonctionnalité par magasin, du lien « Demander un échange », des demandes en attente, des échanges des statistiques,
 * des widgets et de la carte des tableaux de bord et de ses notifications, d'où des valeurs vérifiées une à une.
 */
final class ContractDeclarationsTest extends TestCase
{
    private function bundle(): ShiftSwapBundle
    {
        return (new \ReflectionClass(ShiftSwapBundle::class))->newInstanceWithoutConstructor();
    }

    public function testNavigation(): void
    {
        $items = $this->bundle()->navigation();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(NavItem::class, $items[0]);
        $this->assertSame('swaps', $items[0]->key);
        $this->assertSame(NavItem::REQUESTS, $items[0]->section);
        $this->assertSame('shift_swap_swaps', $items[0]->label);
        $this->assertSame('admin.swap_requests', $items[0]->managerRoute);
        $this->assertSame('employee.swaps', $items[0]->employeeRoute);
        $this->assertSame('arrows', $items[0]->icon);
        $this->assertSame(['/admin/swap-requests', '/employee/swaps'], $items[0]->activePaths);
        $this->assertSame('swaps', $items[0]->storeFeature);
        $this->assertSame(20, $items[0]->order);
        $this->assertTrue($items[0]->mobile);
    }

    public function testPermissions(): void
    {
        $this->assertSame(['swaps' => ['view', 'create', 'update', 'approve', 'delete']], $this->bundle()->permissions());
    }

    public function testStoreFeature(): void
    {
        $features = $this->bundle()->storeFeatures();

        $this->assertCount(1, $features);
        $this->assertInstanceOf(StoreFeature::class, $features[0]);
        $this->assertSame(['swaps', 'shift_swap_feature_swaps', 'shift_swap_feature_swaps_desc', '🔄', 40],
            [$features[0]->key, $features[0]->label, $features[0]->description, $features[0]->icon, $features[0]->order]);
    }

    public function testRequestSwapAction(): void
    {
        $actions = $this->bundle()->actions();

        $this->assertCount(1, $actions);
        $this->assertInstanceOf(Action::class, $actions[0]);
        $this->assertSame('request_swap', $actions[0]->key);
        $this->assertSame(Action::SHIFTS_EMPLOYEE, $actions[0]->slot);
        $this->assertSame('shift_swap_request_swap', $actions[0]->label);
        $this->assertSame('⇄', $actions[0]->icon);
        $this->assertSame(Action::PRIMARY, $actions[0]->style);
        $this->assertSame(10, $actions[0]->order);
    }

    public function testProviders(): void
    {
        $pending = $this->bundle()->pendingRequestsProviders();
        $this->assertCount(1, $pending);
        $this->assertInstanceOf(PendingSwapsProvider::class, $pending[0]);
        $this->assertSame(['swaps', 'shift_swap_pending_swaps', 'shift_swap_no_pending_swap', 'swaps.view', 'swaps', 20],
            [$pending[0]->key(), $pending[0]->label(), $pending[0]->emptyLabel(), $pending[0]->permission(), $pending[0]->storeFeature(), $pending[0]->order()]);

        $swaps = $this->bundle()->swapProviders();
        $this->assertCount(1, $swaps);
        $this->assertInstanceOf(SwapRequestsProvider::class, $swaps[0]);
    }

    /** Mêmes clés que les widgets que le Core affichait : les choix des utilisateurs sont conservés. */
    public function testDashboardWidgets(): void
    {
        $widgets = $this->bundle()->dashboardWidgets();

        $this->assertCount(2, $widgets);
        [$manager, $employee] = $widgets;
        $this->assertInstanceOf(DashboardWidget::class, $manager);
        $this->assertSame(['pending_swaps', DashboardWidget::MANAGER, 'shift_swap_widget_pending_swaps', 'shift-swap::widgets.dashboard-manager', 'swaps.view', 'swaps'],
            [$manager->key, $manager->audience, $manager->label, $manager->view, $manager->permission, $manager->storeFeature]);
        $this->assertSame(['pending_swaps', DashboardWidget::EMPLOYEE, 'shift_swap_pending_swaps', 'shift-swap::widgets.dashboard-employee', null, 'swaps'],
            [$employee->key, $employee->audience, $employee->label, $employee->view, $employee->permission, $employee->storeFeature]);
        $this->assertFileExists(dirname(__DIR__) . '/Views/widgets/dashboard-manager.php');
        $this->assertFileExists(dirname(__DIR__) . '/Views/widgets/dashboard-employee.php');
    }

    public function testDashboardStat(): void
    {
        $stats = $this->bundle()->dashboardStats();

        $this->assertCount(1, $stats);
        $this->assertInstanceOf(DashboardStat::class, $stats[0]);
        $this->assertSame(['pending_swaps', 'shift_swap_swaps', '🔄', 'danger', 'swaps', 20],
            [$stats[0]->key, $stats[0]->label, $stats[0]->icon, $stats[0]->tone, $stats[0]->storeFeature, $stats[0]->order]);
    }

    public function testNotificationTypes(): void
    {
        $declared = $this->bundle()->notificationTypes();
        $this->assertSame(
            ['swap_requested', 'swap_peer_accepted', 'swap_peer_refused', 'swap_accepted', 'swap_refused', 'swap_cancelled', 'swap_applied', 'swap_cancelled_restored'],
            array_keys($declared)
        );

        // Chaque déclaration est valide pour le Core (format enrichi du contrat 1.23).
        $types = [];
        foreach ($declared as $type => $declaration) {
            $types[$type] = \kintai\Contract\Extension\NotificationType::fromDeclaration($type, $declaration);
            $this->assertSame('swap', $types[$type]->referenceType, 'l\'identifiant notifié est celui de la demande d\'échange');
            $this->assertTrue($types[$type]->push);
        }
        // Les deux changements de planning ont leurs propres types, ils ne réutilisent plus shift_assigned du Core.
        $this->assertSame('planning', $types['swap_applied']->category);
        $this->assertSame('planning', $types['swap_cancelled_restored']->category);
        $this->assertSame('requests', $types['swap_requested']->category);
        foreach (['swap_accepted', 'swap_applied', 'swap_cancelled_restored'] as $byMail) {
            $this->assertTrue($types[$byMail]->email, $byMail);
        }
        foreach (['swap_requested', 'swap_peer_accepted', 'swap_peer_refused', 'swap_refused', 'swap_cancelled'] as $pushOnly) {
            $this->assertFalse($types[$pushOnly]->email, $pushOnly);
        }
    }

    /** Le bundle n'apporte rien d'autre au Core. */
    public function testNoOtherContribution(): void
    {
        $bundle = $this->bundle();

        $this->assertSame([], $bundle->viewVariables());
        $this->assertSame([], $bundle->profileSections());
        $this->assertSame([], $bundle->cronJobs());
        $this->assertSame([], $bundle->eventListeners());
        $this->assertSame([], $bundle->absenceProviders());
        $this->assertSame([], $bundle->attendanceProviders());
        $this->assertSame([], $bundle->salesProviders());
        $this->assertSame([], $bundle->shiftFormSections());
    }

    /** Libellés des déclarations, des notifications, du journal d'activité et des widgets, fournis par le bundle. */
    public function testDeclaredLabelsAreTranslatedByTheBundle(): void
    {
        $keys = ['shift_swap_swaps', 'perm_category_swaps', 'shift_swap_feature_swaps', 'shift_swap_feature_swaps_desc', 'shift_swap_request_swap', 'shift_swap_pending_swaps',
            'shift_swap_no_pending_swap', 'shift_swap_widget_pending_swaps', 'notif_swap_requested', 'notif_swap_peer_accepted', 'notif_swap_peer_refused',
            'notif_swap_accepted', 'notif_swap_refused', 'notif_swap_cancelled', 'audit_swap_created', 'audit_swap_peer_accepted',
            'audit_swap_peer_refused', 'audit_swap_cancelled', 'audit_swap_approved', 'audit_swap_refused'];
        foreach (['fr', 'en', 'ja'] as $locale) {
            $lang = json_decode((string) file_get_contents(dirname(__DIR__) . "/lang/{$locale}.json"), true);
            foreach ($keys as $key) {
                $this->assertNotEmpty($lang[$key] ?? null, "{$locale} : {$key}");
            }
        }
    }
}
