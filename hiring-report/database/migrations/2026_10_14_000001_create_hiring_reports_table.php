<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Table du bundle, créée si absente (installation neuve), avec le schéma qu'utilise son code. Reprise lors de
 * l'extraction (étape 4 du plan du contrat) de la migration du Core 2026_06_09_000035_create_hiring_reports_table,
 * avec les évolutions que le Core lui a appliquées depuis : furigana scindé en furigana_last_name /
 * furigana_first_name (2026_06_09_000039), colonne combinée furigana retirée (2026_09_23_000000). Sur une instance
 * existante, la table est déjà là et n'est pas touchée ; ses évolutions futures se font ici.
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if ($this->schema()->hasTable('hiring_reports')) {
            return;
        }
        $this->schema()->create('hiring_reports', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('store_id');
            $table->integer('user_id')->nullable();
            $table->string('employee_number')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('furigana_last_name')->nullable();
            $table->string('furigana_first_name')->nullable();
            $table->string('gender')->nullable();
            $table->string('tax_classification')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('education')->nullable();
            $table->string('postal_code')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile_phone')->nullable();
            $table->string('email')->nullable();
            $table->string('guarantor_name')->nullable();
            $table->string('guarantor_phone')->nullable();
            $table->string('store_name')->nullable();
            $table->string('hired_by')->nullable();
            $table->text('notes')->nullable();
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
