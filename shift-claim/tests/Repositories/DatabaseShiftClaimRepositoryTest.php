<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftClaim\Repositories;

use PHPUnit\Framework\TestCase;
use kintai\Bundles\Installed\ShiftClaim\Repositories\DatabaseShiftClaimRepository;
use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\ShiftClaim\Models\ShiftClaim as EloquentShiftClaim;

/**
 * Régression : approveShiftClaim() approuvait/rejetait des candidatures via de
 * simples read-then-write (findById() puis save()), vulnérable à une race
 * condition si deux admins résolvent deux candidatures du même shift en même
 * temps. approveIfPending() porte désormais la condition "status = pending"
 * dans l'UPDATE lui-même — voir AdminShiftClaimControllerTest pour le contrôleur. Repris du Core à l'extraction.
 */
final class DatabaseShiftClaimRepositoryTest extends TestCase
{
    private DatabaseShiftClaimRepository $repo;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $capsule->getConnection()->getSchemaBuilder()->create('shift_claims', function ($table) {
            $table->increments('id');
            $table->integer('shift_id');
            $table->integer('user_id');
            $table->integer('store_id');
            $table->string('status')->default('pending');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->integer('resolved_by')->nullable();
        });

        $this->repo = new DatabaseShiftClaimRepository();
    }

    public function testApproveIfPendingApprovesAndReturnsUpdatedClaim(): void
    {
        $c = EloquentShiftClaim::create(['shift_id' => 1, 'user_id' => 9, 'store_id' => 5, 'status' => 'pending']);

        $result = $this->repo->approveIfPending($c->id, '2026-09-15 10:00:00', 1);

        $this->assertNotNull($result);
        $this->assertSame('approved', $result['status']);
        $this->assertSame('2026-09-15 10:00:00', $result['resolved_at']);
        $this->assertSame(1, $result['resolved_by']);
    }

    public function testApproveIfPendingReturnsNullWhenAlreadyResolved(): void
    {
        $c = EloquentShiftClaim::create(['shift_id' => 1, 'user_id' => 9, 'store_id' => 5, 'status' => 'rejected']);

        $result = $this->repo->approveIfPending($c->id, '2026-09-15 10:00:00', 1);

        $this->assertNull($result);
        $this->assertSame('rejected', EloquentShiftClaim::find($c->id)->status);
    }

    public function testApproveIfPendingReturnsNullWhenClaimNotFound(): void
    {
        $this->assertNull($this->repo->approveIfPending(999, '2026-09-15 10:00:00', 1));
    }
}
