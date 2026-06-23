<?php

declare(strict_types=1);

namespace App\Enums;

enum Recommendation: string
{
    case StrongHire = 'strong_hire';
    case Hire       = 'hire';
    case Maybe      = 'maybe';
    case Reject     = 'reject';

    public function label(): string
    {
        return match ($this) {
            self::StrongHire => 'Strong Hire',
            self::Hire       => 'Hire',
            self::Maybe      => 'Maybe',
            self::Reject     => 'Reject',
        };
    }

    /** Tailwind color token for badges. */
    public function color(): string
    {
        return match ($this) {
            self::StrongHire => 'green',
            self::Hire       => 'teal',
            self::Maybe      => 'amber',
            self::Reject     => 'red',
        };
    }

    /** Map an overall score (0-100) to a base recommendation using config bands. */
    public static function fromScore(float $overall): self
    {
        $bands = config('watad.scoring.bands');
        return match (true) {
            $overall >= $bands['strong_hire'] => self::StrongHire,
            $overall >= $bands['hire']        => self::Hire,
            $overall >= $bands['maybe']       => self::Maybe,
            default                           => self::Reject,
        };
    }

    public function downgrade(): self
    {
        return match ($this) {
            self::StrongHire => self::Hire,
            self::Hire       => self::Maybe,
            self::Maybe      => self::Reject,
            self::Reject     => self::Reject,
        };
    }
}
