<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthLoginCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'code_hash',
        'expires_at',
        'blocked_until',
        'consumed_at',
        'attempts',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'blocked_until' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
