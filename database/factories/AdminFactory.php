<?php

declare(strict_types=1);

namespace Database\Factories;

use EvilStudio\Cryptosik\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    public const DEFAULT_PASSWORD = 'super-secret-password';

    public function definition(): array
    {
        return [
            'login' => fake()->unique()->userName(),
            'password_hash' => Hash::make(self::DEFAULT_PASSWORD),
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
