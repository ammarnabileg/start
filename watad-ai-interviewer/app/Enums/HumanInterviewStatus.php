<?php

declare(strict_types=1);

namespace App\Enums;

enum HumanInterviewStatus: string
{
    case Scheduled   = 'scheduled';
    case InProgress  = 'in_progress';
    case Completed   = 'completed';
    case Cancelled   = 'cancelled';
    case NoShow      = 'no_show';
    case Rescheduled = 'rescheduled';
}
