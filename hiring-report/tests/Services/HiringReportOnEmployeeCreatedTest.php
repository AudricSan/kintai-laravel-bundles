<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\HiringReport\Services;

use kintai\Bundles\Installed\HiringReport\Repositories\HiringReportRepositoryInterface;
use kintai\Bundles\Installed\HiringReport\Services\HiringReportOnEmployeeCreated;
use kintai\Contract\Event\EmployeeCreated;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Rapport d'embauche créé à la création d'un employé (EmployeeCreated, contrat 1.13) : rempli depuis la fiche de
 * l'employé, avec la date d'embauche du jour, quelle que soit l'origine (formulaire ou import Excel).
 */
final class HiringReportOnEmployeeCreatedTest extends TestCase
{
    private const USER = [
        'id' => 10, 'display_name' => 'Sato Hanako', 'employee_code' => 'E042', 'furigana_last_name' => 'サトウ',
        'furigana_first_name' => 'ハナコ', 'gender' => 'female', 'tax_classification' => 'kou', 'birth_date' => '2000-04-01',
        'education' => 'Université', 'postal_code' => '150-0001', 'address' => 'Tokyo', 'phone' => '0312345678',
        'mobile_phone' => '', 'email' => 'sato@example.test', 'guarantor_name' => 'Sato Taro', 'guarantor_phone' => null,
        'color' => '#3B82F6',
    ];

    /** @return array{0: list<array>, 1: HiringReportRepositoryInterface} */
    private function recorder(): array
    {
        $saved = new \ArrayObject();
        $repo = $this->createStub(HiringReportRepositoryInterface::class);
        $repo->method('save')->willReturnCallback(function (array $d) use ($saved): array {
            $saved[] = $d;
            return $d + ['id' => 1];
        });
        return [$saved, $repo];
    }

    /** @return array<string, array{0: string}> */
    public static function sources(): array
    {
        return ['formulaire' => ['form'], 'import Excel' => ['excel_import']];
    }

    #[DataProvider('sources')]
    public function testReportIsFilledFromTheEmployeeRecord(string $source): void
    {
        [$saved, $repo] = $this->recorder();

        (new HiringReportOnEmployeeCreated($repo))(new EmployeeCreated(self::USER + ['password_hash' => 'x'], 3, 1, $source));

        $this->assertCount(1, $saved);
        $this->assertSame([
            'store_id'            => 3,
            'user_id'             => 10,
            'employee_number'     => 'E042',
            'employee_name'       => 'Sato Hanako',
            'hire_date'           => date('Y-m-d'),
            'store_name'          => null,
            'hired_by'            => 'Sato Hanako',
            'created_by'          => 1,
            'furigana_last_name'  => 'サトウ',
            'furigana_first_name' => 'ハナコ',
            'gender'              => 'female',
            'tax_classification'  => 'kou',
            'birth_date'          => '2000-04-01',
            'education'           => 'Université',
            'postal_code'         => '150-0001',
            'address'             => 'Tokyo',
            'phone'               => '0312345678',
            'mobile_phone'        => null,
            'email'               => 'sato@example.test',
            'guarantor_name'      => 'Sato Taro',
            'guarantor_phone'     => null,
        ], $saved[0]);
    }

    /** Sans magasin (API), pas de rapport : il en faut un par magasin. */
    public function testEmployeeWithoutStoreGetsNoReport(): void
    {
        [$saved, $repo] = $this->recorder();

        (new HiringReportOnEmployeeCreated($repo))(new EmployeeCreated(self::USER, null, 1, 'api'));

        $this->assertCount(0, $saved);
    }

    public function testMissingCodeAndAuthorAreStoredAsBefore(): void
    {
        [$saved, $repo] = $this->recorder();

        (new HiringReportOnEmployeeCreated($repo))(new EmployeeCreated(['employee_code' => null] + self::USER, 3, null, 'form'));

        $this->assertNull($saved[0]['employee_number']);
        $this->assertSame(0, $saved[0]['created_by']);
    }
}
