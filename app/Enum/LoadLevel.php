<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Daily load of a person against their capacity for the day.
 */
enum LoadLevel: string
{
    /** @var string Fewer assigned hours than available */
    case Under = 'sotto_capacita';

    /** @var string Exactly the available hours (to the hundredth of an hour) */
    case Full = 'pieno';

    /** @var string More assigned hours than available */
    case Over = 'sovraccarico';
}
