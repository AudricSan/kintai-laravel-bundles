<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Messaging\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\Messaging\Repositories\DatabaseMessageRepository;
use kintai\Bundles\Installed\Messaging\Services\ThreadVisibility;
use kintai\Core\Database\BundleMigrationRunner;
use PHPUnit\Framework\TestCase;

/**
 * Supprimer une conversation la masque pour soi seul (audit n°8 de Kintai, D5) — avant, n'importe quel participant
 * l'effaçait pour tous. Testé sur les vraies tables migrées.
 */
final class ThreadVisibilityTest extends TestCase
{
    private DatabaseMessageRepository $repo;
    private int $threadId;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $runner = (new \ReflectionClass(BundleMigrationRunner::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(BundleMigrationRunner::class, 'capsule'))->setValue($runner, $capsule);
        $runner->runPendingFor('messaging', dirname(__DIR__, 2) . '/database/migrations');

        $this->repo = new DatabaseMessageRepository();
        $this->threadId = (int) $this->repo->saveThread(['store_id' => 1, 'creator_id' => 3, 'subject' => 'Planning'])['id'];
        $this->repo->saveMessage(['thread_id' => $this->threadId, 'sender_id' => 3, 'body' => 'Bonjour', 'sent_at' => '2026-10-15 09:00:00']);
        $this->repo->saveParticipant(['thread_id' => $this->threadId, 'user_id' => 3, 'is_read' => 1]);
        $this->repo->saveParticipant(['thread_id' => $this->threadId, 'user_id' => 4, 'is_read' => 0]);
    }

    private function visibleFor(int $userId): bool
    {
        foreach ($this->repo->findParticipationsByUser($userId) as $p) {
            if ((int) $p['thread_id'] === $this->threadId) {
                return ThreadVisibility::isVisible($p);
            }
        }
        return false;
    }

    public function testDeletingHidesTheConversationForTheCallerOnly(): void
    {
        $this->assertFalse((new ThreadVisibility($this->repo))->hideFor($this->threadId, 3));

        $this->assertFalse($this->visibleFor(3), 'retirée de la liste du créateur');
        $this->assertTrue($this->visibleFor(4), 'toujours là pour l\'autre participant');
        $this->assertCount(1, $this->repo->findMessagesByThread($this->threadId), 'messages conservés');
        $this->assertSame(1, $this->repo->countUnread(4));
    }

    public function testANewMessageBringsItBack(): void
    {
        (new ThreadVisibility($this->repo))->hideFor($this->threadId, 3);

        $this->repo->saveParticipant(ThreadVisibility::reveal($this->repo->findParticipant($this->threadId, 3)));

        $this->assertTrue($this->visibleFor(3));
        $this->assertSame(1, $this->repo->countUnread(3));
    }

    public function testTheConversationIsErasedOnceNobodySeesIt(): void
    {
        $visibility = new ThreadVisibility($this->repo);
        $visibility->hideFor($this->threadId, 4);

        $this->assertTrue($visibility->hideFor($this->threadId, 3));
        $this->assertNull($this->repo->findThreadById($this->threadId));
        $this->assertSame([], $this->repo->findMessagesByThread($this->threadId));
    }

    public function testAHiddenUnreadConversationNoLongerCountsAsUnread(): void
    {
        (new ThreadVisibility($this->repo))->hideFor($this->threadId, 4);

        $this->assertSame(0, $this->repo->countUnread(4));
    }
}
