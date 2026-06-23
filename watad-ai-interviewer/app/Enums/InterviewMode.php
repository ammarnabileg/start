<?php

declare(strict_types=1);

namespace App\Enums;

enum InterviewMode: string
{
    case Text  = 'text';
    case Voice = 'voice';
    case Video = 'video';

    public function capturesAudio(): bool
    {
        return $this !== self::Text;
    }

    public function capturesVideo(): bool
    {
        return $this === self::Video;
    }
}
