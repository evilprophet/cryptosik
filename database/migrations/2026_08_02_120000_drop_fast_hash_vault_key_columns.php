<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Both columns were fast hashes of the vault key, so holding the database plus the lookup
 * pepper allowed GPU-speed guessing that bypassed Argon2id. Unlock now resolves the vault by
 * trying to unwrap instead. down() cannot restore the values without the vault keys.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vault_crypto', function (Blueprint $table) {
            $table->dropUnique(['vault_locator']);
            $table->dropColumn(['vault_locator', 'key_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('vault_crypto', function (Blueprint $table) {
            $table->string('vault_locator', 64)->nullable();
            $table->string('key_fingerprint', 128)->nullable();
        });

        // Restores the index too, otherwise a rollback followed by a migrate dies on dropUnique.
        Schema::table('vault_crypto', function (Blueprint $table) {
            $table->unique('vault_locator');
        });
    }
};
