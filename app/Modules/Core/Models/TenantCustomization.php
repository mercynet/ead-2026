<?php

namespace App\Modules\Core\Models;

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

    public function manualFreeEnrollmentEnabled(): bool
    {
        return (bool) $this->publishedSetting(
            'learning.enrollments.manual_free_by_instructor',
            $this->publishedSetting('learning_enrollments_manual_free_by_instructor', false)
        );
    }

    public function manualFreeEnrollmentRequiresApproval(): bool
    {
        return (bool) $this->publishedSetting(
            'learning.enrollments.manual_free_requires_approval',
            $this->publishedSetting('learning_enrollments_manual_free_requires_approval', false)
        );
    }

    private function publishedSetting(string $path, mixed $default = null): mixed
    {
        return data_get($this->published_settings ?? [], $path, $default);
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
