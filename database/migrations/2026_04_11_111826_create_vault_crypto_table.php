<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_crypto', function (Blueprint $table) {
            $table->uuid('vault_id')->primary();
            $table->foreign('vault_id')->references('id')->on('vaults')->cascadeOnDelete();
            $table->string('vault_locator', 64)->unique();
            $table->string('kdf_salt');
            $table->json('kdf_params');
            $table->text('wrapped_data_key');
            $table->string('wrap_nonce');
            $table->string('key_fingerprint', 128);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_crypto');
    }
};
