<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaultChainState extends Model
{
    use HasFactory;

    protected $primaryKey = 'vault_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'vault_id',
        'last_sequence_no',
        'last_entry_hash',
        'updated_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'last_sequence_no' => 'integer',
            'updated_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }
}
