<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Models;

use EvilStudio\Cryptosik\Enums\VaultStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vault extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'owner_user_id',
        'name_enc',
        'description_enc',
        'status',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VaultStatus::class,
            'archived_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(VaultMember::class);
    }

    public function crypto(): HasOne
    {
        return $this->hasOne(VaultCrypto::class);
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(EntryDraft::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function chainState(): HasOne
    {
        return $this->hasOne(VaultChainState::class);
    }

    public function verificationRuns(): HasMany
    {
        return $this->hasMany(ChainVerificationRun::class);
    }

    public function latestVerificationRun(): HasOne
    {
        return $this->hasOne(ChainVerificationRun::class)->latestOfMany('started_at');
    }
}
