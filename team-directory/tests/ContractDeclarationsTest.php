<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\TeamDirectory;

use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\ProfileSection;
use kintai\Bundles\Installed\TeamDirectory\TeamDirectoryBundle;
use PHPUnit\Framework\TestCase;

/**
 * Ce que le bundle déclare au Core (contrat 1.12). Le Core n'a plus de repli pour team-directory (étape 4 du plan du
 * contrat, extraction) : ces déclarations sont la seule source de son entrée de menu et de sa section de profil, d'où
 * des valeurs vérifiées une à une plutôt qu'une comparaison avec l'ancien repli du Core.
 */
final class ContractDeclarationsTest extends TestCase
{
    private function bundle(): TeamDirectoryBundle
    {
        return (new \ReflectionClass(TeamDirectoryBundle::class))->newInstanceWithoutConstructor();
    }

    public function testNavigationDeclaresTheDirectoryEntry(): void
    {
        $items = $this->bundle()->navigation();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(NavItem::class, $items[0]);
        $this->assertSame('team_directory', $items[0]->key);
        $this->assertSame(NavItem::MAIN, $items[0]->section);
        $this->assertSame('team_directory', $items[0]->label);
        $this->assertSame('team.index', $items[0]->managerRoute);
        $this->assertSame('team.index', $items[0]->employeeRoute);
        $this->assertSame('users', $items[0]->icon);
        $this->assertSame(['/team'], $items[0]->activePaths);
        $this->assertNull($items[0]->storeFeature, 'annuaire ouvert dans tous les magasins');
        $this->assertSame(10, $items[0]->order);
        $this->assertFalse($items[0]->mobile);
    }

    public function testProfileSectionDeclaresTheDirectoryCheckboxes(): void
    {
        $sections = $this->bundle()->profileSections();

        $this->assertCount(1, $sections);
        $this->assertInstanceOf(ProfileSection::class, $sections[0]);
        $this->assertSame('directory', $sections[0]->key);
        $this->assertSame('team-directory::profile-section', $sections[0]->view);
        $this->assertSame(['show_in_directory', 'share_email', 'share_phone', 'share_mobile_phone'], $sections[0]->flags);
        $this->assertSame(10, $sections[0]->order);
        $this->assertFileExists(dirname(__DIR__) . '/Views/profile-section.php');
    }

    /** Le bundle n'apporte rien d'autre au Core. */
    public function testNoOtherContribution(): void
    {
        $bundle = $this->bundle();

        $this->assertSame([], $bundle->permissions());
        $this->assertSame([], $bundle->storeFeatures());
        $this->assertSame([], $bundle->dashboardWidgets());
        $this->assertSame([], $bundle->viewVariables());
        $this->assertSame([], $bundle->actions());
        $this->assertSame([], $bundle->notificationTypes());
        $this->assertSame([], $bundle->cronJobs());
        $this->assertSame([], $bundle->pendingRequestsProviders());
        $this->assertSame([], $bundle->absenceProviders());
        $this->assertSame([], $bundle->attendanceProviders());
    }

    /** Les libellés utilisés par l'entrée de menu et la section de profil sont traduits par le bundle. */
    public function testDeclaredLabelsAreTranslatedByTheBundle(): void
    {
        $keys = ['team_directory', 'team_directory_show_in_directory', 'team_directory_show_in_directory_hint', 'team_directory_share_contact_info', 'team_directory_share_contact_info_hint'];
        foreach (['fr', 'en', 'ja'] as $locale) {
            $lang = json_decode((string) file_get_contents(dirname(__DIR__) . "/lang/{$locale}.json"), true);
            foreach ($keys as $key) {
                $this->assertNotEmpty($lang[$key] ?? null, "{$locale} : {$key}");
            }
        }
    }
}
