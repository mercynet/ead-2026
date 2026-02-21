<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantCustomization extends Model
{
    protected $fillable = [
        'tenant_id',
        'draft_settings',
        'published_settings',
        'last_published_at',
        'has_pending_changes',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected function casts(): array
    {
        return [
            'draft_settings' => 'array',
            'published_settings' => 'array',
            'last_published_at' => 'datetime',
            'has_pending_changes' => 'boolean',
        ];
    }
}
