<?php

declare(strict_types=1);

namespace App\Enums;

enum EvaluationRecommendation: string
{
    case StrongYes = 'strong_yes';
    case Yes       = 'yes';
    case Neutral   = 'neutral';
    case No        = 'no';
    case StrongNo  = 'strong_no';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
