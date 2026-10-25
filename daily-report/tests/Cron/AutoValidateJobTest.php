<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\DailyReport\Cron;

use PHPUnit\Framework\TestCase;
use kintai\Core\Auth\PermissionService;
use kintai\Bundles\Installed\DailyReport\Cron\AutoValidateJob;
use kintai\Core\Mail\MailerService;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportAutoValidateService;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportMailService;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportPdfService;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportPermissionService;
use kintai\Core\Services\TranslationService;
use kintai\Bundles\Installed\DailyReport\Repositories\DailyReportRepositoryInterface;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\TranslationRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\UI\ViewRenderer;

final class AutoValidateJobTest extends TestCase
{
    private AutoValidateJob $job;

    protected function setUp(): void
    {
        $stores  = $this->createStub(StoreRepositoryInterface::class);
        $stores->method('findActive')->willReturn([]);
        $reports = $this->createStub(DailyReportRepositoryInterface::class);
        $users   = $this->createStub(UserRepositoryInterface::class);

        $shifts     = $this->createStub(ShiftRepositoryInterface::class);
        $shiftTypes = $this->createStub(ShiftTypeRepositoryInterface::class);
        $pdfService = new DailyReportPdfService(
            new ViewRenderer(dirname(__DIR__, 2) . '/Views'),
            new TranslationService(
                $this->createStub(TranslationRepositoryInterface::class),
                $this->createStub(LanguageRepositoryInterface::class),
            ),
            $shifts,
            $shiftTypes,
            $users,
        );

        $permissions = new DailyReportPermissionService(new PermissionService(
            $this->createStub(RoleAssignmentRepositoryInterface::class),
            $this->createStub(RoleRepositoryInterface::class),
        ));
        $mailService = new DailyReportMailService(
            $permissions,
            new MailerService(['driver' => 'native', 'from' => ['address' => 'test@kintai.test', 'name' => 'Kintai']]),
            new TranslationService(
                $this->createStub(TranslationRepositoryInterface::class),
                $this->createStub(LanguageRepositoryInterface::class),
            ),
            new \kintai\Core\Services\UserSelection(
                $this->createStub(\kintai\Core\Repositories\StoreUserRepositoryInterface::class),
                $users,
                new PermissionService($this->createStub(RoleAssignmentRepositoryInterface::class), $this->createStub(RoleRepositoryInterface::class)),
            ),
        );

        $logger          = new AuditLogger();

        $autoValidate = new DailyReportAutoValidateService($stores, $reports, $users, $permissions, $pdfService, $mailService);
        $this->job     = new AutoValidateJob($autoValidate, $logger);
    }

    // -------------------------------------------------------------------------
    // getName()
    // -------------------------------------------------------------------------

    public function testGetNameReturnsAutoValidate(): void
    {
        $this->assertSame('auto-validate', $this->job->getName());
    }

    // -------------------------------------------------------------------------
    // run()
    // -------------------------------------------------------------------------

    public function testRunWithDefaultDateUsesYesterday(): void
    {
        $response = $this->job->run($this->makeRequest());
        $data     = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertTrue($data['ok']);
        $this->assertSame(date('Y-m-d', strtotime('yesterday')), $data['date']);
        $this->assertSame(0, $data['validated']);
        $this->assertSame(0, $data['skipped']);
    }

    public function testRunWithCustomDate(): void
    {
        $response = $this->job->run($this->makeRequest(['date' => '2026-06-01']));
        $data     = json_decode($response->body(), true);

        $this->assertSame('2026-06-01', $data['date']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeRequest(array $query = []): Request
    {
        $queryStr = $query ? '?' . http_build_query($query) : '';
        $_SERVER  = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/cron/auto-validate$queryStr", 'SCRIPT_NAME' => '/index.php'];
        $_GET     = $query;
        $_POST    = $_COOKIE = $_FILES = [];
        return new Request();
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
        $_GET    = [];
        $_POST   = [];
    }
}
