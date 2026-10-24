import en from '../../../lang/en.json';
import it from '../../../lang/it.json';

type Dictionary = Record<string, string>;

const dictionaries: Record<string, Dictionary> = { en, it };

let currentLocale = 'it';

/** Sets the language of the interface; called before every page render (see app.tsx). */
export function setLocale(locale: string): void {
    currentLocale = locale;
}

export function getLocale(): string {
    return currentLocale;
}

/** BCP 47 tag for `Intl` and `toLocale*String`, e.g. `it-IT`. */
export function intlLocale(): string {
    return currentLocale === 'en' ? 'en-GB' : 'it-IT';
}

/**
 * Translates a text. The source text (Italian, or English for the starter-kit screens) is the key: when the current
 * language has no entry, the key itself is shown. Placeholders look like `:name` and are replaced from `params`.
 */
export function t(key: string, params?: Record<string, string | number>): string {
    let text = dictionaries[currentLocale]?.[key] ?? key;

    if (params) {
        for (const [name, value] of Object.entries(params).sort((a, b) => b[0].length - a[0].length)) {
            text = text.split(`:${name}`).join(String(value));
        }
    }

    return text;
}

/** Decimal separator of the current language: a comma in Italian, a dot in English. */
export function decimalSeparator(): string {
    return currentLocale === 'en' ? '.' : ',';
}
