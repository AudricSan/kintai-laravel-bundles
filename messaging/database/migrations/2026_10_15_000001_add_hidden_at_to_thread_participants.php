<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Supprimer une conversation la masque pour soi seul (audit n°8 de Kintai, décision D5) : hidden_at note quand un
 * participant l'a retirée de sa liste. Elle n'est effacée de la base que lorsque tous ses participants l'ont masquée.
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if (!$this->schema()->hasTable('thread_participants') || $this->schema()->hasColumn('thread_participants', 'hidden_at')) {
            return;
        }
        $this->schema()->table('thread_participants', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable();
        });
    }

    public function down(): void
    {
        // Pas de suppression : la colonne porte l'état des listes de conversations.
    }
};
