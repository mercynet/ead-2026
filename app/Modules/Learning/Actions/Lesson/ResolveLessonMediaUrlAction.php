<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\LessonMedia;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Filesystem\Factory;

class ResolveLessonMediaUrlAction
{
    private const URL_TTL_MINUTES = 5;

    private const SAFE_STORAGE_DISKS = ['local', 's3'];

    public function __construct(
        private readonly Factory $filesystem,
    ) {}

    /**
     * @return array{url: ?string, expires_at: ?CarbonInterface, kind: ?string}
     */
    public function handle(LessonMedia $lessonMedia): array
    {
        if (in_array($lessonMedia->provider, ['internal', 's3'], true)) {
            return $this->temporaryStorageUrl($lessonMedia);
        }

        if ($lessonMedia->provider === 'vimeo') {
            return [
                'url' => $this->buildVimeoPlayerUrl($lessonMedia),
                'expires_at' => null,
                'kind' => 'player',
            ];
        }

        if ($lessonMedia->provider === 'youtube') {
            return [
                'url' => $this->buildYoutubePlayerUrl($lessonMedia),
                'expires_at' => null,
                'kind' => 'player',
            ];
        }

        return [
            'url' => $this->embedOrDirectUrl($lessonMedia),
            'expires_at' => null,
            'kind' => $lessonMedia->provider === 'embed' ? 'player' : ($lessonMedia->url === null ? null : 'direct'),
        ];
    }

    /**
     * @return array{url: ?string, expires_at: ?CarbonInterface, kind: ?string}
     */
    private function temporaryStorageUrl(LessonMedia $lessonMedia): array
    {
        $tenantId = (int) $lessonMedia->tenant_id;
        $storagePath = data_get($lessonMedia->metadata, 'storage_path');

        if (! is_string($storagePath) || ! $this->isSafeStoragePath($storagePath, $tenantId)) {
            return $this->invalidStoragePayload();
        }

        $diskName = data_get($lessonMedia->metadata, 'storage_disk', config('filesystems.default'));
        if (! is_string($diskName) || ! $this->isSafeStorageDisk($diskName)) {
            return $this->invalidStoragePayload();
        }

        $expiresAt = CarbonImmutable::now()->addMinutes(self::URL_TTL_MINUTES);
        $disk = $this->filesystem->disk($diskName);

        if (! method_exists($disk, 'temporaryUrl')) {
            return $this->invalidStoragePayload();
        }

        return [
            'url' => call_user_func([$disk, 'temporaryUrl'], $storagePath, $expiresAt),
            'expires_at' => $expiresAt,
            'kind' => 'temporary',
        ];
    }

    /**
     * @return array{url: ?string, expires_at: ?CarbonInterface, kind: ?string}
     */
    private function invalidStoragePayload(): array
    {
        return [
            'url' => null,
            'expires_at' => null,
            'kind' => null,
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

    private function buildVimeoPlayerUrl(LessonMedia $lessonMedia): ?string
    {
        if (is_string($lessonMedia->provider_ref) && $lessonMedia->provider_ref !== '') {
            return 'https://player.vimeo.com/video/'.$lessonMedia->provider_ref;
        }

        return $this->embedOrDirectUrl($lessonMedia);
    }

    private function buildYoutubePlayerUrl(LessonMedia $lessonMedia): ?string
    {
        if (is_string($lessonMedia->provider_ref) && $lessonMedia->provider_ref !== '') {
            return 'https://www.youtube.com/embed/'.$lessonMedia->provider_ref;
        }

        return $this->embedOrDirectUrl($lessonMedia);
    }

    private function embedOrDirectUrl(LessonMedia $lessonMedia): ?string
    {
        $playerUrl = data_get($lessonMedia->metadata, 'player_url');

        if (is_string($playerUrl) && $playerUrl !== '') {
            return $playerUrl;
        }

        return $lessonMedia->url;
    }
}
