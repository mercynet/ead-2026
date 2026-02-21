<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantIntegration extends Model
{
    protected $fillable = [
        'tenant_id',
        'integration_type',
        'configuration',
        'is_enabled',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'is_enabled' => 'boolean',
        ];
    }
}
