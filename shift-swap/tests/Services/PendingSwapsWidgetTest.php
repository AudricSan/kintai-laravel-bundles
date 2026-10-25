<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftSwap\Services;

use kintai\Bundles\Installed\ShiftSwap\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Bundles\Installed\ShiftSwap\Services\PendingSwapsWidget;
use kintai\Core\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Widgets et carte « Échanges » des tableaux de bord (contrats 1.4 et 1.19) : mêmes demandes que celles que le Core
 * affichait.
 */
final class PendingSwapsWidgetTest extends TestCase
{
    private function widget(): PendingSwapsWidget
    {
        $swaps = $this->createStub(ShiftSwapRequestRepositoryInterface::class);
        $swaps->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 1, 'status' => 'pending', 'requester_id' => 5, 'target_user_id' => 6],
            ['id' => 2, 'store_id' => 2, 'status' => 'pending', 'requester_id' => 5, 'target_user_id' => 6],
            ['id' => 3, 'store_id' => 1, 'status' => 'accepted', 'requester_id' => 5, 'target_user_id' => 6],
        ]);
        $swaps->method('findByTarget')->willReturn([
            ['id' => 4, 'status' => 'pending', 'accepted_at' => null],
            ['id' => 5, 'status' => 'pending', 'accepted_at' => '2026-10-01 10:00:00'],
            ['id' => 6, 'status' => 'refused', 'accepted_at' => null],
        ]);
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findAll')->willReturn([['id' => 5, 'display_name' => 'Sato'], ['id' => 6, 'email' => 'tanaka@example.test']]);

        return new PendingSwapsWidget($swaps, $users);
    }

    public function testManagerSeesPendingSwapsOfItsStores(): void
    {
        $data = $this->widget()->forManager([1]);

        $this->assertSame([1], array_column($data['pending_swaps'], 'id'));
        $this->assertSame([5 => 'Sato', 6 => 'tanaka@example.test'], $data['users_map']);
    }

    public function testEmployeeSeesTheRequestsWaitingForItsAnswer(): void
    {
        $this->assertSame([4], array_column($this->widget()->forEmployee(6), 'id'));
    }
}
