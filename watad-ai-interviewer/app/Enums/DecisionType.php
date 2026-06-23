<?php

declare(strict_types=1);

namespace App\Enums;

enum DecisionType: string
{
    case Advance   = 'advance';
    case Hold      = 'hold';
    case Reject    = 'reject';
    case Approve   = 'approve';
    case MakeOffer = 'make_offer';
}
