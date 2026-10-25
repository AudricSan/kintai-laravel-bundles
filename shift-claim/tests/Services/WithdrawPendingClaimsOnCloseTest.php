<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftClaim\Services;

use kintai\Bundles\Installed\ShiftClaim\Repositories\ShiftClaimRepositoryInterface;
use kintai\Bundles\Installed\ShiftClaim\Services\WithdrawPendingClaimsOnClose;
use kintai\Contract\Event\OpenShiftClosed;
use PHPUnit\Framework\TestCase;

/**
 * Shift retiré de la bourse par le Core (OpenShiftClosed, contrat 1.17) : seules les candidatures en attente passent à
 * « retirée ».
 */
final class WithdrawPendingClaimsOnCloseTest extends TestCase
{
    public function testOnlyPendingClaimsAreWithdrawn(): void
    {
        $saved = [];
        $repo = $this->createMock(ShiftClaimRepositoryInterface::class);
        $repo->method('findByShift')->with(10)->willReturn([
            ['id' => 1, 'shift_id' => 10, 'user_id' => 5, 'status' => 'pending'],
            ['id' => 2, 'shift_id' => 10, 'user_id' => 6, 'status' => 'approved'],
            ['id' => 3, 'shift_id' => 10, 'user_id' => 7, 'status' => 'pending'],
        ]);
        $repo->method('save')->willReturnCallback(function (array $d) use (&$saved): array {
            $saved[] = $d;
            return $d;
        });

        (new WithdrawPendingClaimsOnClose($repo))(new OpenShiftClosed(['id' => 10, 'is_open' => 0], 1));

        $this->assertSame([1, 3], array_column($saved, 'id'));
        $this->assertSame(['withdrawn', 'withdrawn'], array_column($saved, 'status'));
    }
}
