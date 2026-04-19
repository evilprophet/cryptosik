<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_chain_states', function (Blueprint $table) {
            $table->uuid('vault_id')->primary();
            $table->foreign('vault_id')->references('id')->on('vaults')->cascadeOnDelete();
            $table->unsignedBigInteger('last_sequence_no')->default(0);
            $table->char('last_entry_hash', 64)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_chain_states');
    }
};
