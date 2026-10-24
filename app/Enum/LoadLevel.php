<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Carico giornaliero di una persona rispetto alla sua capacità del giorno.
 */
enum LoadLevel: string
{
    /** @var string Meno ore assegnate di quelle disponibili */
    case Under = 'sotto_capacita';

    /** @var string Esattamente le ore disponibili (al centesimo di ora) */
    case Full = 'pieno';

    /** @var string Più ore assegnate di quelle disponibili */
    case Over = 'sovraccarico';
}
