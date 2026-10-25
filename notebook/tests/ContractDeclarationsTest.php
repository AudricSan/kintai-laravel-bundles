<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Notebook;

use kintai\Contract\Extension\DashboardWidget;
use kintai\Contract\Extension\StoreFeature;
use kintai\Bundles\Installed\Notebook\NotebookBundle;
use PHPUnit\Framework\TestCase;

/**
 * Ce que le bundle déclare au Core. Le Core n'a plus de repli pour notebook (étape 4 du plan du contrat, extraction) :
 * ces déclarations sont la seule source de sa catégorie de permissions, de sa fonctionnalité par magasin, de son
 * widget de tableau de bord et de son type de notification, d'où des valeurs vérifiées une à une.
 */
final class ContractDeclarationsTest extends TestCase
{
    private function bundle(): NotebookBundle
    {
        return (new \ReflectionClass(NotebookBundle::class))->newInstanceWithoutConstructor();
    }

    public function testPermissions(): void
    {
        $this->assertSame(['notebook' => ['view', 'create', 'manage']], $this->bundle()->permissions());
    }

    public function testStoreFeature(): void
    {
        $features = $this->bundle()->storeFeatures();

        $this->assertCount(1, $features);
        $this->assertInstanceOf(StoreFeature::class, $features[0]);
        $this->assertSame('notes', $features[0]->key);
        $this->assertSame('notebook_feature_notes', $features[0]->label);
        $this->assertSame('notebook_feature_notes_desc', $features[0]->description);
        $this->assertSame('📝', $features[0]->icon);
        $this->assertSame(90, $features[0]->order);
    }

    /** Même clé que l'ancien widget du Core (préférences conservées), un par tableau de bord. */
    public function testDashboardWidgets(): void
    {
        $widgets = $this->bundle()->dashboardWidgets();

        $this->assertCount(2, $widgets);
        $byAudience = [];
        foreach ($widgets as $widget) {
            $this->assertInstanceOf(DashboardWidget::class, $widget);
            $this->assertSame('team_notes', $widget->key);
            $this->assertSame('notebook.view', $widget->permission);
            $this->assertSame('notes', $widget->storeFeature);
            $this->assertNotNull($widget->data);
            $byAudience[$widget->audience] = $widget;
        }
        $this->assertSame('notebook_widget_team_notes', $byAudience[DashboardWidget::MANAGER]->label);
        $this->assertSame('notebook::widgets.dashboard-manager', $byAudience[DashboardWidget::MANAGER]->view);
        $this->assertSame('notebook_my_team_notes', $byAudience[DashboardWidget::EMPLOYEE]->label);
        $this->assertSame('notebook::widgets.dashboard-employee', $byAudience[DashboardWidget::EMPLOYEE]->view);
        $this->assertFileExists(dirname(__DIR__) . '/Views/widgets/dashboard-manager.php');
        $this->assertFileExists(dirname(__DIR__) . '/Views/widgets/dashboard-employee.php');
    }

    public function testNotificationTypes(): void
    {
        $declared = $this->bundle()->notificationTypes();
        $this->assertSame(['notebook_entry_created'], array_keys($declared));

        // Déclaration valide pour le Core (format enrichi du contrat 1.23).
        $type = \kintai\Contract\Extension\NotificationType::fromDeclaration('notebook_entry_created', $declared['notebook_entry_created']);
        $this->assertSame('messages', $type->category);
        $this->assertSame('notebook_entry', $type->referenceType);
        $this->assertTrue($type->push);
        $this->assertFalse($type->email);
    }

    /** Le bundle n'apporte rien d'autre au Core. */
    public function testNoOtherContribution(): void
    {
        $bundle = $this->bundle();

        $this->assertSame([], $bundle->navigation());
        $this->assertSame([], $bundle->viewVariables());
        $this->assertSame([], $bundle->actions());
        $this->assertSame([], $bundle->profileSections());
        $this->assertSame([], $bundle->cronJobs());
        $this->assertSame([], $bundle->pendingRequestsProviders());
        $this->assertSame([], $bundle->absenceProviders());
        $this->assertSame([], $bundle->attendanceProviders());
    }

    /** Les libellés des déclarations et des widgets sont traduits par le bundle (le Core ne les fournit plus). */
    public function testDeclaredLabelsAreTranslatedByTheBundle(): void
    {
        $keys = ['notebook_widget_team_notes', 'notebook_my_team_notes', 'notebook_no_entries', 'notebook_pinned', 'notebook_anonymous_author',
            'notebook_org_wide', 'notif_notebook_entry_created'];
        foreach (['fr', 'en', 'ja'] as $locale) {
            $lang = json_decode((string) file_get_contents(dirname(__DIR__) . "/lang/{$locale}.json"), true);
            foreach ($keys as $key) {
                $this->assertNotEmpty($lang[$key] ?? null, "{$locale} : {$key}");
            }
        }
    }

    /**
     * Un gérant peut afficher les deux widgets côte à côte (le sien et celui de son espace employé) : leurs titres
     * doivent se distinguer, sinon rien n'indique que l'un porte sur les magasins gérés et l'autre sur ses magasins.
     */
    public function testManagerAndEmployeeWidgetTitlesDiffer(): void
    {
        foreach (['fr', 'en', 'ja'] as $locale) {
            $lang = json_decode((string) file_get_contents(dirname(__DIR__) . "/lang/{$locale}.json"), true);
            $this->assertNotSame($lang['notebook_widget_team_notes'], $lang['notebook_my_team_notes'], $locale);
        }
    }
}
