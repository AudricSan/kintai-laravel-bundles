<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Aligne la table timeclocks des instances créées avec l'ancien schéma SQL sur celui de la migration de création : il
 * leur manque shift_id, clock_in_ip et clock_out_ip. Les colonnes absentes sont ajoutées, vides (sans clé étrangère :
 * SQLite ne sait pas en ajouter à une table existante). Une table déjà complète n'est pas touchée.
 */
return new class($this->capsule) extends Migration {
    private const COLUMNS = ['shift_id', 'clock_in_ip', 'clock_out_ip'];

    public function up(): void
    {
        $schema = $this->schema();
        if (!$schema->hasTable('timeclocks')) {
            return;
        }
        $missing = array_values(array_filter(self::COLUMNS, fn(string $c): bool => !$schema->hasColumn('timeclocks', $c)));
        if ($missing === []) {
            return;
        }
        $schema->table('timeclocks', function (Blueprint $table) use ($missing) {
            foreach ($missing as $column) {
                if ($column === 'shift_id') {
                    $table->integer('shift_id')->nullable();
                } else {
                    $table->string($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        // Pas de suppression : des données ont pu être écrites dans ces colonnes depuis.
    }
};
