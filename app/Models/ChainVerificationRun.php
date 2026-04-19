<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Models;

use EvilStudio\Cryptosik\Enums\ChainVerificationResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChainVerificationRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'vault_id',
        'started_at',
        'finished_at',
        'result',
        'broken_sequence_no',
        'details_json',
        'initiated_by_admin_id',
        'initiated_by_system',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'result' => ChainVerificationResult::class,
            'broken_sequence_no' => 'integer',
            'details_json' => 'array',
            'initiated_by_system' => 'boolean',
        ];
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function initiatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'initiated_by_admin_id');
    }
}
