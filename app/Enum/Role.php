<?php

declare(strict_types=1);

namespace App\Enum;

enum Role: string
{
    /** @var string Boss: sees and manages everything */
    case Boss = 'capo';

    /** @var string Area head: manages their own area */
    case AreaHead = 'capo_area';

    /** @var string HR: sees everything, manages holidays and absences */
    case Hr = 'hr';

    /** @var string Employee: sees only their own data */
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
     * Roles this role can assign when creating or editing users.
     * Only the Boss assigns the Boss role; the area head creates only employees of their own area
     * (decisions 2026-10-23, to be confirmed).
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
