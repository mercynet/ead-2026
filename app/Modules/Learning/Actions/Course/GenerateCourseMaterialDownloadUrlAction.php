<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\CourseMaterial;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Validation\ValidationException;

class GenerateCourseMaterialDownloadUrlAction
{
    private const URL_TTL_MINUTES = 5;

    private const SAFE_STORAGE_DISKS = ['local', 's3'];

    public function __construct(
        private readonly Factory $filesystem,
    ) {}

    /**
     * @return array{url: string, expires_at: CarbonInterface}
     */
    public function handle(CourseMaterial $courseMaterial): array
    {
        $filePath = $courseMaterial->file_path;

        if (! is_string($filePath) || ! $this->isSafeStoragePath($filePath, (int) $courseMaterial->tenant_id)) {
            throw ValidationException::withMessages([
                'file_path' => ['Material file path is invalid.'],
            ]);
        }

        $diskName = config('filesystems.default');

        if (! is_string($diskName) || ! $this->isSafeStorageDisk($diskName)) {
            throw ValidationException::withMessages([
                'storage_disk' => ['Material storage disk is not allowed.'],
            ]);
        }

        $expiresAt = CarbonImmutable::now()->addMinutes(self::URL_TTL_MINUTES);
        $disk = $this->filesystem->disk($diskName);

        return [
            'url' => $disk->temporaryUrl(
                $filePath,
                $expiresAt,
                [
                    'ResponseContentDisposition' => 'attachment; filename="'.basename($filePath).'"',
                ],
            ),
            'expires_at' => $expiresAt,
        ];
    }

    private function isSafeStorageDisk(string $diskName): bool
    {
        return in_array($diskName, self::SAFE_STORAGE_DISKS, true);
    }

    private function isSafeStoragePath(string $storagePath, int $tenantId): bool
    {
        $prefix = 'tenants/'.$tenantId.'/';

        return str_starts_with($storagePath, $prefix)
            && preg_match('/^tenants\/\d+\/[A-Za-z0-9._-][A-Za-z0-9\/._-]*$/', $storagePath) === 1
            && ! str_contains($storagePath, '..')
            && ! str_contains($storagePath, '\\');
    }
}
