<?php

declare(strict_types=1);

namespace kintai\Database\Migrations;

use kintai\Contract\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * store_id nullable : null = note visible par toute l'organisation, sinon
 * rattachée à un store précis (voir NotebookEntryRepositoryInterface::findVisibleForStores()).
 */
return new class($this->capsule) extends Migration {
    public function up(): void
    {
        if ($this->schema()->hasTable('notebook_entries')) {
            return;
        }
        $this->schema()->create('notebook_entries', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('author_id');
            $table->integer('store_id')->nullable();
            $table->text('content');
            $table->boolean('pinned')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['store_id', 'pinned']);
            $table->index('expires_at');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('notebook_entries');
    }
};
