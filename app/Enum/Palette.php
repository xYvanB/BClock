<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Palette dell'interfaccia: le modalità della collezione `Tema` di FigCraft.
 * I colori stanno in resources/css/tema.css (esportato da FigCraft).
 */
enum Palette: string
{
    /** @var string Scala di grigi del wireframe */
    case Wireframe = 'wireframe';

    /** @var string BClock scuro/oro (predefinita) */
    case Dark = 'scuro';

    /** @var string BClock chiaro */
    case Light = 'chiaro';

    /** @var string Palette modificabile a mano (parte uguale allo scuro) */
    case Custom = 'personalizzata';

    /**
     * Colori che la persona può scegliere nella palette personalizzata (gli altri si ricavano da questi nel CSS).
     * Il valore è il colore di partenza, uguale alla palette scura.
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
     * Stile inline dell'elemento <html> per la palette personalizzata: i colori scelti più il colore del testo sul
     * primario (scuro o chiaro, a seconda di quanto è chiaro il primario). Vuoto per le altre palette.
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
     * Le palette scure attivano anche la classe `dark` di Tailwind.
     */
    public function isDark(): bool
    {
        return $this === self::Dark || $this === self::Custom;
    }
}
