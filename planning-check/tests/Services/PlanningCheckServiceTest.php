<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\PlanningCheck\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\PlanningCheck\Repositories\DatabasePlanningCheckRepository;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningCheckForce;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningCheckOverlayResolver;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningCheckService;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningMailService;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningPdfService;
use kintai\Bundles\Installed\PlanningCheck\Services\PlanningShiftRows;
use kintai\Core\Auth\PermissionService;
use kintai\Core\Mail\MailerService;
use kintai\Core\Mail\MailTransportInterface;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\RoleAssignmentRepositoryInterface;
use kintai\Core\Repositories\RoleRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\StoreRepositoryInterface;
use kintai\Core\Repositories\StoreUserRepositoryInterface;
use kintai\Core\Repositories\TranslationRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Services\NotificationService;
use kintai\Core\Services\TranslationService;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Cycle complet de la validation du planning de demain : soumettre, valider (PDF + e-mail), refuser, renvoyer, escalader.
 * Vrai schéma SQLite et vraies règles ; le PDF et le transport d'e-mail sont factices.
 */
#[AllowMockObjectsWithoutExpectations]
final class PlanningCheckServiceTest extends TestCase
{
    private const NOW = '2026-10-21 10:00:00'; // UTC = 19:00 à Tokyo ; en Europe/Paris 12:00 → on règle l'heure par le fuseau du magasin

    private PlanningCheckService $service;
    private DatabasePlanningCheckRepository $repo;
    /** @var list<array<string, mixed>> */
    public array $mails = [];
    /** @var list<array<string, mixed>> */
    public array $notifications = [];
    /** @var list<array<string, mixed>> */
    public array $shifts = [];
    public ?string $pdfBytes = '%PDF-fake';
    public bool $mailOk = true;
    private \DateTimeImmutable $now;
    private string $previousTimezone = 'UTC';
    /** @var array{0: StoreRepositoryInterface, 1: PlanningShiftRows} */
    private array $resolverDeps;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $loader = new class($capsule) {
            public function __construct(public Capsule $capsule) {}

            public function load(string $file): object
            {
                return require $file;
            }
        };
        $loader->load(dirname(__DIR__, 2) . '/database/migrations/2026_10_23_000001_create_planning_check_tables.php')->up();
        $loader->load(dirname(__DIR__, 2) . '/database/migrations/2026_10_24_000001_add_mail_recipient_ids_to_planning_check_settings.php')->up();

        // Les règles de temps suivent le fuseau de l'application, pas celui du magasin.
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');

        $this->repo = new DatabasePlanningCheckRepository();
        $this->now =new \DateTimeImmutable('2026-10-21 08:00:00', new \DateTimeZone('Asia/Tokyo')); // 08:00 à Tokyo
        $this->mails = $this->notifications = [];
        $this->pdfBytes = '%PDF-fake';
        $this->mailOk = true;
        $this->shifts = [
            ['id' => 1, 'user_id' => 5, 'shift_type_id' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'duration_minutes' => 480, 'pause_minutes' => 60],
            ['id' => 2, 'user_id' => 6, 'shift_type_id' => 1, 'start_time' => '10:00:00', 'end_time' => '18:00:00', 'duration_minutes' => 480, 'pause_minutes' => 60],
        ];

        $stores = $this->createStub(StoreRepositoryInterface::class);
        $stores->method('findById')->willReturnCallback(fn(int $id): ?array => $id === 3 ? ['id' => 3, 'name' => 'Shibuya', 'timezone' => 'Asia/Tokyo', 'locale' => 'fr'] : null);
        $stores->method('findAll')->willReturn([['id' => 3, 'name' => 'Shibuya', 'timezone' => 'Asia/Tokyo']]);

