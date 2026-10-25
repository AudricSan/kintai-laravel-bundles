<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Notebook\Services;

use kintai\Bundles\Installed\Notebook\Repositories\NotebookEntryRepositoryInterface;
use kintai\Bundles\Installed\Notebook\Services\TeamNotesWidget;
use kintai\Contract\Data\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Données du widget « Notes d'équipe » (repris du Core à l'extraction) : notes expirées écartées, épinglées d'abord
 * puis les plus récentes, cinq au plus, nom de l'auteur et du magasin.
 */
final class TeamNotesWidgetTest extends TestCase
{
    private function note(int $id, string $createdAt, bool $pinned = false, ?string $expiresAt = null, ?int $storeId = null, ?int $authorId = 1): array
    {
        return ['id' => $id, 'author_id' => $authorId, 'store_id' => $storeId, 'content' => "note {$id}",
            'pinned' => $pinned ? 1 : 0, 'expires_at' => $expiresAt, 'created_at' => $createdAt];
    }

    public function testEntriesAreFilteredSortedLimitedAndNamed(): void
    {
        $notes = $this->createMock(NotebookEntryRepositoryInterface::class);
        $notes->expects($this->once())->method('findVisibleForStores')->with([3, 4])->willReturn([
            $this->note(1, '2026-10-01 10:00:00'),
            $this->note(2, '2026-10-02 10:00:00', expiresAt: '2026-10-05 00:00:00'),
            $this->note(3, '2026-09-01 10:00:00', pinned: true, storeId: 3),
            $this->note(4, '2026-10-03 10:00:00'),
            $this->note(5, '2026-10-04 10:00:00'),
            $this->note(6, '2026-10-05 10:00:00'),
            $this->note(7, '2026-10-06 10:00:00', authorId: null),
            $this->note(8, '2026-10-07 10:00:00', expiresAt: '2026-11-01 00:00:00', authorId: 2),
        ]);
        $users = $this->createStub(UserRepositoryInterface::class);
        $users->method('findById')->willReturnMap([
            [1, ['id' => 1, 'last_name' => 'Tanaka', 'first_name' => 'Yui']],
            [2, ['id' => 2, 'last_name' => '', 'first_name' => '', 'email' => 'x@kintai.test']],
        ]);

        $entries = (new TeamNotesWidget($notes, $users))->entries([3, 4], [3 => 'Shibuya'], '2026-10-10 00:00:00');

        $this->assertSame([3, 8, 7, 6, 5], array_column($entries, 'id'), 'épinglée, puis récentes ; expirée écartée ; 5 au plus');
        $this->assertSame('Tanaka Yui', $entries[0]['author_name']);
        $this->assertSame('Shibuya', $entries[0]['store_name']);
        $this->assertSame('x@kintai.test', $entries[1]['author_name'], 'sans nom : e-mail');
        $this->assertNull($entries[2]['author_name'], 'sans auteur');
        $this->assertNull($entries[1]['store_name'], 'toute l\'organisation');
    }
}
