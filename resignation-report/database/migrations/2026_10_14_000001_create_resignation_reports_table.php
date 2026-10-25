<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Table du bundle, créée si absente (installation neuve). Reprise de la migration du Core
 * 2026_06_09_000036_create_resignation_reports_table lors de l'extraction (étape 4 du plan du contrat) : sur une
 * instance existante, la table est déjà là et n'est pas touchée ; ses évolutions futures se font ici.
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if ($this->schema()->hasTable('resignation_reports')) {
            return;
        }
        $this->schema()->create('resignation_reports', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('store_id');
            $table->integer('user_id')->nullable();
            $table->string('employee_number')->nullable();
            $table->string('employee_name')->nullable();
            $table->date('resignation_date')->nullable();
            $table->text('reason')->nullable();
            $table->text('resignation_notice')->nullable();
            $table->text('notes')->nullable();
            $table->string('person_in_charge')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        // Pas de suppression : les tables existaient avant cette migration sur les instances mises à jour, et un retour
        // arrière ne doit pas effacer leurs données.
    }
};
