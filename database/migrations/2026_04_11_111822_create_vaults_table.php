<?php

declare(strict_types=1);

use EvilStudio\Cryptosik\Enums\VaultStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vaults', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->longText('name_enc');
            $table->longText('description_enc')->nullable();
            $table->enum('status', array_column(VaultStatus::cases(), 'value'))->default(VaultStatus::Active->value)->index();
            $table->timestamp('archived_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['owner_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaults');
    }
};
