<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'draft_id',
        'filename_enc',
        'mime_enc',
        'size_bytes',
        'blob_enc',
        'blob_nonce',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(EntryDraft::class, 'draft_id');
    }
}
