<?php

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_type' => UserType::Student,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function developer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => null,
            'user_type' => UserType::Developer,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_type' => UserType::Admin,
        ]);
    }

    public function instructor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_type' => UserType::Instructor,
        ]);
    }

    public function student(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_type' => UserType::Student,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
