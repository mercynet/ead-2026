<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\LessonMedia;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Factory;

class ResolveLessonMediaUrlAction
{
    private const URL_TTL_MINUTES = 5;

    public function __construct(
        private readonly Factory $filesystem,
    ) {}

    /**
     * @return array{url: ?string, expires_at: ?CarbonInterface}
     */
    public function handle(LessonMedia $lessonMedia): array
    {
        if (! in_array($lessonMedia->provider, ['internal', 's3'], true)) {
            return [
                'url' => $lessonMedia->url,
                'expires_at' => null,
            ];
        }

        $storagePath = data_get($lessonMedia->metadata, 'storage_path');
        if (! is_string($storagePath) || $storagePath === '') {
            return [
                'url' => $lessonMedia->url,
                'expires_at' => null,
            ];
        }

        $diskName = data_get($lessonMedia->metadata, 'storage_disk', config('filesystems.default'));
        $expiresAt = CarbonImmutable::now()->addMinutes(self::URL_TTL_MINUTES);

        return [
            'url' => $this->filesystem
                ->disk(is_string($diskName) && $diskName !== '' ? $diskName : config('filesystems.default'))
                ->temporaryUrl($storagePath, $expiresAt),
            'expires_at' => $expiresAt,
        ];
    }
}
