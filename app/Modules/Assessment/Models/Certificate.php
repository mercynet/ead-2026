<?php

namespace App\Modules\Assessment\Models;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected static string $factory = \Database\Factories\CertificateFactory::class;

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'enrollment_id',
        'course_id',
        'certificate_number',
        'issued_at',
        'status',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'user_id' => 'integer',
            'enrollment_id' => 'integer',
            'course_id' => 'integer',
            'issued_at' => 'datetime',
        ];
    }

    public static function generateCertificateNumber(int $tenantId): string
    {
        $year = date('Y');
        $random = strtoupper(bin2hex(random_bytes(4)));

        return "CERT-{$year}-{$random}";
    }
}
