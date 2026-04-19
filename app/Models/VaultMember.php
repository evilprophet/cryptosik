<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Models;

use EvilStudio\Cryptosik\Enums\VaultMemberRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaultMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'vault_id',
        'user_id',
        'role',
        'added_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'role' => VaultMemberRole::class,
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

    public function addedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'added_by_admin_id');
    }
}
