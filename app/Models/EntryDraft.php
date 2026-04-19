<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Models;

use EvilStudio\Cryptosik\Enums\EntryContentFormat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntryDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'vault_id',
        'user_id',
        'entry_date',
        'title_enc',
        'content_enc',
        'content_format',
    ];

    protected function casts(): array
    {
        return [
            'content_format' => EntryContentFormat::class,
            'entry_date' => 'date',
        ];
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DraftAttachment::class, 'draft_id');
    }
}
