<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A request weighs on capacity only after the final confirmation; whoever confirms can reject it with a reason.
 */
enum AbsenceStatus: string
{
    case Pending = 'in_attesa';
    case Confirmed = 'confermata';
    case Rejected = 'rifiutata';
}
