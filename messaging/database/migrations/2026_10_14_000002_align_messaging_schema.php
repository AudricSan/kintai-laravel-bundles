<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Aligne les tables de messagerie déjà existantes sur le schéma de 2026_10_14_000001 (celui qu'utilise le code).
 *
 * Deux variantes existent, selon l'âge de l'instance :
 * - tables créées par l'ancien schéma SQL : thread_messages.sent_at, mais ni type / last_message_at / updated_at sur
 *   message_threads, ni last_read_at / created_at sur thread_participants (l'API, qui écrit created_at, échouait) ;
 * - tables créées par l'ancienne migration du Core : thread_messages.created_at sans sent_at (l'envoi d'un message
 *   depuis le web échouait).
 *
 * Seules les colonnes manquantes sont ajoutées, et sent_at reprend la valeur de created_at pour les messages déjà
 * envoyés. Rien n'est supprimé ni renommé. Sur une installation neuve, il n'y a rien à faire.
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        $schema = $this->schema();

        if ($schema->hasTable('message_threads')) {
            $this->addMissing('message_threads', [
                'type'            => fn(Blueprint $t) => $t->string('type')->default('direct'),
                'last_message_at' => fn(Blueprint $t) => $t->timestamp('last_message_at')->nullable(),
                'updated_at'      => fn(Blueprint $t) => $t->timestamp('updated_at')->nullable(),
            ]);
        }

        if ($schema->hasTable('thread_participants')) {
            $this->addMissing('thread_participants', [
                'last_read_at' => fn(Blueprint $t) => $t->timestamp('last_read_at')->nullable(),
                'created_at'   => fn(Blueprint $t) => $t->timestamp('created_at')->nullable(),
            ]);
        }

        if ($schema->hasTable('thread_messages') && !$schema->hasColumn('thread_messages', 'sent_at')) {
            $this->addMissing('thread_messages', [
                'sent_at' => fn(Blueprint $t) => $t->timestamp('sent_at')->nullable(),
            ]);
            if ($schema->hasColumn('thread_messages', 'created_at')) {
                $this->capsule->getConnection()->table('thread_messages')->whereNull('sent_at')
                    ->update(['sent_at' => $this->capsule->getConnection()->raw('created_at')]);
            }
        }
    }

    public function down(): void
    {
        // Pas de retour arrière : les colonnes ajoutées ne gênent pas l'ancien code et peuvent porter des données.
    }

    /** @param array<string, \Closure(Blueprint): mixed> $columns */
    private function addMissing(string $table, array $columns): void
    {
        foreach ($columns as $name => $define) {
            if (!$this->schema()->hasColumn($table, $name)) {
                $this->schema()->table($table, fn(Blueprint $t) => $define($t));
            }
        }
    }
};
