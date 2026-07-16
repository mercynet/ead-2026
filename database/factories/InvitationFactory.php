<?php

namespace Database\Factories;

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Invitation;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => UserType::Student->value,
            'token_hash' => Invitation::hashToken(Str::random(64)),
            'invited_by' => null,
            'accepted_by' => null,
            'expires_at' => now()->addDays(Invitation::EXPIRES_IN_DAYS),
            'accepted_at' => null,
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function role(UserType $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => $type->value,
        ]);
    }

    /**
     * Fixa um token conhecido (armazena só o hash) para asserts de aceite.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes): array => [
            'token_hash' => Invitation::hashToken($token),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function accepted(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'accepted_at' => now(),
            'accepted_by' => $user->id,
        ]);
    }
}
