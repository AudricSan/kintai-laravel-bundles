<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftSwap\Providers;

use kintai\Bundles\Installed\ShiftSwap\Providers\SwapRequestsProvider;
use kintai\Bundles\Installed\ShiftSwap\Repositories\ShiftSwapRequestRepositoryInterface;
use kintai\Contract\Provider\Swap;
use kintai\Core\Container;
use PHPUnit\Framework\TestCase;

/**
 * Échanges fournis au Core (contrat 1.18) : demandes des magasins demandés, filtrées par date de demande.
 */
final class SwapRequestsProviderTest extends TestCase
{
    public function testSwapsOfTheStoresWithinThePeriod(): void
    {
        $repo = $this->createMock(ShiftSwapRequestRepositoryInterface::class);
        $repo->method('findByStore')->with(4)->willReturn([
            ['id' => 1, 'store_id' => 4, 'requester_id' => 7, 'target_user_id' => 8, 'created_at' => '2026-09-30 10:00:00', 'status' => 'pending'],
            ['id' => 2, 'store_id' => 4, 'requester_id' => 7, 'target_user_id' => 9, 'created_at' => '2026-10-02 10:00:00', 'status' => 'accepted'],
        ]);
        $container = new Container();
        $container->instance(ShiftSwapRequestRepositoryInterface::class, $repo);

        $swaps = (new SwapRequestsProvider($container))->swaps([4], '2026-10-01', '2026-10-31');

        $this->assertCount(1, $swaps);
        $this->assertInstanceOf(Swap::class, $swaps[0]);
        $this->assertSame([2, 4, 7, 9, 'accepted'], [$swaps[0]->id, $swaps[0]->storeId, $swaps[0]->requesterId, $swaps[0]->targetUserId, $swaps[0]->status]);
    }
}
