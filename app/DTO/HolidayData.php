<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\HolidayKind;

/**
 * Dati validati per creare o modificare un festivo.
 */
final readonly class HolidayData
{
    /**
     * @param  string|null  $until  Ultimo giorno di un periodo di chiusura (solo alla creazione): ogni giorno lavorativo
     *                              fra `$date` e `$until` diventa un giorno di chiusura
     */
    public function __construct(
        public string $date,
        public string $name,
        public HolidayKind $kind = HolidayKind::Holiday,
        public ?string $until = null,
    ) {}
}
