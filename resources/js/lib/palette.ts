import { type Palette } from '@/types';

/** Palettes with a dark background: they also enable Tailwind's `dark` class. */
const darkPalettes: Palette[] = ['scuro', 'personalizzata'];

/** Colors that can be chosen in the custom palette, with their label (the keys are the server's). */
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

/** WCAG contrast ratio between two colors (1 to 21); below 4.5 the text is hard to read. */
export const contrastRatio = (first: string, second: string): number => {
    const [light, dark] = [relativeLuminance(first), relativeLuminance(second)].sort((a, b) => b - a);

    return (light + 0.05) / (dark + 0.05);
};

/** Text on the primary color: dark if the primary is light, light otherwise (as the server does). */
const onPrimary = (primary: string): string => {
    const [red, green, blue] = [1, 3, 5].map((start) => parseInt(primary.slice(start, start + 2), 16));

    return (0.299 * red + 0.587 * green + 0.114 * blue) / 255 > 0.55 ? '#0a0a0f' : '#ffffff';
};

/**
 * Applies a palette to the document right away (preview or save). The colors are in resources/css/tema.css;
 * for the custom one the chosen colors are passed, which become the inline style of <html>.
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
