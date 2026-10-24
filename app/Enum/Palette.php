<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Interface palette: the modes of FigCraft's `Tema` collection.
 * The colors live in resources/css/tema.css (exported from FigCraft).
 */
enum Palette: string
{
    /** @var string Wireframe grayscale */
    case Wireframe = 'wireframe';

    /** @var string BClock scuro/oro (predefinita) */
    case Dark = 'scuro';

    /** @var string BClock chiaro */
    case Light = 'chiaro';

    /** @var string Palette modificabile a mano (parte uguale allo scuro) */
    case Custom = 'personalizzata';

    /**
     * Colors a person can choose in the custom palette (the others are derived from these in the CSS).
     * The value is the starting color, equal to the dark palette.
     *
     * @var array<string, string>
     */
    public const array CUSTOM_DEFAULTS = [
        'bg' => '#0a0a0f',
        'surface' => '#1d1d33',
        'text' => '#c8c8d8',
        'border' => '#3a3a5a',
        'primary' => '#c9a84c',
        'accent' => '#e8c97a',
        'danger' => '#e5736f',
        'success' => '#5fbf8a',
    ];

    /**
     * Inline style of the <html> element for the custom palette: the chosen colors plus the text color on the
     * primary (dark or light, depending on how light the primary is). Empty for the other palettes.
     *
     * @param  array<string, string>|null  $colors
     */
    public function customStyle(?array $colors): string
    {
        if ($this !== self::Custom) {
            return '';
        }

        $colors = array_merge(self::CUSTOM_DEFAULTS, array_intersect_key($colors ?? [], self::CUSTOM_DEFAULTS));

        $style = '';
        foreach ($colors as $key => $value) {
            $style .= "--t-{$key}:{$value};";
        }

        [$red, $green, $blue] = sscanf($colors['primary'], '#%02x%02x%02x');
        $light = (0.299 * $red + 0.587 * $green + 0.114 * $blue) / 255 > 0.55;

        return $style.'--t-on-primary:'.($light ? '#0a0a0f' : '#ffffff').';';
    }

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
     * Dark palettes also enable Tailwind's `dark` class.
     */
    public function isDark(): bool
    {
        return $this === self::Dark || $this === self::Custom;
    }
}
