<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Messaging;

use kintai\Contract\Extension\NavItem;
use kintai\Contract\Extension\StoreFeature;
use kintai\Bundles\Installed\Messaging\MessagingBundle;
use PHPUnit\Framework\TestCase;

/**
 * Ce que le bundle déclare au Core. Le Core n'a plus de repli pour messaging (étape 4 du plan du contrat,
 * extraction) : ces déclarations sont la seule source de son entrée de menu (et de son badge), de sa catégorie de
 * permissions, de sa fonctionnalité par magasin et de son type de notification, d'où des valeurs vérifiées une à une.
 */
final class ContractDeclarationsTest extends TestCase
{
    private function bundle(): MessagingBundle
    {
        return (new \ReflectionClass(MessagingBundle::class))->newInstanceWithoutConstructor();
    }

    public function testNavigation(): void
    {
        $items = $this->bundle()->navigation();

        $this->assertCount(1, $items);
        $this->assertInstanceOf(NavItem::class, $items[0]);
        $this->assertSame('messages', $items[0]->key);
        $this->assertSame(NavItem::REQUESTS, $items[0]->section);
        $this->assertSame('messaging_messages', $items[0]->label);
        $this->assertSame('admin.messages', $items[0]->managerRoute);
        $this->assertSame('employee.messages', $items[0]->employeeRoute);
        $this->assertSame('message', $items[0]->icon);
        $this->assertSame(['/admin/messages', '/employee/messages'], $items[0]->activePaths);
        $this->assertSame('messages', $items[0]->storeFeature);
        $this->assertSame('unread_messages_count', $items[0]->badge);
        $this->assertSame(40, $items[0]->order);
        $this->assertTrue($items[0]->mobile);
    }

    public function testPermissions(): void
    {
        $this->assertSame(['messaging' => ['view', 'send', 'delete']], $this->bundle()->permissions());
    }

    public function testStoreFeature(): void
    {
        $features = $this->bundle()->storeFeatures();

        $this->assertCount(1, $features);
        $this->assertInstanceOf(StoreFeature::class, $features[0]);
        $this->assertSame('messages', $features[0]->key);
        $this->assertSame('messaging_feature_messages', $features[0]->label);
        $this->assertSame('messaging_feature_messages_desc', $features[0]->description);
        $this->assertSame('💬', $features[0]->icon);
        $this->assertSame(60, $features[0]->order);
    }

    /** Badge de la navigation : la variable que le Core lisait jusqu'ici lui-même. */
    public function testViewVariables(): void
    {
        $this->assertSame(['unread_messages_count'], array_keys($this->bundle()->viewVariables()));
    }

    public function testNotificationTypes(): void
    {
        $declared = $this->bundle()->notificationTypes();
        $this->assertSame(['message_received'], array_keys($declared));

        // Déclaration valide pour le Core (format enrichi du contrat 1.23).
        $type = \kintai\Contract\Extension\NotificationType::fromDeclaration('message_received', $declared['message_received']);
        $this->assertSame('messages', $type->category);
        $this->assertSame('message_thread', $type->referenceType);
        $this->assertTrue($type->push);
        $this->assertFalse($type->email);
    }

    /** Le bundle n'apporte rien d'autre au Core. */
    public function testNoOtherContribution(): void
    {
        $bundle = $this->bundle();

        $this->assertSame([], $bundle->dashboardWidgets());
        $this->assertSame([], $bundle->actions());
        $this->assertSame([], $bundle->profileSections());
        $this->assertSame([], $bundle->cronJobs());
        $this->assertSame([], $bundle->pendingRequestsProviders());
        $this->assertSame([], $bundle->absenceProviders());
        $this->assertSame([], $bundle->attendanceProviders());
    }

    /** Libellés des déclarations, des notifications et du journal d'activité, fournis par le bundle. */
    public function testDeclaredLabelsAreTranslatedByTheBundle(): void
    {
        $keys = ['messaging_messages', 'perm_category_messaging', 'messaging_feature_messages', 'messaging_feature_messages_desc', 'notif_message_received',
            'notif_message_received_body', 'notif_thread_message_received_body', 'audit_message_sent', 'audit_message_replied',
            'audit_message_thread_deleted', 'audit_message_deleted'];
        foreach (['fr', 'en', 'ja'] as $locale) {
            $lang = json_decode((string) file_get_contents(dirname(__DIR__) . "/lang/{$locale}.json"), true);
            foreach ($keys as $key) {
                $this->assertNotEmpty($lang[$key] ?? null, "{$locale} : {$key}");
            }
        }
    }
}
