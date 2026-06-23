<?php

declare(strict_types=1);

namespace App\Enums;

enum ApplicationStatus: string
{
    case Applied          = 'applied';
    case AiScreening      = 'ai_screening';
    case Qualified        = 'qualified';
    case Disqualified     = 'disqualified';
    case TechInterview    = 'tech_interview';
    case ManagerInterview = 'manager_interview';
    case FinalReview      = 'final_review';
    case Offer            = 'offer';
    case Hired            = 'hired';
    case Rejected         = 'rejected';
    case Withdrawn        = 'withdrawn';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Hired, self::Rejected, self::Withdrawn], true);
    }

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
