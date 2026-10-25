<?php
declare(strict_types=1);

namespace kintai\Tests\Bundles\DailyReport\Repositories;

use PHPUnit\Framework\TestCase;
use kintai\Bundles\Installed\DailyReport\Repositories\DatabaseDailyReportRepository;
use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\DailyReport\Models\DailyReport as EloquentDailyReport;

final class DatabaseDailyReportRepositoryTest extends TestCase
{
    private DatabaseDailyReportRepository $repo;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $capsule->getConnection()->getSchemaBuilder()->create('daily_reports', function ($table) {
            $table->increments('id');
            $table->integer('store_id');
            $table->integer('author_id');
            $table->string('report_date');
            $table->decimal('sales_total', 12, 2)->default(0);
            $table->integer('customer_count')->default(0);
            $table->decimal('labor_cost', 12, 2)->default(0);
            $table->decimal('waste_total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('data')->nullable();
            $table->string('status')->default('draft');
            $table->integer('is_finalized')->default(0);
            $table->integer('finalized_by')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->integer('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('pdf_generated_at')->nullable();
            $table->timestamp('mail_sent_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });

        $this->repo = new DatabaseDailyReportRepository();
    }

    private function report(int $storeId = 1, string $date = '2025-06-15', string $status = 'draft', ?string $deletedAt = null, int $authorId = 10): array
    {
        return [
            'store_id'   => $storeId,
            'report_date'=> $date,
            'status'     => $status,
            'deleted_at' => $deletedAt,
            'author_id'  => $authorId,
        ];
    }

    public function testFindByIdReturnsReport(): void
    {
        $r = EloquentDailyReport::create($this->report());
        $found = $this->repo->findById($r->id);
        $this->assertNotNull($found);
    }

    public function testFindByIdReturnsNullForDeletedReport(): void
    {
        $r = EloquentDailyReport::create($this->report(deletedAt: '2025-01-01 00:00:00'));
        $this->assertNull($this->repo->findById($r->id));
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repo->findById(999));
    }

    public function testFindByStoreReturnsSortedByDateDesc(): void
    {
        EloquentDailyReport::create($this->report(1, '2025-06-10'));
        EloquentDailyReport::create($this->report(1, '2025-06-15'));
        EloquentDailyReport::create($this->report(1, '2025-06-12'));

        $result = $this->repo->findByStore(1);
        $dates = array_column($result, 'report_date');
        $this->assertSame(['2025-06-15', '2025-06-12', '2025-06-10'], $dates);
    }

    public function testFindByStoreExcludesDeleted(): void
    {
        EloquentDailyReport::create($this->report(1, '2025-06-10', deletedAt: '2025-06-11 00:00:00'));
        $this->assertSame([], $this->repo->findByStore(1));
    }

    public function testFindByStoreAndDateReturnsReport(): void
    {
        EloquentDailyReport::create($this->report(1, '2025-06-15'));
        $found = $this->repo->findByStoreAndDate(1, '2025-06-15');
        $this->assertNotNull($found);
    }

    public function testFindByStoreAndDateReturnsNullForDeleted(): void
    {
        EloquentDailyReport::create($this->report(1, '2025-06-15', deletedAt: '2025-06-16 00:00:00'));
        $this->assertNull($this->repo->findByStoreAndDate(1, '2025-06-15'));
    }

    public function testFindByAuthorReturnsSortedDesc(): void
    {
        EloquentDailyReport::create($this->report(1, '2025-06-10', authorId: 5));
        EloquentDailyReport::create($this->report(1, '2025-06-15', authorId: 5));

        $result = $this->repo->findByAuthor(5);
        $this->assertSame('2025-06-15', $result[0]['report_date']);
    }

    public function testFindByStoreAndStatusFiltersCorrectly(): void
    {
        EloquentDailyReport::create($this->report(1, '2025-06-10', 'submitted'));
        EloquentDailyReport::create($this->report(1, '2025-06-15', 'draft'));

        $result = $this->repo->findByStoreAndStatus(1, 'submitted');
        $this->assertCount(1, $result);
    }

    public function testFindByStoreAndDateRangeFiltersCorrectly(): void
    {
        EloquentDailyReport::create($this->report(1, '2025-06-05'));
        EloquentDailyReport::create($this->report(1, '2025-06-15'));
        EloquentDailyReport::create($this->report(1, '2025-07-01'));

        $result = $this->repo->findByStoreAndDateRange(1, '2025-06-01', '2025-06-30');
        $this->assertCount(2, $result);
    }

    public function testSaveCreatesReport(): void
    {
        $result = $this->repo->save($this->report());
        $this->assertArrayHasKey('id', $result);
        $this->assertCount(1, EloquentDailyReport::all());
    }

    public function testDeleteDeletesReport(): void
    {
        $r = EloquentDailyReport::create($this->report());
        $result = $this->repo->delete($r->id);
        $this->assertSame(1, $result);
        $this->assertNull(EloquentDailyReport::find($r->id));
    }

    public function testDeleteReturnsZeroWhenNotFound(): void
    {
        $this->assertSame(0, $this->repo->delete(999));
    }
}
