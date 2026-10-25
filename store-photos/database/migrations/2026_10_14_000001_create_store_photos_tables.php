<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Tables des envois de photos, créées si absentes (installation neuve). Reprise à l'identique de la migration du Core
 * 2026_06_09_000038 lors de l'extraction (étape 4 du plan du contrat) : sur une instance existante, les tables sont
 * déjà là et ne sont pas touchées ; leurs évolutions futures se font ici.
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if (!$this->schema()->hasTable('store_photo_submissions')) {
            $this->schema()->create('store_photo_submissions', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('store_id');
                $table->string('week_label', 10);
                $table->text('notes')->nullable();
                $table->integer('image_count')->default(0);
                $table->integer('retention_days')->default(14);
                $table->tinyInteger('compressed')->default(0);
                $table->timestamp('compressed_at')->nullable();
                $table->timestamp('mail_sent_at')->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->integer('created_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
                $table->index('store_id');
                $table->index('week_label');
            });
        }

        if (!$this->schema()->hasTable('store_photo_images')) {
            $this->schema()->create('store_photo_images', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('submission_id');
                $table->string('filename', 255);
                $table->string('filepath', 512);
                $table->integer('filesize')->default(0);
                $table->string('mime_type', 50)->nullable();
                $table->integer('compressed_filesize')->nullable();
                $table->integer('width')->nullable();
                $table->integer('height')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('submission_id')->references('id')->on('store_photo_submissions')->onDelete('cascade');
                $table->index('submission_id');
            });
        }
    }

    public function down(): void
    {
        // Pas de suppression : les tables existaient avant cette migration sur les instances mises à jour, et un retour
        // arrière ne doit pas effacer les envois de photos.
    }
};
