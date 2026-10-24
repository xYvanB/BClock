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
