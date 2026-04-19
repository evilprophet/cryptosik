<?php

declare(strict_types=1);

use EvilStudio\Cryptosik\Enums\ChainVerificationResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chain_verification_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('vault_id');
            $table->foreign('vault_id')->references('id')->on('vaults')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->enum('result', array_column(ChainVerificationResult::cases(), 'value'))->default(ChainVerificationResult::Pending->value);
            $table->unsignedBigInteger('broken_sequence_no')->nullable();
            $table->json('details_json')->nullable();
            $table->foreignId('initiated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->boolean('initiated_by_system')->default(false);
            $table->timestamps();

            $table->index(['vault_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chain_verification_runs');
    }
};
