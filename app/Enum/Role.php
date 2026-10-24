<?php

declare(strict_types=1);

namespace App\Enum;

enum Role: string
{
    /** @var string Capo: vede e gestisce tutto */
    case Boss = 'capo';

    /** @var string Capo area: gestisce la propria area */
    case AreaHead = 'capo_area';

    /** @var string HR: vede tutto, gestisce festivi e assenze */
    case Hr = 'hr';

    /** @var string Dipendente: vede solo i propri dati */
    case Employee = 'dipendente';

    /**
     * Get all enum values as an array.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Ruoli che questo ruolo può assegnare creando o modificando utenti.
     * Solo il Capo assegna il ruolo Capo; il Capo area crea solo dipendenti della propria area
     * (decisioni 2026-10-23, da confermare).
     *
     * @return list<self>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::Boss => self::cases(),
            self::Hr => [self::AreaHead, self::Hr, self::Employee],
            self::AreaHead => [self::Employee],
            self::Employee => [],
        };
    }
}
