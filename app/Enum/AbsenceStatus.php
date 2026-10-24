<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Una richiesta pesa sulla capacità solo dopo la conferma dell'HR; l'HR può rifiutarla con un motivo.
 */
enum AbsenceStatus: string
{
    case Pending = 'in_attesa';
    case Confirmed = 'confermata';
    case Rejected = 'rifiutata';
}
