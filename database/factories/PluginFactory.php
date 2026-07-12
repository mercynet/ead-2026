<?php

namespace Database\Factories;

use App\Modules\Ecosystem\Models\Plugin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plugin>
 */
class PluginFactory extends Factory
{
    protected $model = Plugin::class;

    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'slug' => $slug,
            'name' => fake()->words(2, true),
            'capability_key' => 'feature.'.str_replace('-', '_', $slug),
            'kind' => 'feature',
            'status' => 'draft',
            'visibility' => 'public',
            'tier' => 'free',
            'is_curated' => false,
            'directory_name' => null,
            'short_description' => fake()->sentence(),
            'long_description' => fake()->paragraph(),
            'logo_path' => null,
            'default_locale' => 'pt_BR',
            'support_url' => null,
            'docs_url' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => 'published']);
    }

    public function deprecated(): static
    {
        return $this->state(fn (): array => ['status' => 'deprecated']);
    }

    public function internal(): static
    {
        return $this->state(fn (): array => ['visibility' => 'internal']);
    }

    public function premium(): static
    {
        return $this->state(fn (): array => ['tier' => 'premium']);
    }

    public function gateway(string $slug): static
    {
        return $this->state(fn (): array => [
            'kind' => 'gateway',
            'slug' => $slug,
            'capability_key' => 'gateway.'.$slug,
        ]);
    }
}
