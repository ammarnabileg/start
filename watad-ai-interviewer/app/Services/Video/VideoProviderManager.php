<?php

declare(strict_types=1);

namespace App\Services\Video;

use App\Services\Video\Contracts\AvatarProvider;
use App\Services\Video\Providers\HeyGenProvider;
use App\Services\Video\Providers\TavusProvider;

/**
 * Resolves the configured real-time avatar provider (config('watad.video.provider')).
 * Returns null when video is disabled ('none'), so the engine's text/voice path is untouched.
 */
final class VideoProviderManager
{
    public function enabled(): bool
    {
        return config('watad.video.provider', 'none') !== 'none';
    }

    public function resolve(): ?AvatarProvider
    {
        return match (config('watad.video.provider')) {
            'tavus'  => app(TavusProvider::class),
            'heygen' => app(HeyGenProvider::class),
            default  => null,
        };
    }
}
