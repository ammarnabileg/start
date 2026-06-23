<?php

declare(strict_types=1);

namespace App\Enums;

enum RedFlagType: string
{
    case InconsistentAnswer = 'inconsistent_answer';
    case SuspiciousClaim    = 'suspicious_claim';
    case SalaryMismatch     = 'salary_mismatch';
    case FakeExperience     = 'fake_experience';
    case LackOfOwnership    = 'lack_of_ownership';
    case PoorCommunication  = 'poor_communication';
    case AggressiveBehavior = 'aggressive_behavior';
    case EvasiveAnswer      = 'evasive_answer';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function isFatal(): bool
    {
        return in_array($this->value, config('watad.scoring.overrides.fatal_flag_types', []), true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
