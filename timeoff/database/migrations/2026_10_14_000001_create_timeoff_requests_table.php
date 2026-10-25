<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Table du bundle, créée si absente (installation neuve), avec le schéma qu'utilise son code (processed_by,
 * processed_at, admin_note). Reprise de la migration du Core 2026_06_09_000008 lors de l'extraction (étape 4 du plan
 * du contrat). Une table existante n'est pas touchée ici : la migration suivante (2026_10_14_000002) aligne celles
 * créées par l'ancien schéma SQL.
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if ($this->schema()->hasTable('timeoff_requests')) {
            return;
        }
        $this->schema()->create('timeoff_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('store_id');
            $table->string('start_date');
            $table->string('end_date');
            $table->string('type')->default('vacation');
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->text('admin_note')->nullable();
            $table->integer('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['user_id', 'store_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Pas de suppression : les tables existaient avant cette migration sur les instances mises à jour, et un retour
        // arrière ne doit pas effacer leurs données.
    }
};
