<?php

namespace Database\Factories;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Assessment\Models\Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = \App\Modules\Assessment\Models\Certificate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'enrollment_id' => Enrollment::factory(),
            'certificate_number' => 'CERT-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(4))),
            'issued_at' => now(),
            'status' => 'issued',
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['status' => 'revoked']);
    }
}
