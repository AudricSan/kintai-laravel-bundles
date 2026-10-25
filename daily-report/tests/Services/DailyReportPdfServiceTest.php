<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\DailyReport\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use kintai\Bundles\Installed\DailyReport\Services\DailyReportPdfService;
use kintai\Core\Services\TranslationService;
use kintai\Core\Repositories\LanguageRepositoryInterface;
use kintai\Core\Repositories\ShiftRepositoryInterface;
use kintai\Core\Repositories\ShiftTypeRepositoryInterface;
use kintai\Core\Repositories\TranslationRepositoryInterface;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\UI\ViewRenderer;

final class DailyReportPdfServiceTest extends TestCase
{
    private ?DailyReportPdfService $service = null;
    private ShiftRepositoryInterface&Stub $shifts;
    private ShiftTypeRepositoryInterface&Stub $shiftTypes;
    private UserRepositoryInterface&Stub $users;

    protected function setUp(): void
    {
        $this->shifts     = $this->createStub(ShiftRepositoryInterface::class);
        $this->shiftTypes = $this->createStub(ShiftTypeRepositoryInterface::class);
        $this->users      = $this->createStub(UserRepositoryInterface::class);
    }

    /** Objet testé, construit à la première utilisation : un test peut d'abord remplacer un bouchon par un mock. */
    private function service(): DailyReportPdfService
    {
        if ($this->service === null) {
            $this->service = new DailyReportPdfService(
                new ViewRenderer('/nonexistent/views'),
                new TranslationService(
                    $this->createStub(TranslationRepositoryInterface::class),
                    $this->createStub(LanguageRepositoryInterface::class),
                ),
                $this->shifts,
                $this->shiftTypes,
                $this->users,
            );
        }

        return $this->service;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function report(int $id = 1, string $date = '2025-06-15'): array
    {
        return ['id' => $id, 'report_date' => $date, 'status' => 'validated'];
    }

    private function store(int $id = 1): array
    {
        return ['id' => $id, 'name' => 'Test Store'];
    }

    private function shift(int $userId, string $start, string $end, int $grossMin, int $pauseMin = 0, int $typeId = 0, ?string $deletedAt = null): array
    {
        return [
            'id'               => rand(1, 9999),
            'user_id'          => $userId,
            'shift_type_id'    => $typeId,
            'start_time'       => $start,
            'end_time'         => $end,
            'duration_minutes' => $grossMin,
            'pause_minutes'    => $pauseMin,
            'deleted_at'       => $deletedAt,
        ];
    }

    private function invokeShiftRows(array $store, array $report): array
    {
        $ref = new \ReflectionMethod(DailyReportPdfService::class, 'buildShiftRows');
        $ref->setAccessible(true);
        return $ref->invoke($this->service(), $store, $report);
    }

    private function invokeResolveLocale(array $author, array $store, ?string $override = null): string
    {
        $ref = new \ReflectionMethod(DailyReportPdfService::class, 'resolveLocale');
        $ref->setAccessible(true);
        return $ref->invoke($this->service(), $author, $store, $override);
    }

    // -------------------------------------------------------------------------
    // generateBytes() — vue inexistante → RuntimeException attrapée → null
    // -------------------------------------------------------------------------

    public function testGenerateBytesReturnsNullWhenViewNotFound(): void
    {
        $this->shifts->method('findByDate')->willReturn([]);
        $this->shiftTypes->method('findByStore')->willReturn([]);

        $result = $this->service()->generateBytes($this->report(), $this->store());
        $this->assertNull($result);
    }

    public function testGenerateBytesReturnsNullWithExplicitLocale(): void
    {
        $this->shifts->method('findByDate')->willReturn([]);
        $this->shiftTypes->method('findByStore')->willReturn([]);

        $result = $this->service()->generateBytes($this->report(), $this->store(), [], 'en');
        $this->assertNull($result);
    }

    public function testGenerateBytesReturnsNullWithJaLocale(): void
    {
        $this->shifts->method('findByDate')->willReturn([]);
        $this->shiftTypes->method('findByStore')->willReturn([]);

        $result = $this->service()->generateBytes($this->report(), $this->store(), [], 'ja');
        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // buildShiftRows — date vide
    // -------------------------------------------------------------------------

    public function testBuildShiftRowsReturnsEmptyArrayForEmptyDate(): void
    {
        $report = ['id' => 1, 'report_date' => ''];
        $rows   = $this->invokeShiftRows($this->store(), $report);
        $this->assertSame([], $rows);
    }

    public function testBuildShiftRowsDoesNotQueryRepositoriesForEmptyDate(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shifts->expects($this->never())->method('findByDate');

        $report = ['id' => 1, 'report_date' => ''];
        $this->invokeShiftRows($this->store(), $report);
    }

    // -------------------------------------------------------------------------
    // buildShiftRows — filtrage soft-delete
    // -------------------------------------------------------------------------

    public function testBuildShiftRowsExcludesSoftDeletedShifts(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '09:00:00', '17:00:00', 480, 0, 1, '2025-06-15 12:00:00'),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame([], $rows);
    }

    public function testBuildShiftRowsIncludesShiftsWithNullDeletedAt(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '09:00:00', '17:00:00', 480, 30, 1, null),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([['id' => 1, 'name' => 'Jour']]);
        $this->users->method('findById')->willReturn(['first_name' => 'Alice', 'last_name' => 'Martin', 'email' => 'a@m.com']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertCount(1, $rows);
    }

    public function testBuildShiftRowsMixedDeletedAndActiveShifts(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '08:00:00', '16:00:00', 480, 0, 1, null),
            $this->shift(20, '09:00:00', '17:00:00', 480, 0, 1, '2025-06-15 00:00:00'),
            $this->shift(30, '10:00:00', '18:00:00', 480, 0, 1, null),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([['id' => 1, 'name' => 'Standard']]);
        $this->users->method('findById')->willReturn(['first_name' => 'X', 'last_name' => 'Y', 'email' => 'x@y.com']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertCount(2, $rows);
    }

    // -------------------------------------------------------------------------
    // buildShiftRows — calcul net_min
    // -------------------------------------------------------------------------

    public function testBuildShiftRowsNetMinIsGrossMinusPause(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '09:00', '18:00', 540, 60),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);
        $this->users->method('findById')->willReturn(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.com']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame(480, $rows[0]['net_min']);
    }

