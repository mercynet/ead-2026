<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\UserType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $email
 * @property string $role
 * @property string $token_hash
 * @property int|null $invited_by
 * @property int|null $accepted_by
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $accepted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Invitation extends Model
{
    protected static string $factory = \Database\Factories\InvitationFactory::class;

    /** @use HasFactory<\Database\Factories\InvitationFactory> */
    use HasFactory, LogsActivity;

    /**
     * Prazo de validade padrão de um convite, em dias.
     */
    public const int EXPIRES_IN_DAYS = 7;

    /**
     * UserTypes que um convite pode atribuir. Nunca admin/developer (escalada).
     *
     * @return list<string>
     */
    public static function invitableRoles(): array
    {
        return [UserType::Student->value, UserType::Instructor->value];
    }

    /**
     * Deriva o hash de lookup a partir do token opaco. Só o hash é persistido;
     * o token em claro só existe na resposta de criação do convite.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Trilha de auditoria LGPD (invariante #9): loga o email inventariado em
     * config/lgpd.php.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(config('lgpd.pii')[self::class] ?? [])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'email',
        'role',
        'token_hash',
        'invited_by',
        'accepted_by',
        'expires_at',
        'accepted_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token_hash',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    public function userType(): UserType
    {
        return UserType::from($this->role);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
