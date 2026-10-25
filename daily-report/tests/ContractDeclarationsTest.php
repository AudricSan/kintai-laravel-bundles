<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\DailyReport;

use kintai\Bundles\Installed\DailyReport\Cron\AutoValidateJob;
use kintai\Bundles\Installed\DailyReport\DailyReportBundle;
use kintai\Bundles\Installed\DailyReport\Providers\DailyReportSales;
use kintai\Bundles\Installed\DailyReport\Repositories\DailyReportRepositoryInterface;
use kintai\Contract\Data\StoreRepositoryInterface;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\StoreFeature;
use kintai\Core\Container;
use PHPUnit\Framework\TestCase;

/**
 * Ce que le bundle déclare au Core. Le Core n'a plus de repli pour daily-report (étape 4 du plan du contrat,
 * extraction) : ces déclarations sont la seule source de son entrée de menu, de sa catégorie de permissions, de sa
 * fonctionnalité par magasin, des magasins de l'employé dans la navigation, de la tâche « auto-validate », de ses
 * notifications et des ventes journalières, d'où des valeurs vérifiées une à une.
 */
final class ContractDeclarationsTest extends TestCase
{
    private function bundle(): DailyReportBundle
    {
        return (new \ReflectionClass(DailyReportBundle::class))->newInstanceWithoutConstructor();
    }

    public function testNavigation(): void
    {
        $items = $this->bundle()->navigation();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(NavItem::class, $items[0]);
        $this->assertSame('daily_reports', $items[0]->key);
        $this->assertSame(NavItem::REPORTS, $items[0]->section);
        $this->assertSame('daily_report_daily_reports', $items[0]->label);
        $this->assertSame('admin.daily_reports.all', $items[0]->managerRoute);
        $this->assertSame('admin.daily_reports.all', $items[0]->employeeRoute);
        $this->assertSame('chart', $items[0]->icon);
        $this->assertSame('daily_reports', $items[0]->storeFeature);
        $this->assertSame(30, $items[0]->order);
        $this->assertTrue($items[0]->mobile);
    }

    /** Liens : tous les rapports avec la permission globale, sinon un lien par magasin où l'employé rédige. */
    public function testNavigationLinks(): void
    {
        $links = $this->bundle()->navigation()[0]->links;
        $view = static fn(bool $global, array $stores): array => [
            'path'                      => '/admin/stores/2/daily-reports',
            'route_visible'             => static fn(string $name): bool => $global,
            'route_url'                 => static fn(string $name): string => ['admin.daily_reports.all' => '/admin/daily-reports', 'admin.stores' => '/admin/stores'][$name],
            'daily_report_staff_stores' => $stores,
        ];

        $this->assertSame([['href' => '/admin/daily-reports', 'label' => __('daily_report_daily_reports'), 'active' => true]], $links($view(true, []), true));
        $this->assertSame([
            ['href' => '/admin/stores/1/daily-reports', 'label' => __('daily_report_daily_reports') . ' — Shibuya', 'active' => false],
            ['href' => '/admin/stores/2/daily-reports', 'label' => __('daily_report_daily_reports') . ' — Ueno', 'active' => true],
        ], $links($view(false, [['id' => 1, 'name' => 'Shibuya'], ['id' => 2, 'name' => 'Ueno']]), false));
        $this->assertSame([['href' => '/admin/stores/1/daily-reports', 'label' => __('daily_report_daily_reports'), 'active' => false]], $links($view(false, [['id' => 1, 'name' => 'Shibuya']]), false));
        $this->assertSame([], $links($view(false, []), false));
    }

    public function testPermissions(): void
    {
        $this->assertSame(['daily_reports' => ['view', 'create', 'update', 'submit', 'approve', 'delete']], $this->bundle()->permissions());
    }

