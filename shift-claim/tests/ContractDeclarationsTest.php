<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftClaim;

use kintai\Bundles\Installed\ShiftClaim\Providers\PendingClaimsProvider;
use kintai\Bundles\Installed\ShiftClaim\ShiftClaimBundle;
use kintai\Contract\Event\OpenShiftClosed;
use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\ShiftFormSection;
use kintai\Contract\Extension\StoreFeature;
use PHPUnit\Framework\TestCase;

/**
 * Ce que le bundle déclare au Core. Le Core n'a plus de repli pour shift-claim (étape 4 du plan du contrat,
 * extraction) : ces déclarations sont la seule source de son entrée de menu, de sa catégorie de permissions, de sa
 * fonctionnalité par magasin, des candidatures en attente, des champs « Shift ouvert » du formulaire de shift, de ses
 * notifications et du retrait des candidatures d'un shift fermé, d'où des valeurs vérifiées une à une.
 */
final class ContractDeclarationsTest extends TestCase
{
    private function bundle(): ShiftClaimBundle
    {
        return (new \ReflectionClass(ShiftClaimBundle::class))->newInstanceWithoutConstructor();
    }

    public function testNavigation(): void
    {
        $items = $this->bundle()->navigation();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(NavItem::class, $items[0]);
        $this->assertSame('open_shifts', $items[0]->key);
        $this->assertSame(NavItem::REQUESTS, $items[0]->section);
        $this->assertSame('shift_claim_open_shifts', $items[0]->label);
        $this->assertSame('admin.open_shifts', $items[0]->managerRoute);
        $this->assertSame('employee.open_shifts', $items[0]->employeeRoute);
        $this->assertSame('plus', $items[0]->icon);
        $this->assertSame(['/admin/open-shifts', '/employee/open-shifts'], $items[0]->activePaths);
        $this->assertSame('open_shifts', $items[0]->storeFeature);
        $this->assertSame(30, $items[0]->order);
        $this->assertTrue($items[0]->mobile);
    }

    public function testPermissions(): void
    {
        $this->assertSame(['open_shifts' => ['view', 'publish', 'approve']], $this->bundle()->permissions());
    }

    public function testStoreFeature(): void
    {
        $features = $this->bundle()->storeFeatures();

        $this->assertCount(1, $features);
        $this->assertInstanceOf(StoreFeature::class, $features[0]);
        $this->assertSame('open_shifts', $features[0]->key);
        $this->assertSame('shift_claim_feature_open_shifts', $features[0]->label);
        $this->assertSame('shift_claim_feature_open_shifts_desc', $features[0]->description);
        $this->assertSame('📋', $features[0]->icon);
        $this->assertSame(50, $features[0]->order);
    }

    public function testPendingRequestsProvider(): void
    {
        $providers = $this->bundle()->pendingRequestsProviders();

        $this->assertCount(1, $providers);
        $this->assertInstanceOf(PendingClaimsProvider::class, $providers[0]);
        $this->assertSame('open_shifts', $providers[0]->key());
        $this->assertSame('shift_claim_pending_claims', $providers[0]->label());
        $this->assertSame('shift_claim_no_pending_claims', $providers[0]->emptyLabel());
        $this->assertSame('open_shifts.view', $providers[0]->permission());
        $this->assertSame('open_shifts', $providers[0]->storeFeature());
        $this->assertSame(30, $providers[0]->order());
    }

    public function testShiftFormSection(): void
    {
        $sections = $this->bundle()->shiftFormSections();

        $this->assertCount(1, $sections);
        $this->assertInstanceOf(ShiftFormSection::class, $sections[0]);
        $this->assertSame('open_shift', $sections[0]->key);
        $this->assertSame('shift-claim::shift-form-section', $sections[0]->view);
        $this->assertSame(['is_open', 'open_shift_note'], $sections[0]->fields);
        $this->assertSame('shift-claim::shift-form-actions', $sections[0]->actionsView);
        $this->assertSame('open_shifts', $sections[0]->storeFeature);
        $this->assertFileExists(dirname(__DIR__) . '/Views/shift-form-section.php');
        $this->assertFileExists(dirname(__DIR__) . '/Views/shift-form-actions.php');
    }

    public function testEventListeners(): void
    {
        $listeners = $this->bundle()->eventListeners();

        $this->assertSame([OpenShiftClosed::class], array_keys($listeners));
        $this->assertIsCallable($listeners[OpenShiftClosed::class]);
    }

