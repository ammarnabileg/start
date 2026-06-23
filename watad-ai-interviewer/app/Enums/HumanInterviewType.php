<?php

declare(strict_types=1);

namespace App\Enums;

enum HumanInterviewType: string
{
    case Technical  = 'technical';
    case Manager    = 'manager';
    case Department = 'department';
    case Panel      = 'panel';

    public function label(): string
    {
        return ucfirst($this->value).' Interview';
    }
}
