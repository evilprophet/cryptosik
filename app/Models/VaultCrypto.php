<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaultCrypto extends Model
{
    use HasFactory;

    protected $table = 'vault_crypto';

    protected $primaryKey = 'vault_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * No fast hash of the vault key is stored here - vault_locator and key_fingerprint were
     * dropped because they allowed GPU-speed guessing that bypassed Argon2id.
     */
    protected $fillable = [
        'vault_id',
        'kdf_salt',
        'kdf_params',
        'wrapped_data_key',
        'wrap_nonce',
    ];

    protected function casts(): array
    {
        return [
            'kdf_params' => 'array',
        ];
    }

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }
}