        $members = $this->createStub(StoreUserRepositoryInterface::class);
        $members->method('findByStore')->willReturn([['user_id' => 5], ['user_id' => 7]]);

        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findById')->willReturnCallback(fn(int $id): ?array => ['id' => $id, 'is_active' => 1, 'first_name' => 'U' . $id, 'last_name' => 'N', 'email' => "u$id@example.com"]);

        $shiftsRepo = $this->createStub(ShiftRepositoryInterface::class);
        $shiftsRepo->method('findByDate')->willReturnCallback(fn(int $storeId, string $date): array => $this->shifts);
        $types = $this->createStub(ShiftTypeRepositoryInterface::class);
        $types->method('findByStore')->willReturn([['id' => 1, 'name' => 'Journée', 'color' => '#fff']]);

        // 7 est approbateur, 5 ne l'est pas ; 99 est Owner (rôle système global).
        $assignments = $this->createStub(RoleAssignmentRepositoryInterface::class);
        $assignments->method('findByUser')->willReturnCallback(fn(int $uid): array => match ($uid) {
            7 => [['role_id' => 2, 'scope_type' => 'store', 'scope_id' => 3, 'user_id' => 7]],
            99 => [['role_id' => 1, 'scope_type' => 'global', 'scope_id' => null, 'user_id' => 99]],
            default => [],
        });
        $assignments->method('findByScope')->willReturn([['role_id' => 1, 'scope_type' => 'global', 'scope_id' => null, 'user_id' => 99]]);
        $roles = $this->createStub(RoleRepositoryInterface::class);
        $roles->method('findById')->willReturnCallback(fn(int $id): ?array => $id === 1 ? ['id' => 1, 'is_system' => 1] : ['id' => $id, 'is_system' => 0]);
        $roles->method('getPermissions')->willReturn(['planning_check.approve']);
        $roles->method('getGlobalPermissionKeys')->willReturn([]);
        $permissions = new PermissionService($assignments, $roles);

        $notifications = $this->createStub(NotificationService::class);
        $notifications->method('notifyMany')->willReturnCallback(function (array $ids, string $type, string $key, array $replace, ?int $ref, ?string $link): void {
            $this->notifications[] = compact('ids', 'type', 'replace', 'ref', 'link');
        });

