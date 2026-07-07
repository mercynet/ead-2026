<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\CourseMaterial;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Factory;

class GenerateCourseMaterialDownloadUrlAction
{
    private const URL_TTL_MINUTES = 5;

    public function __construct(
        private readonly Factory $filesystem,
    ) {}

    /**
     * @return array{url: string, expires_at: CarbonInterface}
     */
    public function handle(CourseMaterial $courseMaterial): array
    {
        $expiresAt = CarbonImmutable::now()->addMinutes(self::URL_TTL_MINUTES);
        $disk = $this->filesystem->disk(config('filesystems.default'));

        return [
            'url' => $disk->temporaryUrl(
                $courseMaterial->file_path,
                $expiresAt,
                [
                    'ResponseContentDisposition' => 'attachment; filename="'.basename($courseMaterial->file_path).'"',
                ],
            ),
            'expires_at' => $expiresAt,
        ];
    }
}
