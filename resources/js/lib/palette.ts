import { type Palette } from '@/types';

/** Palette con sfondo scuro: attivano anche la classe `dark` di Tailwind. */
const darkPalettes: Palette[] = ['scuro', 'personalizzata'];

/** Applica subito una palette al documento (anteprima o salvataggio). I colori sono in resources/css/tema.css. */
export function applyPalette(palette: Palette): void {
    document.documentElement.dataset.tema = palette;
    document.documentElement.classList.toggle('dark', darkPalettes.includes(palette));
}