    public function testStoreFeature(): void
    {
        $features = $this->bundle()->storeFeatures();

        $this->assertCount(1, $features);
        $this->assertInstanceOf(StoreFeature::class, $features[0]);
        $this->assertSame('daily_reports', $features[0]->key);
        $this->assertSame('daily_report_feature_daily_reports', $features[0]->label);
        $this->assertSame('daily_report_feature_daily_reports_desc', $features[0]->description);
        $this->assertSame('📊', $features[0]->icon);
        $this->assertSame(70, $features[0]->order);
    }

    /** Magasins où l'employé rédige les rapports, pour les liens de la navigation. */
    public function testViewVariables(): void
    {
        $this->assertSame(['daily_report_staff_stores'], array_keys($this->bundle()->viewVariables()));
    }

    public function testCronJobs(): void
    {
        $this->assertSame([AutoValidateJob::class], $this->bundle()->cronJobs());
    }

    public function testNotificationTypes(): void
    {
        $declared = $this->bundle()->notificationTypes();
        $this->assertSame(['daily_report_submitted', 'daily_report_validated'], array_keys($declared));

        // Chaque déclaration est valide pour le Core (format enrichi du contrat 1.23).
        foreach ($declared as $type => $declaration) {
            $parsed = \kintai\Contract\Extension\NotificationType::fromDeclaration($type, $declaration);
            $this->assertSame('reports', $parsed->category);
            $this->assertSame('daily_report', $parsed->referenceType);
            $this->assertTrue($parsed->push);
            $this->assertFalse($parsed->email);
        }
    }

    public function testSalesProvider(): void
    {
        $container = Container::getInstance();
        $container->instance(DailyReportRepositoryInterface::class, $this->createStub(DailyReportRepositoryInterface::class));
        $container->instance(StoreRepositoryInterface::class, $this->createStub(StoreRepositoryInterface::class));

        $providers = $this->bundle()->salesProviders();

        $this->assertCount(1, $providers);
        $this->assertInstanceOf(DailyReportSales::class, $providers[0]);
    }

    /** Le bundle n'apporte rien d'autre au Core. */
    public function testNoOtherContribution(): void
    {
        $bundle = $this->bundle();

        $this->assertSame([], $bundle->dashboardWidgets());
        $this->assertSame([], $bundle->actions());
        $this->assertSame([], $bundle->profileSections());
        $this->assertSame([], $bundle->eventListeners());
        $this->assertSame([], $bundle->pendingRequestsProviders());
        $this->assertSame([], $bundle->absenceProviders());
        $this->assertSame([], $bundle->attendanceProviders());
    }

    /** Libellés des déclarations, des notifications, du journal d'activité et des erreurs, fournis par le bundle. */
    public function testDeclaredLabelsAreTranslatedByTheBundle(): void
    {
        $keys = ['daily_report_daily_reports', 'perm_category_daily_reports', 'daily_report_feature_daily_reports', 'daily_report_feature_daily_reports_desc',
            'notif_daily_report_submitted', 'notif_daily_report_submitted_body', 'notif_daily_report_validated',
            'notif_daily_report_validated_body', 'audit_daily_report_created', 'audit_daily_report_updated',
            'audit_daily_report_submitted', 'audit_daily_report_validated', 'audit_daily_report_mail_sent',
            'audit_daily_report_deleted', 'audit_daily_report_settings_updated', 'audit_export_daily_report_pdf',
            'audit_cron_auto_validated', 'daily_report_error_access_denied', 'daily_report_error_not_authorized_create',
            'daily_report_error_not_authorized_delete', 'daily_report_error_not_authorized_send',
            'daily_report_error_not_authorized_submit', 'daily_report_error_not_authorized_validate',
            'daily_report_error_permission_insufficient'];
        foreach (['fr', 'en', 'ja'] as $locale) {
            $lang = json_decode((string) file_get_contents(dirname(__DIR__) . "/lang/{$locale}.json"), true);
            foreach ($keys as $key) {
                $this->assertNotEmpty($lang[$key] ?? null, "{$locale} : {$key}");
            }
        }
    }
}
