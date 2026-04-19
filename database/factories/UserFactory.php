<?php

declare(strict_types=1);

namespace Database\Factories;

use EvilStudio\Cryptosik\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
        ];
    }
}
