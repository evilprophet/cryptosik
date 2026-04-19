<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->longText('filename_enc');
            $table->longText('mime_enc');
            $table->unsignedInteger('size_bytes');
            $table->longText('blob_enc');
            $table->string('blob_nonce');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_attachments');
    }
};
