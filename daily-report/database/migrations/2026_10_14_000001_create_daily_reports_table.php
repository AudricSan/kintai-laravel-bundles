<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Table du bundle, créée si absente (installation neuve), avec le schéma qu'utilise son code. Reprise lors de
 * l'extraction (étape 4 du plan du contrat) de la migration du Core 2026_06_09_000021_create_daily_reports_table,
 * sans les colonnes que le Core a retirées depuis (2026_09_23_000000 : is_finalized, finalized_by, finalized_at,
 * remplacées par status/validated_by/validated_at ; pdf_path, pdf_generated_at, le PDF étant généré à la volée). Sur une
 * instance existante, la table est déjà là et n'est pas touchée ; ses évolutions futures se font ici.
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if ($this->schema()->hasTable('daily_reports')) {
            return;
        }
        $this->schema()->create('daily_reports', function (Blueprint $table) {
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
            $table->timestamp('submitted_at')->nullable();
            $table->integer('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('mail_sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();

            $table->unique(['store_id', 'report_date']);
            $table->index(['store_id', 'report_date', 'deleted_at']);
            $table->index(['author_id', 'report_date']);
            $table->index(['store_id', 'status', 'report_date']);
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('validated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Pas de suppression : les tables existaient avant cette migration sur les instances mises à jour, et un retour
        // arrière ne doit pas effacer leurs données.
    }
};
