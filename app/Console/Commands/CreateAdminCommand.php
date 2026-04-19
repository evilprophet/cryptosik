<?php

declare(strict_types=1);

namespace EvilStudio\Cryptosik\Console\Commands;

use EvilStudio\Cryptosik\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'cryptosik:admin-create {login} {password?}';

    protected $description = 'Create a Cryptosik admin account (CLI-only flow).';

    public function handle(): int
    {
        $login = (string) $this->argument('login');
        $password = (string) ($this->argument('password') ?? $this->secret('Admin password'));

        if ($password === '') {
            $this->error('Password is required.');

            return self::FAILURE;
        }

        if (Admin::query()->where('login', $login)->exists()) {
            $this->error('Admin with this login already exists.');

            return self::FAILURE;
        }

        Admin::query()->create([
            'login' => $login,
            'password_hash' => Hash::make($password),
            'is_active' => true,
        ]);

        $this->info('Admin account created successfully.');

        return self::SUCCESS;
    }
}
