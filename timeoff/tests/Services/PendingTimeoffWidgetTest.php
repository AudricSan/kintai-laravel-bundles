<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\TimeOff\Services;

use kintai\Bundles\Installed\TimeOff\Repositories\TimeoffRequestRepositoryInterface;
use kintai\Bundles\Installed\TimeOff\Services\PendingTimeoffWidget;
use kintai\Core\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Widgets et carte « Congés en attente » des tableaux de bord (contrats 1.4 et 1.19) : mêmes demandes que celles que
 * le Core affichait.
 */
final class PendingTimeoffWidgetTest extends TestCase
{
    public function testManagerSeesPendingRequestsOfItsStores(): void
    {
        $timeoff = $this->createStub(TimeoffRequestRepositoryInterface::class);
        $timeoff->method('findAll')->willReturn([
            ['id' => 1, 'store_id' => 1, 'user_id' => 5, 'status' => 'pending'],
            ['id' => 2, 'store_id' => 2, 'user_id' => 5, 'status' => 'pending'],
            ['id' => 3, 'store_id' => 1, 'user_id' => 6, 'status' => 'approved'],
        ]);
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findAll')->willReturn([['id' => 5, 'display_name' => 'Sato'], ['id' => 6, 'email' => 'tanaka@example.test']]);

        $data = (new PendingTimeoffWidget($timeoff, $users))->forManager([1]);

        $this->assertSame([1], array_column($data['pending_timeoff'], 'id'));
        $this->assertSame([5 => 'Sato', 6 => 'tanaka@example.test'], $data['users_map']);
    }

    public function testEmployeeSeesItsPendingRequests(): void
    {
        $timeoff = $this->createStub(TimeoffRequestRepositoryInterface::class);
        $timeoff->method('findByUser')->willReturn([
            ['id' => 4, 'status' => 'pending'],
            ['id' => 5, 'status' => 'refused'],
        ]);

        $this->assertSame([4], array_column((new PendingTimeoffWidget($timeoff, $this->createStub(UserRepositoryInterface::class)))->forEmployee(6), 'id'));
    }
}
