<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\AppLocale;
use App\Enum\Palette;
use App\Enum\TimeFormat;

/**
 * Preferenze personali: lingua, formato orario e palette.
 */
final readonly class PreferencesData
{
    public function __construct(
        public AppLocale $locale,
        public TimeFormat $timeFormat,
        public Palette $palette,
    ) {}
}
