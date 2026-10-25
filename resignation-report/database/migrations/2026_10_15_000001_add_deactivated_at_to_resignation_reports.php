<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Désactivation différée (audit n°8 de Kintai, décision D2) : le compte d'un démissionnaire n'est plus désactivé à la
 * création du rapport mais à sa date de démission, par la tâche planifiée « resignation-deactivate ». deactivated_at
 * note quand le rapport a été appliqué, pour ne jamais le rejouer (un compte réactivé à la main le reste).
 *
 * Rapports existants : ils ont tous été appliqués à leur création (ancien comportement), d'où le remplissage avec
 * created_at.
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if (!$this->schema()->hasTable('resignation_reports') || $this->schema()->hasColumn('resignation_reports', 'deactivated_at')) {
            return;
        }
        $this->schema()->table('resignation_reports', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable();
        });

        if (!$this->schema()->hasColumn('resignation_reports', 'user_id')) {
            return;
        }
        $connection = $this->capsule->getConnection();
        $connection->table('resignation_reports')
            ->whereNotNull('user_id')
            ->update(['deactivated_at' => $connection->raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        // Pas de suppression : la colonne porte l'historique des désactivations.
    }
};
