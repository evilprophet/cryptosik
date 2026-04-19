<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Models;

use EvilStudio\Cryptosik\Enums\EntryContentFormat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entry extends Model
{
    use HasFactory;

    protected $fillable = [
        'vault_id',
        'sequence_no',
        'entry_date',
        'title_enc',
        'content_enc',
        'content_format',
        'prev_hash',
        'entry_hash',
        'attachment_hash',
        'created_by',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'content_format' => EntryContentFormat::class,
            'sequence_no' => 'integer',
            'entry_date' => 'date',
            'finalized_at' => 'datetime',
        ];
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EntryAttachment::class);
    }
}
