<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Aligne la table shift_swap_requests des instances créées avec l'ancien schéma SQL sur celui qu'utilise le code.
 *
 * Ces instances ont target_id (obligatoire), reviewed_by / reviewed_at et peer_accepted_at, alors que le code écrit
 * target_user_id, accepted_at et approved_by_id / approved_at : créer une demande d'échange y échouait. La table est
 * reconstruite au bon schéma et ses lignes recopiées (target_id → target_user_id, peer_accepted_at → accepted_at,
 * reviewed_by / reviewed_at → approved_by_id / approved_at). Une table déjà au bon schéma (installation neuve ou
 * migration du Core) n'est pas touchée.
 */
return new class($this->capsule) extends Migration {
    private const LEGACY = 'shift_swap_requests_legacy_schema';

    public function up(): void
    {
        $schema = $this->schema();
        if (!$schema->hasTable('shift_swap_requests') || $schema->hasColumn('shift_swap_requests', 'target_user_id')
            || !$schema->hasColumn('shift_swap_requests', 'target_id')) {
            return;
        }

        $db = $this->capsule->getConnection();
        $schema->rename('shift_swap_requests', self::LEGACY);
        $schema->create('shift_swap_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('store_id');
            $table->integer('requester_id');
            $table->integer('target_user_id')->nullable();
            $table->integer('requester_shift_id');
            $table->integer('target_shift_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->integer('accepted_by_id')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->integer('approved_by_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['store_id', 'status']);
            $table->index('requester_id');
            $table->index('target_user_id');
        });

        $hasPeer = $schema->hasColumn(self::LEGACY, 'peer_accepted_at');
        foreach ($db->table(self::LEGACY)->orderBy('id')->get() as $row) {
            $row = (array) $row;
            $db->table('shift_swap_requests')->insert([
                'id'                 => $row['id'],
                'store_id'           => $row['store_id'],
                'requester_id'       => $row['requester_id'],
                'target_user_id'     => $row['target_id'] ?? null,
                'requester_shift_id' => $row['requester_shift_id'],
                'target_shift_id'    => $row['target_shift_id'] ?? null,
                'status'             => $row['status'] ?? 'pending',
                'reason'             => $row['reason'] ?? null,
                'accepted_at'        => $hasPeer ? ($row['peer_accepted_at'] ?? null) : null,
                'approved_by_id'     => $row['reviewed_by'] ?? null,
                'approved_at'        => $row['reviewed_at'] ?? null,
                'created_at'         => $row['created_at'] ?? date('Y-m-d H:i:s'),
                'updated_at'         => $row['updated_at'] ?? null,
            ]);
        }
        $schema->drop(self::LEGACY);
    }

    public function down(): void
    {
        // Pas de retour à l'ancien schéma : il empêchait le code de créer des demandes d'échange.
    }
};
