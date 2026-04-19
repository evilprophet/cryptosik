<?php

declare(strict_types=1);

use EvilStudio\Cryptosik\Enums\VaultMemberRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('vault_id');
            $table->foreign('vault_id')->references('id')->on('vaults')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('role', array_column(VaultMemberRole::cases(), 'value'))->default(VaultMemberRole::Member->value);
            $table->foreignId('added_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique(['vault_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_members');
    }
};
