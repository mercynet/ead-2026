<?php

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_type',
        'name',
        'email',
        'password',
        'cpf',
        'headline',
        'bio',
        'avatar',
        'linkedin_url',
        'twitter_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isDeveloper(): bool
    {
        return $this->user_type === UserType::Developer;
    }

    public function isAdmin(): bool
    {
        return $this->user_type === UserType::Admin;
    }

    public function isInstructor(): bool
    {
        return $this->user_type === UserType::Instructor;
    }

    public function isStudent(): bool
    {
        return $this->user_type === UserType::Student;
    }

    public function isTenantAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function canAccessAllTenants(): bool
    {
        return $this->isDeveloper();
    }

    public function canAccessTenant(Tenant $tenant): bool
    {
        if ($this->isDeveloper()) {
            return true;
        }

        return $this->tenant_id === $tenant->id;
    }

    public function canManageContent(): bool
    {
        return in_array($this->user_type, [
            UserType::Developer,
            UserType::Admin,
            UserType::Instructor,
        ]);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function belongsToTenant(Tenant $tenant): bool
    {
        return (int) $this->tenant_id === (int) $tenant->id;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'user_type' => UserType::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
