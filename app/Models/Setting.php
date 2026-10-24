<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Impostazione dell'applicazione (chiave-valore), modificabile da Capo e HR.
 */
class Setting extends Model
{
    public const string LOAD_TOLERANCE_ENABLED = 'load_tolerance_enabled';

    public const string LOAD_TOLERANCE_HOURS = 'load_tolerance_hours';

    public const string WORKING_WEEKDAYS = 'working_weekdays';

    /** Giorni lavorativi di partenza: da lunedì (1) a venerdì (5), numerati come `dayOfWeekIso`. */
    public const array DEFAULT_WORKING_WEEKDAYS = [1, 2, 3, 4, 5];

    /** Tolleranza proposta quando la si attiva per la prima volta. */
    public const float DEFAULT_LOAD_TOLERANCE_HOURS = 0.5;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    public static function read(string $key, ?string $default = null): ?string
    {
        return static::find($key)?->value ?? $default;
    }

    public static function write(string $key, string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Giorni della settimana in cui si lavora (1 = lunedì … 7 = domenica), definiti da Capo e HR.
     *
     * @return list<int>
     */
    public static function workingWeekdays(): array
    {
        $saved = static::read(self::WORKING_WEEKDAYS);
        $days = $saved === null ? [] : array_values(array_filter(array_map('intval', explode(',', $saved)), fn (int $day): bool => $day >= 1 && $day <= 7));

        return $days === [] ? self::DEFAULT_WORKING_WEEKDAYS : array_values(array_unique($days));
    }

    public static function loadToleranceEnabled(): bool
    {
        return static::read(self::LOAD_TOLERANCE_ENABLED, '0') === '1';
    }

    /**
     * Ore di scarto entro cui un giorno conta come "pieno". Il valore salvato resta anche a tolleranza spenta.
     */
    public static function configuredLoadToleranceHours(): float
    {
        return (float) static::read(self::LOAD_TOLERANCE_HOURS, (string) self::DEFAULT_LOAD_TOLERANCE_HOURS);
    }

    /**
     * Tolleranza effettiva: 0 se spenta.
     */
    public static function loadToleranceHours(): float
    {
        return static::loadToleranceEnabled() ? static::configuredLoadToleranceHours() : 0.0;
    }
}
