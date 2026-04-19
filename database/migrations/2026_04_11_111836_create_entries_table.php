<?php

declare(strict_types=1);

use EvilStudio\Cryptosik\Enums\EntryContentFormat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('vault_id');
            $table->foreign('vault_id')->references('id')->on('vaults')->cascadeOnDelete();
            $table->unsignedBigInteger('sequence_no');
            $table->date('entry_date')->nullable();
            $table->longText('title_enc');
            $table->longText('content_enc');
            $table->enum('content_format', array_column(EntryContentFormat::cases(), 'value'))->default(EntryContentFormat::Markdown->value);
            $table->char('prev_hash', 64)->nullable();
            $table->char('entry_hash', 64);
            $table->char('attachment_hash', 64)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at');
            $table->timestamps();

            $table->unique(['vault_id', 'sequence_no']);
            $table->unique(['vault_id', 'entry_hash']);
            $table->index(['vault_id', 'finalized_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
