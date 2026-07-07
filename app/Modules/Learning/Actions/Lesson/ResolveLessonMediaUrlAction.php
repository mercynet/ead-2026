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
        $storagePath = data_get($lessonMedia->metadata, 'storage_path');
        if (! is_string($storagePath) || $storagePath === '') {
            return [
                'url' => $lessonMedia->url,
                'expires_at' => null,
                'kind' => $lessonMedia->url === null ? null : 'direct',
            ];
        }

        $diskName = data_get($lessonMedia->metadata, 'storage_disk', config('filesystems.default'));
        $expiresAt = CarbonImmutable::now()->addMinutes(self::URL_TTL_MINUTES);

        return [
            'url' => $this->filesystem
                ->disk(is_string($diskName) && $diskName !== '' ? $diskName : config('filesystems.default'))
                ->temporaryUrl($storagePath, $expiresAt),
            'expires_at' => $expiresAt,
            'kind' => 'temporary',
        ];
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
