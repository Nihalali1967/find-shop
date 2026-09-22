<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string,mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => '+9198'.fake()->unique()->numerify('########'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone_verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['phone_verified_at' => null]);
    }
}
