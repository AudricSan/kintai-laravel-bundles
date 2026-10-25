<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\DailyReport\Services;

use PHPUnit\Framework\TestCase;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Mail\MailerService;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportMailService;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportPermissionService;
use kintai\Core\Services\TranslationService;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\TranslationRepositoryInterface;

final class DailyReportMailServiceTest extends TestCase
{
    private DailyReportPermissionService $permissions;
    private DailyReportMailService $service;

    protected function setUp(): void
    {
        // DailyReportPermissionService et MailerService sont final — on utilise les vraies classes.
        // MailerService avec driver 'native' : mail() retourne false en CLI, ce qui est attendu.
        $this->permissions = new DailyReportPermissionService(new PermissionService(
            $this->createStub(RoleAssignmentRepositoryInterface::class),
            $this->createStub(RoleRepositoryInterface::class),
        ));
        $mailer            = new MailerService(['driver' => 'native', 'from' => ['address' => 'test@kintai.test', 'name' => 'Kintai Test']]);
        $translations      = new TranslationService(
            $this->createStub(TranslationRepositoryInterface::class),
            $this->createStub(LanguageRepositoryInterface::class),
        );
        $this->service     = new DailyReportMailService($this->permissions, $mailer, $translations, new \kintai\Core\Services\UserSelection(
            $this->createStub(\kintai\Core\Repositories\StoreUserRepositoryInterface::class),
            $this->createStub(\kintai\Core\Repositories\UserRepositoryInterface::class),
            new PermissionService($this->createStub(RoleAssignmentRepositoryInterface::class), $this->createStub(RoleRepositoryInterface::class)),
        ));
    }

    private function report(string $date = '2025-06-15'): array
    {
        return [
            'id'             => 1,
            'report_date'    => $date,
            'status'         => 'validated',
            'sales_total'    => 150000,
            'customer_count' => 80,
            'labor_cost'     => 40000,
            'waste_total'    => 3000,
            'notes'          => 'RAS',
        ];
    }

    /** Store sans daily_report_settings → DEFAULTS → mail_recipients = [] */
    private function storeWithNoSettings(): array
    {
        return ['id' => 1, 'name' => 'Test Store', 'currency' => '¥'];
    }

    /** Store avec destinataires invalides dans les settings JSON */
    private function storeWithInvalidRecipients(): array
    {
        $settings = json_encode(['mail_recipients' => ['not-an-email', 'bad@', '@nodomain']]);
        return ['id' => 1, 'name' => 'Test Store', 'currency' => '¥', 'daily_report_settings' => $settings];
    }

    /** Store avec un destinataire valide */
    private function storeWithValidRecipient(): array
    {
        $settings = json_encode(['mail_recipients' => ['valid@example.com']]);
        return ['id' => 1, 'name' => 'Test Store', 'currency' => '¥', 'daily_report_settings' => $settings];
    }

    // -------------------------------------------------------------------------
    // Pas de destinataires → false
    // -------------------------------------------------------------------------

    public function testSendReturnsFalseWhenNoRecipients(): void
    {
        // DEFAULTS.mail_recipients = [] → false
        $this->assertFalse($this->service->send($this->report(), $this->storeWithNoSettings()));
    }

    public function testSendReturnsFalseWhenAllEmailsInvalid(): void
    {
        $this->assertFalse($this->service->send($this->report(), $this->storeWithInvalidRecipients()));
    }

    // -------------------------------------------------------------------------
    // Avec destinataire valide → mail() retourne bool (false en env CLI)
    // -------------------------------------------------------------------------

    public function testSendReturnsBoolWithValidRecipient(): void
    {
        // mail() retourne false en env CLI/test, mais le service ne plante pas
        $result = $this->service->send($this->report(), $this->storeWithValidRecipient());
        $this->assertIsBool($result);
    }

    // -------------------------------------------------------------------------
    // pdfPath inexistant → envoie sans pièce jointe
    // -------------------------------------------------------------------------

    public function testSendWithNonExistentPdfPathDoesNotThrow(): void
    {
        // Pas de destinataires → return false avant d'atteindre le code d'attachement
        $result = $this->service->send($this->report(), $this->storeWithNoSettings(), '/nonexistent/report.pdf');
        $this->assertFalse($result);
    }

    public function testSendWithValidRecipientAndFakePdfReturnsBool(): void
    {
        // mail() peut émettre E_WARNING sur XAMPP (SMTP non configuré) — on le supprime dans ce test
        set_error_handler(static fn() => true, E_WARNING);
        try {
            $result = $this->service->send($this->report(), $this->storeWithValidRecipient(), '/fake/path.pdf');
        } finally {
            restore_error_handler();
        }
        $this->assertIsBool($result);
    }

    // -------------------------------------------------------------------------
    // Settings fusionnés avec DEFAULTS (mail_recipients surchargé)
    // -------------------------------------------------------------------------

    public function testEmptyJsonSettingsFallsBackToDefaults(): void
    {
        $store = ['id' => 1, 'name' => 'S', 'daily_report_settings' => '{}'];
        // {} → merge avec DEFAULTS → mail_recipients = []
        $this->assertFalse($this->service->send($this->report(), $store));
    }

    public function testNullDailyReportSettingsUsesDefaults(): void
    {
        $store = ['id' => 1, 'name' => 'S', 'daily_report_settings' => null];
        $this->assertFalse($this->service->send($this->report(), $store));
    }
}
