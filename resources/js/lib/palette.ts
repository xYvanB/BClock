import { type Palette } from '@/types';

/** Palette con sfondo scuro: attivano anche la classe `dark` di Tailwind. */
const darkPalettes: Palette[] = ['scuro', 'personalizzata'];

/** Colori che si possono scegliere nella palette personalizzata, con la loro etichetta (le chiavi sono quelle del server). */
export const customColorFields: { key: string; label: string; hint: string }[] = [
    { key: 'bg', label: 'Sfondo', hint: 'Fondo delle pagine' },
    { key: 'surface', label: 'Superfici', hint: 'Schede, menu e pannelli' },
    { key: 'text', label: 'Testo', hint: 'Colore del testo' },
    { key: 'border', label: 'Bordi', hint: 'Linee e contorni' },
    { key: 'primary', label: 'Primario', hint: 'Pulsanti e selezioni' },
    { key: 'accent', label: 'Accento', hint: 'Evidenziazioni e focus' },
    { key: 'danger', label: 'Errore', hint: 'Errori ed eliminazioni' },
    { key: 'success', label: 'Successo', hint: 'Conferme' },
];

export type CustomColors = Record<string, string>;

const relativeLuminance = (hex: string): number => {
    const channels = [1, 3, 5].map((start) => {
        const value = parseInt(hex.slice(start, start + 2), 16) / 255;

        return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
};

/** Rapporto di contrasto WCAG fra due colori (da 1 a 21); sotto 4,5 il testo si legge male. */
export const contrastRatio = (first: string, second: string): number => {
    const [light, dark] = [relativeLuminance(first), relativeLuminance(second)].sort((a, b) => b - a);

    return (light + 0.05) / (dark + 0.05);
};

/** Testo sul colore primario: scuro se il primario è chiaro, chiaro altrimenti (come fa il server). */
const onPrimary = (primary: string): string => {
    const [red, green, blue] = [1, 3, 5].map((start) => parseInt(primary.slice(start, start + 2), 16));

    return (0.299 * red + 0.587 * green + 0.114 * blue) / 255 > 0.55 ? '#0a0a0f' : '#ffffff';
};

/**
 * Applica subito una palette al documento (anteprima o salvataggio). I colori sono in resources/css/tema.css;
 * per la personalizzata si passano i colori scelti, che diventano stile inline di <html>.
 */
export function applyPalette(palette: Palette, customColors?: CustomColors | null): void {
    const root = document.documentElement;
    root.dataset.tema = palette;
    root.classList.toggle('dark', darkPalettes.includes(palette));

    customColorFields.forEach(({ key }) => root.style.removeProperty(`--t-${key}`));
    root.style.removeProperty('--t-on-primary');

    if (palette === 'personalizzata' && customColors) {
        Object.entries(customColors).forEach(([key, value]) => root.style.setProperty(`--t-${key}`, value));

        if (customColors.primary) {
            root.style.setProperty('--t-on-primary', onPrimary(customColors.primary));
        }
    }
}
