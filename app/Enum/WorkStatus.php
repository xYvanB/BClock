<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Status shared by projects and activities.
 */
enum WorkStatus: string
{
    case Todo = 'da_fare';
    case InProgress = 'in_corso';
    case Done = 'completato';
    case WaitingClient = 'in_attesa_di_cliente';
    case Archived = 'archiviato';

    /**
     * Get all enum values as an array.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
