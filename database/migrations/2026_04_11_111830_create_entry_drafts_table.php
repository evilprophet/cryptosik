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
        Schema::create('entry_drafts', function (Blueprint $table) {
            $table->id();
            $table->uuid('vault_id');
            $table->foreign('vault_id')->references('id')->on('vaults')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('entry_date')->nullable();
            $table->longText('title_enc');
            $table->longText('content_enc');
            $table->enum('content_format', array_column(EntryContentFormat::cases(), 'value'))->default(EntryContentFormat::Markdown->value);
            $table->timestamps();

            $table->unique(['vault_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_drafts');
    }
};
