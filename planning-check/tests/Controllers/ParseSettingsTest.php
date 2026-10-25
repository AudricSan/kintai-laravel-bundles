<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\PlanningCheck\Controllers;

use kintai\Bundles\Installed\PlanningCheck\Controllers\Web\PlanningCheckController;
use PHPUnit\Framework\TestCase;

/** Validation des réglages saisis : heures, jours, destinataires (employés cochés et adresses d'avant la sélection). */
final class ParseSettingsTest extends TestCase
{
    private const VALID = ['enabled' => '1', 'window_start_time' => '07:00', 'deadline_time' => '11:30', 'weekdays' => ['1', '2', '3'],
        'mail_recipient_ids' => ['4', '9', '4', 'x', '0'], 'include_open_shifts' => '1'];

    private const STORED = ['mail_recipients' => ['boss@example.com', 'other@example.com']];

    public function testValidSettingsAreNormalised(): void
    {
        [$settings, $errors] = PlanningCheckController::parseSettings(self::VALID + ['legacy_recipients' => ['BOSS@example.com']], self::STORED);

        $this->assertSame([], $errors);
        $this->assertSame(['enabled' => true, 'deadline_time' => '11:30', 'window_start_time' => '07:00', 'weekdays' => [1, 2, 3],
            'mail_recipient_ids' => [4, 9], 'mail_recipients' => ['boss@example.com'], 'include_open_shifts' => true], $settings,
            'comptes dédoublonnés, identifiants invalides écartés, adresse d\'avant la sélection restée cochée conservée');
    }

    public function testAddressesCannotBeAddedOnlyRemoved(): void
    {
        [$settings] = PlanningCheckController::parseSettings(self::VALID + ['legacy_recipients' => ['new@example.com', 'other@example.com']], self::STORED);

        $this->assertSame(['other@example.com'], $settings['mail_recipients']);

        [$none] = PlanningCheckController::parseSettings(self::VALID, self::STORED);
        $this->assertSame([], $none['mail_recipients'], 'tout décocher retire les adresses héritées');
    }

    public function testUncheckedBoxesMeanFalse(): void
    {
        [$settings] = PlanningCheckController::parseSettings(['window_start_time' => '07:00', 'deadline_time' => '11:30', 'weekdays' => ['1']]);

        $this->assertFalse($settings['enabled']);
        $this->assertFalse($settings['include_open_shifts']);
        $this->assertSame([], $settings['mail_recipient_ids']);
    }

    public function testEachDefectIsReported(): void
    {
        $errors = fn(array $override): array => PlanningCheckController::parseSettings($override + self::VALID, self::STORED)[1];

        $this->assertSame(['planning_check_err_time_invalid'], $errors(['deadline_time' => '25:00']));
        $this->assertSame(['planning_check_err_window_order'], $errors(['window_start_time' => '12:00', 'deadline_time' => '12:00']));
        $this->assertSame(['planning_check_err_weekdays'], $errors(['weekdays' => ['0', '8', 'x']]));
        $this->assertSame(['planning_check_err_recipients_required'], $errors(['mail_recipient_ids' => []]));
        $this->assertSame(['planning_check_err_recipients_max'], $errors(['mail_recipient_ids' => range(1, 11)]));
    }

    public function testTheMaximumCountsEmployeesAndKeptAddressesTogether(): void
    {
        $stored = ['mail_recipients' => ['a@example.com', 'b@example.com']];
        $post = ['mail_recipient_ids' => range(1, 9), 'legacy_recipients' => ['a@example.com', 'b@example.com']] + self::VALID;

        $this->assertSame(['planning_check_err_recipients_max'], PlanningCheckController::parseSettings($post, $stored)[1]);
    }

    public function testAnAddressKeptIsEnoughToEnableTheCheck(): void
    {
        [, $errors] = PlanningCheckController::parseSettings(['mail_recipient_ids' => [], 'legacy_recipients' => ['boss@example.com']] + self::VALID, self::STORED);

        $this->assertSame([], $errors);
    }

    public function testRecipientsAreOptionalWhenTheCheckIsDisabled(): void
    {
        [, $errors] = PlanningCheckController::parseSettings(['enabled' => '0', 'window_start_time' => '07:00', 'deadline_time' => '11:30', 'weekdays' => ['1']]);

        $this->assertSame([], $errors);
    }
}
