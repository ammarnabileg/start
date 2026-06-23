<?php

declare(strict_types=1);

namespace App\Enums;

enum InterviewStatus: string
{
    case Scheduled  = 'scheduled';
    case InProgress = 'in_progress';
    case Processing = 'processing';
    case Completed  = 'completed';
    case Abandoned  = 'abandoned';
    case Error      = 'error';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Abandoned], true);
    }

    public function isLive(): bool
    {
        return $this === self::InProgress;
    }
}