    public function testNotificationTypes(): void
    {
        $declared = $this->bundle()->notificationTypes();
        $this->assertSame([
            'open_shift_published', 'shift_claim_submitted', 'shift_claim_approved',
            'shift_claim_rejected', 'shift_claim_withdrawn', 'shift_claim_handed_over',
        ], array_keys($declared));

        // Chaque déclaration est valide pour le Core (format enrichi du contrat 1.23).
        $types = [];
        foreach ($declared as $type => $declaration) {
            $types[$type] = \kintai\Contract\Extension\NotificationType::fromDeclaration($type, $declaration);
            $this->assertTrue($types[$type]->push);
        }
        // Renvoyées vers la bourse : objet propre au bundle, pour ne pas lire les notifications de planning du Core.
        foreach (['open_shift_published', 'shift_claim_submitted', 'shift_claim_rejected', 'shift_claim_withdrawn'] as $onList) {
            $this->assertSame('open_shift', $types[$onList]->referenceType, $onList);
        }
        // Renvoyées vers la vue jour du shift : objet « shift », que le Core marque lu à l'ouverture de cette vue.
        foreach (['shift_claim_approved', 'shift_claim_handed_over'] as $onDay) {
            $this->assertSame('shift', $types[$onDay]->referenceType, $onDay);
        }
        $this->assertSame('planning', $types['open_shift_published']->category);
        $this->assertSame('planning', $types['shift_claim_handed_over']->category);
        $this->assertSame('requests', $types['shift_claim_submitted']->category);
        // Les décisions qui concernent le planning de l'employé arrivent aussi par e-mail.
        foreach (['shift_claim_approved', 'shift_claim_rejected', 'shift_claim_handed_over'] as $decision) {
            $this->assertTrue($types[$decision]->email, $decision);
        }
        foreach (['open_shift_published', 'shift_claim_submitted', 'shift_claim_withdrawn'] as $other) {
            $this->assertFalse($types[$other]->email, $other);
        }
    }

    /** Le bundle n'apporte rien d'autre au Core. */
    public function testNoOtherContribution(): void
    {
        $bundle = $this->bundle();

        $this->assertSame([], $bundle->dashboardWidgets());
        $this->assertSame([], $bundle->viewVariables());
        $this->assertSame([], $bundle->actions());
        $this->assertSame([], $bundle->profileSections());
        $this->assertSame([], $bundle->cronJobs());
        $this->assertSame([], $bundle->absenceProviders());
        $this->assertSame([], $bundle->attendanceProviders());
        $this->assertSame([], $bundle->salesProviders());
    }

    /** Libellés des déclarations, des notifications, du journal d'activité et du formulaire, fournis par le bundle. */
    public function testDeclaredLabelsAreTranslatedByTheBundle(): void
    {
        $keys = ['shift_claim_open_shifts', 'perm_category_open_shifts', 'shift_claim_feature_open_shifts', 'shift_claim_feature_open_shifts_desc', 'shift_claim_pending_claims',
            'shift_claim_no_pending_claims', 'notif_open_shift_published', 'notif_open_shift_published_body', 'notif_shift_claim_submitted',
            'notif_shift_claim_approved', 'notif_shift_claim_approved_body', 'notif_shift_claim_rejected',
            'notif_shift_claim_rejected_body', 'notif_shift_claim_withdrawn', 'notif_shift_claim_withdrawn_body', 'notif_shift_claim_handed_over',
            'notif_shift_claim_handed_over_body', 'shift_claim_error_open_shift_unavailable', 'shift_claim_error_open_shift_own', 'shift_claim_open_shift_yours',
            'audit_shift_published', 'audit_shift_unpublished', 'audit_shift_claim_created', 'audit_shift_claim_withdrawn',
            'audit_shift_claim_approved', 'audit_shift_claim_rejected', 'shift_claim_open_shift_note_label', 'shift_claim_open_shift_note_placeholder',
            'shift_claim_publish_to_bourse', 'shift_claim_close_bourse', 'shift_claim_open_bourse', 'shift_claim_error_claim_not_found'];
        foreach (['fr', 'en', 'ja'] as $locale) {
            $lang = json_decode((string) file_get_contents(dirname(__DIR__) . "/lang/{$locale}.json"), true);
            foreach ($keys as $key) {
                $this->assertNotEmpty($lang[$key] ?? null, "{$locale} : {$key}");
            }
        }
    }
}
