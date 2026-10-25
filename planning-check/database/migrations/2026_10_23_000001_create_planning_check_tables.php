<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;
use kintai\Contract\Database\Migration;

/**
 * Tables du bundle, créées si absentes avec le schéma qu'utilise son code.
 *
 * - planning_check_settings : réglages par magasin (heure limite, début d'affichage, jours, destinataires, contenu du PDF) ;
 * - planning_checks : une validation par magasin et par jour visé, avec son état (pending → submitted → validated, ou
 *   rejected / escalated), l'envoi du PDF et l'empreinte des shifts au moment de l'envoi ;
 * - planning_check_sends : historique des envois (le premier envoi et chaque renvoi d'une version mise à jour).
 *
 * Pas de clés étrangères : SQLite n'applique pas les suppressions en cascade ici, et l'historique doit survivre à la
 * suppression d'un compte (les identifiants d'utilisateur ne sont que des références).
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if (!$this->schema()->hasTable('planning_check_settings')) {
            $this->schema()->create('planning_check_settings', function (Blueprint $table) {
                $table->unsignedInteger('store_id')->primary();
                $table->boolean('enabled')->default(false);
                $table->string('deadline_time', 5)->default('12:00');
                $table->string('window_start_time', 5)->default('06:00');
                $table->string('weekdays', 20)->default('1,2,3,4,5,6,7');
                $table->text('mail_recipients')->nullable();
                $table->boolean('include_open_shifts')->default(false);
                $table->timestamp('updated_at')->nullable();
            });
        }

        if (!$this->schema()->hasTable('planning_checks')) {
            $this->schema()->create('planning_checks', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('store_id');
                $table->string('target_date', 10);
                $table->string('status', 20)->default('pending');
                $table->boolean('confirmed_empty')->default(false);
                $table->unsignedInteger('submitted_by')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedInteger('validated_by')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->unsignedInteger('rejected_by')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->text('reject_reason')->nullable();
                $table->string('send_status', 10)->default('');
                $table->timestamp('sent_at')->nullable();
                $table->text('last_error')->nullable();
                $table->string('shifts_hash', 64)->default('');
                $table->unsignedInteger('editing_by')->nullable();
                $table->timestamp('editing_until')->nullable();
                $table->timestamp('escalated_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();
                $table->unique(['store_id', 'target_date']);
                $table->index(['store_id', 'status']);
            });
        }

        if (!$this->schema()->hasTable('planning_check_sends')) {
            $this->schema()->create('planning_check_sends', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('check_id')->index();
                $table->unsignedInteger('sent_by')->nullable();
                $table->string('status', 10);
                $table->string('shifts_hash', 64)->default('');
                $table->text('recipients')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        // Pas de suppression : un retour arrière ne doit pas effacer l'historique des validations.
    }
};
