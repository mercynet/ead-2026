<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Multitenancy\Concerns\UsesMultitenancyConfig;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Concerns\ImplementsTenant;

/**
 * @property int $id
 * @property string $name
 * @property string $domain
 * @property string|null $database
 * @property string|null $description
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Tenant extends Model implements IsTenant
{
    protected static string $factory = \Database\Factories\TenantFactory::class;

    use HasFactory, LogsActivity;
    use ImplementsTenant;
    use UsesMultitenancyConfig;

    protected $fillable = [
        'name',
        'domain',
        'database',
        'description',
        'is_active',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('core')
            ->logOnly(['is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customization(): HasOne
    {
        return $this->hasOne(TenantCustomization::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(TenantIntegration::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
