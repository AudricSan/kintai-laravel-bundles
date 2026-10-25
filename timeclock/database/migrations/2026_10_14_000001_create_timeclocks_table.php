<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Table du bundle, créée si absente (installation neuve). Reprise des migrations du Core 2026_06_09_000007 (table) et
 * 2026_09_30_000001 (index magasin + jour) lors de l'extraction (étape 4 du plan du contrat). Une table existante
 * n'est pas touchée ici : la migration suivante (2026_10_14_000002) complète celles de l'ancien schéma SQL.
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if ($this->schema()->hasTable('timeclocks')) {
            return;
        }
        $this->schema()->create('timeclocks', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('store_id');
            $table->integer('shift_id')->nullable();
            $table->string('shift_date');
            $table->string('clock_in_time');
            $table->string('clock_out_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('clock_in_ip')->nullable();
            $table->string('clock_out_ip')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['user_id', 'store_id']);
            $table->index(['store_id', 'shift_date'], 'timeclocks_store_id_shift_date_index');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Pas de suppression : les tables existaient avant cette migration sur les instances mises à jour, et un retour
        // arrière ne doit pas effacer leurs données.
    }
};
