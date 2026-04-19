<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'email',
        'nickname',
        'locale',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function ownedVaults(): HasMany
    {
        return $this->hasMany(Vault::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(VaultMember::class);
    }

    public function vaults(): BelongsToMany
    {
        return $this->belongsToMany(Vault::class, 'vault_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(EntryDraft::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class, 'created_by');
    }

    public function displayName(): string
    {
        return trim((string) $this->nickname);
    }
}
