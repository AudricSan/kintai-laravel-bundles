<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\StorePhoto;

use kintai\Bundles\Installed\StorePhoto\Cron\PhotoRetentionJob;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\StoreFeature;
use kintai\Bundles\Installed\StorePhoto\StorePhotoBundle;
use PHPUnit\Framework\TestCase;

/**
 * Ce que le bundle déclare au Core. Le Core n'a plus de repli pour store-photos (étape 4 du plan du contrat,
 * extraction) : ces déclarations sont la seule source de son entrée de menu, de sa catégorie de permissions, de sa
 * fonctionnalité par magasin, de sa tâche planifiée et de son type de notification, d'où des valeurs vérifiées une à
 * une.
 */
final class ContractDeclarationsTest extends TestCase
{
    private function bundle(): StorePhotoBundle
    {
        return (new \ReflectionClass(StorePhotoBundle::class))->newInstanceWithoutConstructor();
    }

    public function testNavigation(): void
    {
        $items = $this->bundle()->navigation();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(NavItem::class, $items[0]);
        $this->assertSame('photos', $items[0]->key);
        $this->assertSame(NavItem::REPORTS, $items[0]->section);
        $this->assertSame('store_photos_photos', $items[0]->label);
        $this->assertSame('admin.photos.index', $items[0]->managerRoute);
        $this->assertSame('admin.photos.index', $items[0]->employeeRoute);
        $this->assertSame('camera', $items[0]->icon);
        $this->assertSame(['/admin/photos'], $items[0]->activePaths);
        $this->assertSame('photos', $items[0]->storeFeature);
        $this->assertSame(60, $items[0]->order);
        $this->assertFalse($items[0]->mobile);
    }

    public function testPermissions(): void
    {
        $this->assertSame(['photos' => ['view', 'create', 'update', 'delete']], $this->bundle()->permissions());
    }

    public function testStoreFeature(): void
    {
        $features = $this->bundle()->storeFeatures();

        $this->assertCount(1, $features);
        $this->assertInstanceOf(StoreFeature::class, $features[0]);
        $this->assertSame('photos', $features[0]->key);
        $this->assertSame('store_photos_feature_photos', $features[0]->label);
        $this->assertSame('store_photos_feature_photos_desc', $features[0]->description);
        $this->assertSame('📷', $features[0]->icon);
        $this->assertSame(80, $features[0]->order);
    }

    public function testCronJobAndNotificationType(): void
    {
        $this->assertSame([PhotoRetentionJob::class], $this->bundle()->cronJobs());
        $declared = $this->bundle()->notificationTypes();
        $this->assertSame(['photo_archived'], array_keys($declared));

        // Déclaration valide pour le Core (format enrichi du contrat 1.23).
        $type = \kintai\Contract\Extension\NotificationType::fromDeclaration('photo_archived', $declared['photo_archived']);
        $this->assertSame('reports', $type->category);
        $this->assertSame('photo_submission', $type->referenceType);
        $this->assertTrue($type->push);
        $this->assertFalse($type->email);
    }

    /** Le bundle n'apporte rien d'autre au Core. */
    public function testNoOtherContribution(): void
    {
        $bundle = $this->bundle();

        $this->assertSame([], $bundle->dashboardWidgets());
        $this->assertSame([], $bundle->viewVariables());
        $this->assertSame([], $bundle->actions());
        $this->assertSame([], $bundle->profileSections());
        $this->assertSame([], $bundle->pendingRequestsProviders());
        $this->assertSame([], $bundle->absenceProviders());
        $this->assertSame([], $bundle->attendanceProviders());
    }

    /** Les libellés des déclarations sont traduits par le bundle (le Core ne les fournit plus). */
    public function testDeclaredLabelsAreTranslatedByTheBundle(): void
    {
        $keys = ['store_photos_photos', 'perm_category_photos', 'store_photos_feature_photos', 'store_photos_feature_photos_desc', 'notif_photo_archived',
            'notif_photo_archived_body', 'store_photos_error_owner_only_delete_photos', 'store_photos_error_photos_feature_disabled'];
        foreach (['fr', 'en', 'ja'] as $locale) {
            $lang = json_decode((string) file_get_contents(dirname(__DIR__) . "/lang/{$locale}.json"), true);
            foreach ($keys as $key) {
                $this->assertNotEmpty($lang[$key] ?? null, "{$locale} : {$key}");
            }
        }
    }
}
