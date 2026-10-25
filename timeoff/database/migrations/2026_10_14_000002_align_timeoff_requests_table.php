<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Aligne la table timeoff_requests des instances créées avec l'ancien schéma SQL sur celui de la migration de création.
 *
 * Ces instances ont reviewed_by / reviewed_at / review_notes là où le code écrit processed_by / processed_at /
 * admin_note : traiter une demande par l'API y échouait. Les colonnes sont renommées, leurs données gardées. Une table
 * déjà au bon schéma (installation neuve ou migration du Core) n'est pas touchée.
 */
return new class($this->capsule) extends Migration {
    private const RENAMES = ['reviewed_by' => 'processed_by', 'reviewed_at' => 'processed_at', 'review_notes' => 'admin_note'];

    public function up(): void
    {
        $schema = $this->schema();
        if (!$schema->hasTable('timeoff_requests')) {
            return;
        }
        foreach (self::RENAMES as $old => $new) {
            if ($schema->hasColumn('timeoff_requests', $old) && !$schema->hasColumn('timeoff_requests', $new)) {
                $schema->table('timeoff_requests', function (Blueprint $table) use ($old, $new) {
                    $table->renameColumn($old, $new);
                });
            }
        }
    }

    public function down(): void
    {
        // Pas de retour à l'ancien schéma : le code n'utilise que les nouveaux noms.
    }
};
