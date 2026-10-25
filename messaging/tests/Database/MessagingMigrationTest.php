<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Messaging\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use kintai\Bundles\Installed\Messaging\Repositories\DatabaseMessageRepository;
use kintai\Core\Database\BundleMigrationRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Migrations du bundle, jouées par le vrai BundleMigrationRunner, sur les trois états possibles d'une base :
 * installation neuve, tables de l'ancien schéma SQL (thread_messages.sent_at, colonnes manquantes) et tables de
 * l'ancienne migration du Core (thread_messages.created_at sans sent_at). Dans les trois cas, le schéma obtenu est
 * celui qu'utilise le code : le web (sent_at) comme l'API (created_at des participants) peuvent écrire.
 */
final class MessagingMigrationTest extends TestCase
{
    private const MIGRATIONS = ['2026_10_14_000001_create_messaging_tables', '2026_10_14_000002_align_messaging_schema', '2026_10_15_000001_add_hidden_at_to_thread_participants'];

    private function migrationsPath(): string
    {
        return dirname(__DIR__, 2) . '/database/migrations';
    }

    /** @return array{0: BundleMigrationRunner, 1: Capsule} */
    private function freshRunner(): array
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $runner = (new \ReflectionClass(BundleMigrationRunner::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(BundleMigrationRunner::class, 'capsule'))->setValue($runner, $capsule);

        return [$runner, $capsule];
    }

    /** Tables telles que les a laissées l'ancien schéma SQL (relevé sur une instance réelle). */
    private static function legacySqlSchema(Capsule $capsule): void
    {
        $capsule->getConnection()->unprepared(<<<'SQL'
            CREATE TABLE message_threads (id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER NOT NULL,
                creator_id INTEGER NOT NULL, subject TEXT, created_at TEXT NOT NULL DEFAULT (datetime('now')));
            CREATE TABLE thread_participants (id INTEGER PRIMARY KEY AUTOINCREMENT, thread_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL, is_read INTEGER NOT NULL DEFAULT 0);
            CREATE TABLE thread_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, thread_id INTEGER NOT NULL,
                sender_id INTEGER NOT NULL, body TEXT NOT NULL, sent_at TEXT NOT NULL DEFAULT (datetime('now')));
            INSERT INTO message_threads (store_id, creator_id, subject, created_at) VALUES (1, 1, 'Ancien', '2026-01-01 08:00:00');
            INSERT INTO thread_messages (thread_id, sender_id, body, sent_at) VALUES (1, 1, 'Bonjour', '2026-01-01 08:00:00');
            SQL);
    }

    /** Tables telles que les créait l'ancienne migration du Core (2026_06_09_000011). */
    private static function legacyCoreMigrationSchema(Capsule $capsule): void
    {
        $capsule->getConnection()->unprepared(<<<'SQL'
            CREATE TABLE message_threads (id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER NOT NULL,
                creator_id INTEGER NOT NULL, subject VARCHAR, type VARCHAR NOT NULL DEFAULT 'direct',
                last_message_at DATETIME, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME);
            CREATE TABLE thread_participants (id INTEGER PRIMARY KEY AUTOINCREMENT, thread_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL, is_read INTEGER NOT NULL DEFAULT 0, last_read_at DATETIME,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP);
            CREATE TABLE thread_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, thread_id INTEGER NOT NULL,
                sender_id INTEGER NOT NULL, body TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP);
            INSERT INTO message_threads (store_id, creator_id, subject, created_at) VALUES (1, 1, 'Ancien', '2026-01-01 08:00:00');
            INSERT INTO thread_messages (thread_id, sender_id, body, created_at) VALUES (1, 1, 'Bonjour', '2026-01-01 08:00:00');
            SQL);
    }

    /** @return array<string, array{0: ?string}> */
    public static function startingPoints(): array
    {
        return [
            'installation neuve'                  => [null],
            'ancien schéma SQL'                   => ['legacySqlSchema'],
            'ancienne migration du Core'          => ['legacyCoreMigrationSchema'],
        ];
    }

    #[DataProvider('startingPoints')]
    public function testEveryStartingPointEndsWithTheSchemaTheCodeUses(?string $existing): void
    {
        [$runner, $capsule] = $this->freshRunner();
        if ($existing !== null) {
            self::$existing($capsule);
        }

        $this->assertSame(self::MIGRATIONS, $runner->runPendingFor('messaging', $this->migrationsPath()));

        $schema = $capsule->getConnection()->getSchemaBuilder();
        foreach (['id', 'store_id', 'creator_id', 'subject', 'type', 'last_message_at', 'created_at', 'updated_at'] as $c) {
            $this->assertTrue($schema->hasColumn('message_threads', $c), "message_threads.{$c}");
        }
        foreach (['id', 'thread_id', 'user_id', 'is_read', 'last_read_at', 'created_at', 'hidden_at'] as $c) {
            $this->assertTrue($schema->hasColumn('thread_participants', $c), "thread_participants.{$c}");
        }
        foreach (['id', 'thread_id', 'sender_id', 'body', 'sent_at'] as $c) {
            $this->assertTrue($schema->hasColumn('thread_messages', $c), "thread_messages.{$c}");
        }

        // Écritures du web (sent_at, participant sans date) et de l'API (participant avec created_at).
        $repo = new DatabaseMessageRepository();
        $thread = $repo->saveThread(['store_id' => 1, 'creator_id' => 1, 'subject' => 'Planning', 'created_at' => '2026-10-14 09:00:00']);
        $repo->saveParticipant(['thread_id' => $thread['id'], 'user_id' => 2, 'is_read' => 0]);
        $repo->saveParticipant(['thread_id' => $thread['id'], 'user_id' => 3, 'is_read' => 0, 'created_at' => '2026-10-14 09:00:00']);
        $repo->saveMessage(['thread_id' => $thread['id'], 'sender_id' => 1, 'body' => 'Bonjour', 'sent_at' => '2026-10-14 09:00:00']);

        $this->assertSame(1, $repo->countUnread(2));
        $this->assertSame('2026-10-14 09:00:00', $repo->findMessagesByThread((int) $thread['id'])[0]['sent_at']);
        if ($existing !== null) {
            $this->assertSame('2026-01-01 08:00:00', $repo->findMessagesByThread(1)[0]['sent_at'], 'message existant : date d\'envoi conservée');
        }
        $this->assertSame([], $runner->runPendingFor('messaging', $this->migrationsPath()), 'rejouées : rien');
    }
}
