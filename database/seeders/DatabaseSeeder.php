<?php

declare(strict_types=1);

namespace Database\Seeders;

use EvilStudio\Cryptosik\Models\Admin;
use EvilStudio\Cryptosik\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'email' => 'owner@example.com',
            'is_active' => true,
        ]);

        User::factory()->create([
            'email' => 'member@example.com',
            'is_active' => true,
        ]);

        Admin::query()->firstOrCreate([
            'login' => 'admin',
        ], [
            'password_hash' => Hash::make('admin-password'),
            'is_active' => true,
        ]);
    }
}
