<?php

declare(strict_types=1);

namespace Database\Factories;

use EvilStudio\Cryptosik\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'nickname' => fake()->firstName(),
            'locale' => 'en',
            'is_active' => true,
            'notifications_enabled' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }

    public function withoutNotifications(): self
    {
        return $this->state(['notifications_enabled' => false]);
    }
}
