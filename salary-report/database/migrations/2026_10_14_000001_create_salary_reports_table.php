<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Table du bundle, créée si absente (installation neuve), avec le schéma qu'utilise son code. Reprise lors de
 * l'extraction (étape 4 du plan du contrat) de la migration du Core 2026_06_09_000037_create_salary_reports_table,
 * avec les évolutions que le Core lui a appliquées depuis : rapport par employé (user_id, employee_name, unicité
 * magasin + mois + employé, 2026_07_15_000001) et salaire versé par virement (bank_transfer_salary,
 * 2026_07_17_000001). Sur une instance existante, la table est déjà là et n'est pas touchée ; ses évolutions futures
 * se font ici.
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if ($this->schema()->hasTable('salary_reports')) {
            return;
        }
        $this->schema()->create('salary_reports', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('store_id');
            // Rapport d'un seul employé ; null = rapport global du magasin.
            $table->integer('user_id')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('target_month', 7);
            $table->string('store_name')->nullable();
            $table->string('person_in_charge')->nullable();
            $table->decimal('total_payment', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('income_tax_base', 12, 2)->default(0);
            $table->decimal('withholding_tax', 12, 2)->default(0);
            $table->decimal('residence_tax', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('net_payment', 12, 2)->default(0);
            $table->integer('active_employees')->default(0);
            $table->decimal('hand_delivered_salary', 12, 2)->default(0);
            $table->decimal('bank_transfer_salary', 12, 2)->default(0);
            $table->decimal('staff_man_hours', 10, 2)->default(0);
            $table->decimal('staff_total_payment', 12, 2)->default(0);
            $table->decimal('staff_avg_hourly_wage', 10, 2)->default(0);
            $table->text('employee_work_hours')->nullable();
            $table->integer('new_hires')->default(0);
            $table->integer('resigned_staff')->default(0);
            $table->text('hire_registrations')->nullable();
            $table->text('remarks')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            // Un null n'étant jamais égal à un autre, l'unicité du rapport global par mois est vérifiée par le contrôleur.
            $table->unique(['store_id', 'target_month', 'user_id']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        // Pas de suppression : les tables existaient avant cette migration sur les instances mises à jour, et un retour
        // arrière ne doit pas effacer leurs données.
    }
};