    public function testBuildShiftRowsNetMinIsNeverNegative(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '09:00', '09:30', 30, 60),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);
        $this->users->method('findById')->willReturn(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.com']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame(0, $rows[0]['net_min']);
    }

    public function testBuildShiftRowsNoPauseGivesNetEqualToGross(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '08:00', '12:00', 240, 0),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);
        $this->users->method('findById')->willReturn(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.com']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame(240, $rows[0]['gross_min']);
        $this->assertSame(240, $rows[0]['net_min']);
        $this->assertSame(0, $rows[0]['pause_min']);
    }

    // -------------------------------------------------------------------------
    // buildShiftRows — résolution du nom employé
    // -------------------------------------------------------------------------

    public function testBuildShiftRowsUsesLastNameFirstName(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '09:00', '17:00', 480),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);
        $this->users->method('findById')->willReturn(['first_name' => 'Marie', 'last_name' => 'Curie', 'email' => 'm@c.com']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame('Curie Marie', $rows[0]['employee']);
    }

    public function testBuildShiftRowsFallsBackToEmailWhenNameIsEmpty(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '09:00', '17:00', 480),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);
        $this->users->method('findById')->willReturn(['first_name' => '', 'last_name' => '', 'email' => 'noname@store.com']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame('noname@store.com', $rows[0]['employee']);
    }

    public function testBuildShiftRowsFallsBackToEmailWhenNameIsWhitespace(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '09:00', '17:00', 480),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);
        $this->users->method('findById')->willReturn(['first_name' => '  ', 'last_name' => '  ', 'email' => 'ws@store.com']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame('ws@store.com', $rows[0]['employee']);
    }

    public function testBuildShiftRowsShowsDashForUnknownUser(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(99, '09:00', '17:00', 480),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);
        $this->users->method('findById')->willReturn(null);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame('—', $rows[0]['employee']);
    }

    // -------------------------------------------------------------------------
    // buildShiftRows — résolution du type de shift
    // -------------------------------------------------------------------------

    public function testBuildShiftRowsResolvesShiftTypeName(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '14:00', '22:00', 480, 0, 5),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([['id' => 5, 'name' => 'Soir']]);
        $this->users->method('findById')->willReturn(['first_name' => 'A', 'last_name' => 'B', 'email' => '']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame('Soir', $rows[0]['type']);
    }

    public function testBuildShiftRowsShowsDashForUnknownShiftType(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '09:00', '17:00', 480, 0, 999),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([['id' => 5, 'name' => 'Jour']]);
        $this->users->method('findById')->willReturn(['first_name' => 'A', 'last_name' => 'B', 'email' => '']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame('—', $rows[0]['type']);
    }

    public function testBuildShiftRowsShowsDashWhenTypeIdIsZero(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '09:00', '17:00', 480, 0, 0),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);
        $this->users->method('findById')->willReturn(['first_name' => 'A', 'last_name' => 'B', 'email' => '']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame('—', $rows[0]['type']);
    }

    // -------------------------------------------------------------------------
    // buildShiftRows — format des horaires (substr 5 chars)
    // -------------------------------------------------------------------------

    public function testBuildShiftRowsTruncatesTimesWithSeconds(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '09:00:00', '17:30:00', 510),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);
        $this->users->method('findById')->willReturn(['first_name' => 'A', 'last_name' => 'B', 'email' => '']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame('09:00', $rows[0]['start']);
        $this->assertSame('17:30', $rows[0]['end']);
    }

    public function testBuildShiftRowsHandlesAlreadyShortTimeFormat(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '08:30', '16:45', 495),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);
        $this->users->method('findById')->willReturn(['first_name' => 'A', 'last_name' => 'B', 'email' => '']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame('08:30', $rows[0]['start']);
        $this->assertSame('16:45', $rows[0]['end']);
    }

    // -------------------------------------------------------------------------
    // buildShiftRows — tri par heure de début
    // -------------------------------------------------------------------------

    public function testBuildShiftRowsSortedByStartTime(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(20, '14:00:00', '22:00:00', 480),
            $this->shift(10, '08:00:00', '16:00:00', 480),
            $this->shift(30, '10:00:00', '18:00:00', 480),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);
        $this->users->method('findById')->willReturnMap([
            [10, ['first_name' => 'Matin', 'last_name' => '', 'email' => 'a@a.com']],
            [20, ['first_name' => 'Aprem', 'last_name' => '', 'email' => 'b@b.com']],
            [30, ['first_name' => 'Midi',  'last_name' => '', 'email' => 'c@c.com']],
        ]);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame('08:00', $rows[0]['start']);
        $this->assertSame('10:00', $rows[1]['start']);
        $this->assertSame('14:00', $rows[2]['start']);
    }

    public function testBuildShiftRowsSortingIsStableForSameStartTime(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '09:00:00', '13:00:00', 240),
            $this->shift(20, '09:00:00', '17:00:00', 480),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);
        $this->users->method('findById')->willReturn(['first_name' => 'A', 'last_name' => 'B', 'email' => '']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertCount(2, $rows);
        $this->assertSame('09:00', $rows[0]['start']);
        $this->assertSame('09:00', $rows[1]['start']);
    }

    // -------------------------------------------------------------------------
    // buildShiftRows — cache utilisateur (évite les doubles appels findById)
    // -------------------------------------------------------------------------

    public function testBuildShiftRowsCachesUserLookupForSameUser(): void
    {
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '08:00', '12:00', 240),
            $this->shift(10, '13:00', '17:00', 240),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);
        $this->users->expects($this->once())
            ->method('findById')
            ->willReturn(['first_name' => 'Alice', 'last_name' => '', 'email' => 'alice@store.com']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['employee']);
        $this->assertSame('Alice', $rows[1]['employee']);
    }

    public function testBuildShiftRowsQueriesEachDistinctUserOnce(): void
    {
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '08:00', '12:00', 240),
            $this->shift(20, '09:00', '17:00', 480),
            $this->shift(10, '13:00', '17:00', 240),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([]);
        $this->users->expects($this->exactly(2))
            ->method('findById')
            ->willReturn(['first_name' => 'X', 'last_name' => '', 'email' => 'x@x.com']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertCount(3, $rows);
    }

    // -------------------------------------------------------------------------
    // buildShiftRows — structure du tableau retourné
    // -------------------------------------------------------------------------

    public function testBuildShiftRowsRowHasExpectedKeys(): void
    {
        $this->shifts->method('findByDate')->willReturn([
            $this->shift(10, '09:00', '17:00', 480, 30, 2),
        ]);
        $this->shiftTypes->method('findByStore')->willReturn([['id' => 2, 'name' => 'Journée']]);
        $this->users->method('findById')->willReturn(['first_name' => 'Jean', 'last_name' => 'Bon', 'email' => 'jb@test.com']);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $row  = $rows[0];

        $this->assertArrayHasKey('employee',  $row);
        $this->assertArrayHasKey('type',      $row);
        $this->assertArrayHasKey('start',     $row);
        $this->assertArrayHasKey('end',       $row);
        $this->assertArrayHasKey('gross_min', $row);
        $this->assertArrayHasKey('pause_min', $row);
        $this->assertArrayHasKey('net_min',   $row);
    }

    public function testBuildShiftRowsEmptyWhenNoShifts(): void
    {
        $this->shifts->method('findByDate')->willReturn([]);
        $this->shiftTypes->method('findByStore')->willReturn([]);

        $rows = $this->invokeShiftRows($this->store(), $this->report());
        $this->assertSame([], $rows);
    }

    // -------------------------------------------------------------------------
    // resolveLocale — sélection de la locale
    // -------------------------------------------------------------------------

    public function testResolveLocaleReturnsOverrideEn(): void
    {
        $this->assertSame('en', $this->invokeResolveLocale([], [], 'en'));
    }

    public function testResolveLocaleReturnsOverrideJa(): void
    {
        $this->assertSame('ja', $this->invokeResolveLocale([], [], 'ja'));
    }

    public function testResolveLocaleReturnsOverrideFr(): void
    {
        $this->assertSame('fr', $this->invokeResolveLocale([], [], 'fr'));
    }

    public function testResolveLocaleOverrideTakesPrecedenceOverAuthorLanguage(): void
    {
        $this->assertSame('fr', $this->invokeResolveLocale(['language' => 'en'], ['locale' => 'ja'], 'fr'));
    }

    public function testResolveLocaleUsesAuthorLanguageWhenNoOverride(): void
    {
        $this->assertSame('ja', $this->invokeResolveLocale(['language' => 'ja'], []));
        $this->assertSame('en', $this->invokeResolveLocale(['language' => 'en'], []));
    }

    public function testResolveLocaleUsesStoreLocaleWhenNoAuthorLanguage(): void
    {
        $this->assertSame('ja', $this->invokeResolveLocale([], ['locale' => 'ja']));
        $this->assertSame('en', $this->invokeResolveLocale([], ['locale' => 'en']));
    }

    public function testResolveLocaleDefaultsToFrenchWhenNothingSet(): void
    {
        $this->assertSame('fr', $this->invokeResolveLocale([], []));
    }

    public function testResolveLocaleDefaultsToFrenchForUnsupportedLocale(): void
    {
        $this->assertSame('fr', $this->invokeResolveLocale(['language' => 'de'], []));
        $this->assertSame('fr', $this->invokeResolveLocale([], ['locale' => 'zh']));
    }

    public function testResolveLocaleIsCaseInsensitive(): void
    {
        $this->assertSame('ja', $this->invokeResolveLocale(['language' => 'JA'], []));
        $this->assertSame('en', $this->invokeResolveLocale(['language' => 'EN'], []));
    }

    public function testResolveLocaleHandlesLongLocaleCode(): void
    {
        // 'en-US' → extrait 'en'
        $this->assertSame('en', $this->invokeResolveLocale(['language' => 'en-US'], []));
    }
}
