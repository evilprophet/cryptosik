<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vault_members', function (Blueprint $table): void {
            $table->timestamp('membership_notified_at')->nullable()->after('added_by_admin_id');
            $table->index('membership_notified_at');
        });

        Schema::create('entry_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['entry_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
            $table->index('entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_reads');

        Schema::table('vault_members', function (Blueprint $table): void {
            $table->dropIndex(['membership_notified_at']);
            $table->dropColumn('membership_notified_at');
        });
    }
};

