<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use Illuminate\Database\Schema\Blueprint;
use kintai\Contract\Database\Migration;

/**
 * Destinataires choisis parmi les employés du magasin : planning_check_settings.mail_recipient_ids, liste JSON
 * d'identifiants de compte (l'adresse est relue à l'envoi, donc à jour). La colonne mail_recipients, qui garde les
 * adresses saisies auparavant, reste utilisée.
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if ($this->schema()->hasTable('planning_check_settings') && !$this->schema()->hasColumn('planning_check_settings', 'mail_recipient_ids')) {
            $this->schema()->table('planning_check_settings', function (Blueprint $table) {
                $table->text('mail_recipient_ids')->nullable();
            });
        }
    }

    public function down(): void
    {
        if ($this->schema()->hasColumn('planning_check_settings', 'mail_recipient_ids')) {
            $this->schema()->table('planning_check_settings', function (Blueprint $table) {
                $table->dropColumn('mail_recipient_ids');
            });
        }
    }
};
