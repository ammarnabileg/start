<?php

declare(strict_types=1);

namespace App\Enums;

enum OfferStatus: string
{
    case Draft     = 'draft';
    case Sent      = 'sent';
    case Viewed    = 'viewed';
    case Accepted  = 'accepted';
    case Declined  = 'declined';
    case Expired   = 'expired';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
