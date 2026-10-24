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
