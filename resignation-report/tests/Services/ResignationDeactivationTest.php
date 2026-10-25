<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ResignationReport\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\ResignationReport\Cron\ResignationDeactivateJob;
use kintai\Bundles\Installed\ResignationReport\Repositories\DatabaseResignationReportRepository;
use kintai\Bundles\Installed\ResignationReport\Services\ResignationDeactivation;
use kintai\Core\Database\BundleMigrationRunner;
use kintai\Core\Repositories\UserRepositoryInterface;
use kintai\Core\Request;
use kintai\Core\Services\AuditLogger;
use PHPUnit\Framework\TestCase;

/**
 * Désactivation à la date de démission (audit n°8 de Kintai, D2), sur la vraie table migrée : la tâche planifiée
 * n'applique que les rapports échus et une seule fois ; un rapport repoussé dans le futur rend l'accès.
 */
final class ResignationDeactivationTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $accounts = [];
    private DatabaseResignationReportRepository $reports;
    private ResignationDeactivation $deactivation;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $runner = (new \ReflectionClass(BundleMigrationRunner::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(BundleMigrationRunner::class, 'capsule'))->setValue($runner, $capsule);
        $runner->runPendingFor('resignation-report', dirname(__DIR__, 2) . '/database/migrations');

        $this->accounts = [5 => ['id' => 5, 'is_active' => 1], 6 => ['id' => 6, 'is_active' => 1], 7 => ['id' => 7, 'is_active' => 1]];
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findById')->willReturnCallback(fn(int $id) => $this->accounts[$id] ?? null);
        $users->method('save')->willReturnCallback(function (array $u) {
            $this->accounts[(int) $u['id']] = $u;
            return $u;
        });

        $this->reports = new DatabaseResignationReportRepository();
        $this->deactivation = new ResignationDeactivation($this->reports, $users);
    }

    public function testTheDailyJobOnlyAppliesDueReportsAndOnlyOnce(): void
    {
        $this->reports->save(['store_id' => 1, 'user_id' => 5, 'resignation_date' => '2026-10-10']);
        $this->reports->save(['store_id' => 1, 'user_id' => 6, 'resignation_date' => '2026-11-30']);
        $this->reports->save(['store_id' => 1, 'user_id' => null, 'resignation_date' => '2026-10-01']);

        $this->assertCount(1, $this->deactivation->applyDue('2026-10-15'));
        $this->assertSame(0, $this->accounts[5]['is_active']);
        $this->assertSame(1, $this->accounts[6]['is_active'], 'préavis en cours : compte encore actif');

        // Réactivé à la main : la tâche ne le désactive pas une seconde fois.
        $this->accounts[5]['is_active'] = 1;
        $this->assertSame([], $this->deactivation->applyDue('2026-10-16'));
        $this->assertSame(1, $this->accounts[5]['is_active']);

        // Le jour venu, le second l'est.
        $this->assertCount(1, $this->deactivation->applyDue('2026-11-30'));
        $this->assertSame(0, $this->accounts[6]['is_active']);
    }

    public function testAReportPushedBackToTheFutureGivesAccessBack(): void
    {
        $report = $this->reports->save(['store_id' => 1, 'user_id' => 7, 'resignation_date' => '2026-10-01']);
        $this->assertSame('deactivated', $this->deactivation->sync($report, '2026-10-15'));

        $moved = $this->reports->save(['id' => (int) $report['id'], 'resignation_date' => '2026-12-31']);
        $this->assertSame('reactivated', $this->deactivation->sync($moved, '2026-10-15'));
        $this->assertSame(1, $this->accounts[7]['is_active']);
        $this->assertNull($this->reports->findById((int) $report['id'])['deactivated_at']);
    }

    public function testTheCronJobReportsWhatItDid(): void
    {
        $this->reports->save(['store_id' => 1, 'user_id' => 5, 'resignation_date' => date('Y-m-d', strtotime('-1 day'))]);
        $job = new ResignationDeactivateJob($this->deactivation, new AuditLogger());

        $this->assertSame('resignation-deactivate', $job->getName());
        $body = json_decode($job->run(new Request())->body(), true);
        $this->assertSame(1, $body['deactivated']);
    }
}