        $translations = new TranslationService($this->createStub(TranslationRepositoryInterface::class), $this->createStub(LanguageRepositoryInterface::class));
        $test = $this;
        $pdf = new class(new ViewRenderer('/nonexistent'), $translations, $test) extends PlanningPdfService {
            public function __construct(ViewRenderer $view, TranslationService $translations, private PlanningCheckServiceTest $test)
            {
                parent::__construct($view, $translations);
            }

            public function generateBytes(array $store, array $check, array $day, array $context = []): ?string
            {
                return $this->test->pdfBytes;
            }
        };
        $transport = new class($test) implements MailTransportInterface {
            public function __construct(private PlanningCheckServiceTest $test) {}

            public function send(string $fromAddress, string $fromName, array $to, string $subject, string $htmlBody, array $attachmentPaths = [], array $extraHeaders = []): bool
            {
                $this->test->mails[] = ['to' => $to, 'subject' => $subject, 'attachments' => count($attachmentPaths), 'attached' => $attachmentPaths !== [] && is_file($attachmentPaths[0])];
                return $this->test->mailOk;
            }
        };
        $mail = new PlanningMailService(
            new MailerService(['driver' => 'native', 'from' => ['address' => 'noreply@kintai.test']], $transport),
            $translations,
            $pdf,
            new \kintai\Core\Services\UserSelection($members, $users, $permissions),
        );
        $rows = new PlanningShiftRows($shiftsRepo, $types, $users);
        $this->service = new PlanningCheckService($this->repo, $stores, $members, $users, $permissions, $notifications, $rows, $pdf, $mail);
        $this->resolverDeps = [$stores, $rows];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);
        $_SESSION = [];
    }

    /** Un résolveur neuf (il mémorise son résultat le temps d'une requête) pour l'utilisateur 5, qui peut soumettre. */
    private function resolve(\DateTimeImmutable $now): ?array
    {
        $resolver = new PlanningCheckOverlayResolver($this->repo, $this->resolverDeps[0], $this->service, $this->resolverDeps[1]);

        return $resolver->resolve([
            'user' => ['id' => 5], 'now' => $now, 'store_ids' => [3],
            'can' => static fn(string $permission, ?int $storeId = null): bool => $permission === 'planning_check.submit',
        ]);
    }

    private function enable(array $overrides = []): void
    {
        $this->repo->saveSettings(3, $overrides + ['enabled' => true, 'deadline_time' => '12:00', 'window_start_time' => '06:00', 'weekdays' => [1, 2, 3, 4, 5, 6, 7],
            'mail_recipients' => ['boss@example.com'], 'include_open_shifts' => false]);
    }

    private function submitted(): array
    {
        $this->enable();
        return $this->service->submit(3, '2026-10-22', 5, false, $this->now)['check'];
    }

    // ── État ────────────────────────────────────────────────────────────────────────────────────────────────────

    public function testStateIsDueInTheWindowOnlyWhenEnabled(): void
    {
        $store = ['id' => 3, 'timezone' => 'Asia/Tokyo'];

        $this->assertFalse($this->service->state($store, $this->now)['due'], 'désactivée');
        $this->enable();
        $state = $this->service->state($store, $this->now);
        $this->assertTrue($state['due']);
        $this->assertSame('2026-10-22', $state['date']);
        $this->assertFalse($state['late']);
        $this->assertSame('pending', $state['check']['status']);

        $early = new \DateTimeImmutable('2026-10-21 05:00:00', new \DateTimeZone('Asia/Tokyo'));
        $this->assertFalse($this->service->state($store, $early)['due'], 'fenêtre pas encore ouverte');
        $late = new \DateTimeImmutable('2026-10-21 12:30:00', new \DateTimeZone('Asia/Tokyo'));
        $this->assertTrue($this->service->state($store, $late)['late']);
    }

    public function testTheStoreTimezoneIsIgnoredInFavourOfTheApplicationOne(): void
    {
        $this->enable();
        // Magasin resté sur le « UTC » par défaut : 08:00 à Tokyo (fuseau de l'application) reste 08:00, pas 23:00 la veille.
        $state = $this->service->state(['id' => 3, 'timezone' => 'UTC'], $this->now);

        $this->assertTrue($state['due']);
        $this->assertSame('2026-10-22', $state['date']);
        $this->assertFalse($state['late']);
    }

    public function testAClosedDayIsNeverDue(): void
    {
        $this->enable(['weekdays' => [1, 2, 3, 4, 5]]); // 22/10 = jeudi → ouvert ; on vise le vendredi 23 → samedi décoché
        $friday = new \DateTimeImmutable('2026-10-23 08:00:00', new \DateTimeZone('Asia/Tokyo')); // demain = samedi
        $this->assertFalse($this->service->state(['id' => 3, 'timezone' => 'Asia/Tokyo'], $friday)['due']);
    }

    // ── Soumettre ───────────────────────────────────────────────────────────────────────────────────────────────

    public function testSubmittingNotifiesTheApproversButNotTheSubmitter(): void
    {
        $result = $this->submitted();

        $this->assertSame('submitted', $result['status']);
        $this->assertSame(5, $result['submitted_by']);
        $notified = $this->notifications[0];
        $this->assertSame('planning_check_submitted', $notified['type']);
        $this->assertEqualsCanonicalizing([7, 99], $notified['ids'], 'valideurs membres + Owner, pas le soumetteur');
        $this->assertSame('/admin/stores/3/planning-check?highlight=' . $result['id'], $notified['link']);
        $this->assertSame($result['id'], $notified['ref']);
    }

    public function testASecondSubmissionIsRefusedOnceTheFirstManagerSubmitted(): void
    {
        $this->submitted();
        $second = $this->service->submit(3, '2026-10-22', 6, false, $this->now);

        $this->assertFalse($second['ok']);
        $this->assertSame('already_submitted', $second['error']);
    }

    public function testNoShiftNeedsAnExplicitConfirmation(): void
    {
        $this->enable();
        $this->shifts = [];

        $refused = $this->service->submit(3, '2026-10-22', 5, false, $this->now);
        $this->assertSame('no_shifts', $refused['error']);

        $accepted = $this->service->submit(3, '2026-10-22', 5, true, $this->now);
        $this->assertTrue($accepted['ok']);
        $this->assertTrue($accepted['check']['confirmed_empty']);
    }

    public function testSubmittingIsRefusedWhenTheStoreIsNotEnabled(): void
    {
        $this->assertSame('not_enabled', $this->service->submit(3, '2026-10-22', 5, false, $this->now)['error']);
        $this->assertSame('not_enabled', $this->service->submit(404, '2026-10-22', 5, false, $this->now)['error']);
    }

    public function testStartingToEditSnoozesTheWindowThenResumingClearsIt(): void
    {
        $this->enable();
        $this->service->startEditing(3, '2026-10-22', 5, $this->now);
        $check = $this->repo->findFor(3, '2026-10-22');

        $this->assertSame(5, $check['editing_by']);
        $this->assertGreaterThan($this->now->getTimestamp(), strtotime((string) $check['editing_until']));

        $this->service->resumeValidation(3, '2026-10-22');
        $this->assertNull($this->repo->findFor(3, '2026-10-22')['editing_until']);
    }

    // ── Valider ─────────────────────────────────────────────────────────────────────────────────────────────────

    public function testApprovingGeneratesAndSendsThePdfAndNotifiesTheSubmitter(): void
    {
        $check = $this->submitted();
        $this->notifications = [];

        $result = $this->service->approve($check['id'], 7, $this->now);

        $this->assertTrue($result['ok']);
        $this->assertSame('validated', $result['check']['status']);
        $this->assertSame('sent', $result['check']['send_status']);
        $this->assertSame(7, $result['check']['validated_by']);
        $this->assertCount(1, $this->mails);
        $this->assertSame(['boss@example.com'], $this->mails[0]['to']);
        $this->assertStringStartsWith('[Shibuya]', $this->mails[0]['subject']);
        $this->assertTrue($this->mails[0]['attached'], 'le PDF est joint');
        $this->assertSame(['planning_check_validated'], array_column($this->notifications, 'type'));
        $this->assertSame([5], $this->notifications[0]['ids']);
        $this->assertCount(1, $this->repo->sendsFor($check['id']));
    }

    public function testChosenEmployeesEachReceiveTheirOwnMailWithThePdf(): void
    {
        $check = $this->submitted();
        $this->enable(['mail_recipient_ids' => [5, 7, 98, 99], 'mail_recipients' => ['boss@EXAMPLE.com', 'U5@example.com']]);

        $result = $this->service->approve($check['id'], 7, $this->now);

        $this->assertTrue($result['ok']);
        // 98 n'est pas membre du magasin : écarté ; 99 est l'Owner : proposable même hors magasin ; U5@example.com est déjà l'adresse de l'employé 5 : pas de doublon.
        $this->assertSame([['u5@example.com'], ['u7@example.com'], ['u99@example.com'], ['boss@EXAMPLE.com']], array_column($this->mails, 'to'));
        foreach ($this->mails as $mail) {
            $this->assertTrue($mail['attached'], 'le PDF est joint à chaque message');
        }
        $this->assertSame('["u5@example.com","u7@example.com","u99@example.com","boss@EXAMPLE.com"]', $this->repo->sendsFor($check['id'])[0]['recipients'], 'l\'historique garde les adresses réellement utilisées');
    }

    public function testAnEmployeeSelectionAloneIsEnoughToApprove(): void
    {
        $check = $this->submitted();
        $this->enable(['mail_recipient_ids' => [5], 'mail_recipients' => []]);

        $this->assertTrue($this->service->approve($check['id'], 7, $this->now)['ok']);
        $this->assertSame([['u5@example.com']], array_column($this->mails, 'to'));
    }

    public function testApprovingNeedsAValidRecipientAndKeepsTheCheckSubmitted(): void
    {
        $check = $this->submitted();
        $this->enable(['mail_recipients' => []]);

        $result = $this->service->approve($check['id'], 7, $this->now);

        $this->assertSame('no_recipients', $result['error']);
        $this->assertSame('submitted', $this->repo->find($check['id'])['status']);
        $this->assertSame([], $this->mails);
    }

    public function testAMailFailureLeavesTheCheckValidatedButMarksTheSendFailedAndNotifies(): void
    {
        $check = $this->submitted();
        $this->mailOk = false;
        $this->notifications = [];

        $result = $this->service->approve($check['id'], 7, $this->now);

        $this->assertTrue($result['ok']);
        $this->assertSame('failed', $result['check']['send_status']);
        $this->assertContains('planning_check_failed', array_column($this->notifications, 'type'));
        $this->assertTrue($this->service->needsResend($this->repo->find($check['id'])));
    }

    public function testAPdfFailureIsReportedAsAFailedSend(): void
    {
        $check = $this->submitted();
        $this->pdfBytes = null;

        $result = $this->service->approve($check['id'], 7, $this->now);

        $this->assertSame('failed', $result['check']['send_status']);
        $this->assertSame('pdf_failed', $result['check']['last_error']);
        $this->assertSame([], $this->mails);
    }

    public function testOnlyASubmittedCheckCanBeApproved(): void
    {
        $this->enable();
        $pending = $this->repo->ensure(3, '2026-10-22');

        $this->assertSame('bad_status', $this->service->approve($pending['id'], 7, $this->now)['error']);
        $this->assertSame('not_found', $this->service->approve(999, 7, $this->now)['error']);
    }

    public function testApprovingTwiceSendsOnlyOnce(): void
    {
        $check = $this->submitted();
        $this->service->approve($check['id'], 7, $this->now);
        $again = $this->service->approve($check['id'], 99, $this->now);

        $this->assertSame('bad_status', $again['error']);
        $this->assertCount(1, $this->mails);
    }

    // ── Refuser ─────────────────────────────────────────────────────────────────────────────────────────────────

    public function testRejectingNeedsAReasonAndSendsTheCheckBackToTheSubmitter(): void
    {
        $check = $this->submitted();
        $this->notifications = [];

        $this->assertSame('reason_required', $this->service->reject($check['id'], 7, '   ', $this->now)['error']);
        $result = $this->service->reject($check['id'], 7, 'Il manque Léa', $this->now);

        $this->assertTrue($result['ok']);
        $this->assertSame('rejected', $result['check']['status']);
        $this->assertSame('Il manque Léa', $result['check']['reject_reason']);
        $this->assertSame('planning_check_rejected', $this->notifications[0]['type']);
        $this->assertSame([5], $this->notifications[0]['ids']);
        $this->assertSame('Il manque Léa', $this->notifications[0]['replace']['reason']);

        // Le soumetteur corrige et soumet de nouveau.
        $this->assertTrue($this->service->submit(3, '2026-10-22', 5, false, $this->now)['ok']);
    }

    // ── Renvoyer ────────────────────────────────────────────────────────────────────────────────────────────────

    public function testAChangeAfterTheSendOffersAnUpdatedResendWithItsOwnHistory(): void
    {
        $check = $this->submitted();
        $validated = $this->service->approve($check['id'], 7, $this->now)['check'];
        $this->assertFalse($this->service->needsResend($validated), 'rien n\'a changé');

        $this->shifts[] = ['id' => 3, 'user_id' => 8, 'shift_type_id' => 1, 'start_time' => '12:00:00', 'end_time' => '20:00:00', 'duration_minutes' => 480, 'pause_minutes' => 60];
        $this->assertTrue($this->service->needsResend($this->repo->find($check['id'])), 'un shift ajouté après l\'envoi');

        $result = $this->service->resend($check['id'], 7, $this->now);
        $this->assertTrue($result['ok']);
        $this->assertStringContainsString('planning_check_updated_label', $this->mails[1]['subject'], 'version mise à jour');
        $this->assertCount(2, $this->mails);
        $this->assertCount(2, $this->repo->sendsFor($check['id']));
        $this->assertFalse($this->service->needsResend($this->repo->find($check['id'])));
    }

    public function testResendingIsOnlyForValidatedChecks(): void
    {
        $check = $this->submitted();

        $this->assertSame('bad_status', $this->service->resend($check['id'], 7, $this->now)['error']);
    }

    // ── Envoyer maintenant / refaire le signalement ─────────────────────────────────────────────────────────────

    public function testSendNowSubmitsValidatesAndSendsEvenOutsideTheWindow(): void
    {
        $this->enable();
        $early = new \DateTimeImmutable('2026-10-21 05:00:00', new \DateTimeZone('Asia/Tokyo')); // fenêtre pas encore ouverte

        $result = $this->service->sendNow(3, '2026-10-22', 7, false, $early);

        $this->assertTrue($result['ok']);
        $this->assertSame('validated', $result['check']['status']);
        $this->assertSame('sent', $result['check']['send_status']);
        $this->assertSame(7, $result['check']['submitted_by']);
        $this->assertSame(7, $result['check']['validated_by']);
        $this->assertCount(1, $this->mails);
        $this->assertSame(['boss@example.com'], $this->mails[0]['to']);
        $this->assertSame(1, $this->mails[0]['attachments']);
    }

    public function testSendNowValidatesWhatWasAlreadySubmitted(): void
    {
        $check = $this->submitted();

        $result = $this->service->sendNow(3, '2026-10-22', 7, false, $this->now);

        $this->assertTrue($result['ok']);
        $this->assertSame($check['id'], $result['check']['id']);
        $this->assertSame('validated', $result['check']['status']);
        $this->assertSame(5, $result['check']['submitted_by'], 'le soumetteur d\'origine est conservé');
        $this->assertCount(1, $this->mails);
    }

    public function testSendNowRegeneratesAndResendsAnAlreadyValidatedCheckAsAnUpdate(): void
    {
        $this->enable();
        $this->service->sendNow(3, '2026-10-22', 7, false, $this->now);
        $this->shifts[] = ['id' => 3, 'user_id' => 8, 'shift_type_id' => 1, 'start_time' => '12:00:00', 'end_time' => '20:00:00', 'duration_minutes' => 480, 'pause_minutes' => 60];

        $result = $this->service->sendNow(3, '2026-10-22', 7, false, $this->now);

        $this->assertTrue($result['ok']);
        $this->assertCount(2, $this->mails);
        $this->assertStringContainsString('planning_check_updated_label', $this->mails[1]['subject']);
        $this->assertFalse($this->service->needsResend($this->repo->find($result['check']['id'])), 'la nouvelle version reflète les shifts actuels');
    }

    public function testSendNowWithoutShiftNeedsTheExplicitConfirmation(): void
    {
        $this->enable();
        $this->shifts = [];

        $refused = $this->service->sendNow(3, '2026-10-22', 7, false, $this->now);
        $this->assertFalse($refused['ok']);
        $this->assertSame('no_shifts', $refused['error']);
        $this->assertSame([], $this->mails);

        $this->assertTrue($this->service->sendNow(3, '2026-10-22', 7, true, $this->now)['ok']);
        $this->assertCount(1, $this->mails);
    }

    public function testSendNowNeedsAnEnabledStoreAndARecipient(): void
    {
        $this->assertSame('not_enabled', $this->service->sendNow(3, '2026-10-22', 7, false, $this->now)['error']);

        $this->enable(['mail_recipients' => []]);
        $result = $this->service->sendNow(3, '2026-10-22', 7, false, $this->now);
        $this->assertSame('no_recipients', $result['error']);
        $this->assertSame([], $this->mails);
    }

    public function testReopeningAValidatedCheckSendsTheNextValidationAsAnUpdate(): void
    {
        $this->enable();
        $sent = $this->service->sendNow(3, '2026-10-22', 7, false, $this->now)['check'];

        $reopened = $this->service->reopen(3, '2026-10-22');

        $this->assertTrue($reopened['ok']);
        $this->assertSame('pending', $reopened['check']['status']);
        $this->assertNull($reopened['check']['submitted_by']);
        $this->assertNull($reopened['check']['validated_by']);
        $this->assertCount(1, $this->repo->sendsFor($sent['id']), 'l\'historique des envois est conservé');

        $this->assertTrue($this->service->sendNow(3, '2026-10-22', 7, false, $this->now)['ok']);
        $this->assertCount(2, $this->mails);
        $this->assertStringContainsString('planning_check_updated_label', $this->mails[1]['subject']);
    }

    public function testReopeningAlsoWorksOnASubmittedCheck(): void
    {
        $this->submitted();

        $this->assertSame('pending', $this->service->reopen(3, '2026-10-22')['check']['status']);
    }

    public function testReopeningNeedsASubmittedOrValidatedCheck(): void
    {
        $this->enable();
        $this->assertSame('not_found', $this->service->reopen(3, '2026-10-22')['error'], 'rien à rouvrir');

        $this->service->state(['id' => 3, 'timezone' => 'Asia/Tokyo'], $this->now); // crée la vérification en attente
        $this->assertSame('bad_status', $this->service->reopen(3, '2026-10-22')['error']);
    }

    // ── Revérifier : forcer la fenêtre habituelle ───────────────────────────────────────────────────────────────

    public function testForceRecheckReopensAValidatedCheckAndKeepsTheSendHistory(): void
    {
        $this->enable();
        $sent = $this->service->sendNow(3, '2026-10-22', 7, false, $this->now)['check'];

        $result = $this->service->forceRecheck(3, '2026-10-22');

        $this->assertTrue($result['ok']);
        $this->assertSame('pending', $result['check']['status']);
        $this->assertNull($result['check']['validated_by']);
        $this->assertCount(1, $this->repo->sendsFor($sent['id']));
    }

    public function testForceRecheckCreatesTheCheckAndLiftsTheEditingWithdrawal(): void
    {
        $this->enable();
        $created = $this->service->forceRecheck(3, '2026-10-22');
        $this->assertSame('pending', $created['check']['status'], 'créée si elle n\'existait pas encore');

        $this->service->startEditing(3, '2026-10-22', 5, $this->now);
        $this->assertNotNull($this->repo->findFor(3, '2026-10-22')['editing_until']);

        $this->assertNull($this->service->forceRecheck(3, '2026-10-22')['check']['editing_until']);
    }

    public function testForceRecheckNeedsAnEnabledStore(): void
    {
        $this->assertSame('not_enabled', $this->service->forceRecheck(3, '2026-10-22')['error']);
    }

    public function testTheWindowDoesNotOpenOutsideTheHoursUnlessForced(): void
    {
        $this->enable();
        $early = new \DateTimeImmutable('2026-10-21 05:00:00', new \DateTimeZone('Asia/Tokyo')); // fenêtre pas encore ouverte

        $this->assertNull($this->resolve($early), 'hors fenêtre : rien');

        PlanningCheckForce::mark(3, '2026-10-22');
        $forced = $this->resolve($early);

        $this->assertSame(PlanningCheckOverlayResolver::KIND_SUBMIT, $forced['kind']);
        $this->assertSame('2026-10-22', $forced['date']);
        $this->assertSame('pending', $forced['check']['status']);
        $this->assertCount(2, $forced['day']['rows'], 'les shifts de demain sont montrés');
    }

    public function testRecheckingAValidatedCheckOpensTheNormalWindowAgainAndSubmittingCreatesTheNewReport(): void
    {
        $this->enable();
        $this->service->sendNow(3, '2026-10-22', 7, false, $this->now);
        $this->assertNull($this->resolve($this->now), 'validée : la fenêtre ne s\'affiche plus');

        $this->service->forceRecheck(3, '2026-10-22');
        PlanningCheckForce::mark(3, '2026-10-22');
        $this->assertSame(PlanningCheckOverlayResolver::KIND_SUBMIT, $this->resolve($this->now)['kind']);

        $this->assertTrue($this->service->submit(3, '2026-10-22', 5, false, $this->now)['ok']);
        PlanningCheckForce::clear(3);
        $this->assertNull($this->resolve($this->now), 'soumise : plus de fenêtre pour le manager');

        $this->assertTrue($this->service->approve($this->repo->findFor(3, '2026-10-22')['id'], 7, $this->now)['ok']);
        $this->assertCount(2, $this->mails);
        $this->assertStringContainsString('planning_check_updated_label', $this->mails[1]['subject'], 'le nouveau rapport part comme version mise à jour');
    }

    public function testAForcedWindowOnlyConcernsThatDayAndThatStore(): void
    {
        $this->enable();
        PlanningCheckForce::mark(3, '2026-10-25'); // un autre jour que demain
        $early = new \DateTimeImmutable('2026-10-21 05:00:00', new \DateTimeZone('Asia/Tokyo'));

        $this->assertNull($this->resolve($early));
        $this->assertFalse(PlanningCheckForce::isForced(4, '2026-10-25'));
    }

    // ── Escalade ────────────────────────────────────────────────────────────────────────────────────────────────

    public function testNothingIsEscalatedBeforeTheDeadlineOrOutsideTheWindow(): void
    {
        $this->enable();

        $this->assertSame([], $this->service->escalate($this->now));
        $early = new \DateTimeImmutable('2026-10-21 05:00:00', new \DateTimeZone('Asia/Tokyo'));
        $this->assertSame([], $this->service->escalate($early));
    }

    public function testAnUnsubmittedCheckIsEscalatedOnceAfterTheDeadlineWithoutSendingAnyPdf(): void
    {
        $this->enable();
        $late = new \DateTimeImmutable('2026-10-21 12:05:00', new \DateTimeZone('Asia/Tokyo'));

        $first = $this->service->escalate($late);
        $second = $this->service->escalate($late);

        $this->assertCount(1, $first);
        $this->assertSame([], $second, 'une seule escalade');
        $this->assertSame('escalated', $this->repo->findFor(3, '2026-10-22')['status']);
        $overdue = array_values(array_filter($this->notifications, fn(array $n): bool => $n['type'] === 'planning_check_overdue'));
        $this->assertCount(1, $overdue);
        $this->assertEqualsCanonicalizing([7, 99], $overdue[0]['ids'], 'valideurs (membres) et Owners');
        $this->assertSame([], $this->mails, 'aucun PDF sans validation');
        // Le manager peut encore soumettre un planning escaladé.
        $this->assertTrue($this->service->submit(3, '2026-10-22', 5, false, $late)['ok']);
    }

    public function testASubmittedCheckIsNotEscalated(): void
    {
        $this->submitted();
        $late = new \DateTimeImmutable('2026-10-21 12:05:00', new \DateTimeZone('Asia/Tokyo'));

        $this->assertSame([], $this->service->escalate($late));
        $this->assertSame('submitted', $this->repo->findFor(3, '2026-10-22')['status']);
    }
}
