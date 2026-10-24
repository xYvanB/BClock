import { intlLocale } from '@/lib/i18n';
import { type TimeFormat } from '@/types';

/** 210 → "3h 30m", 120 → "2h", 45 → "45m". */
export function formatMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) {
        return `${rest}m`;
    }

    return rest === 0 ? `${hours}h` : `${hours}h ${rest}m`;
}

/** "14:30" → "14:30" (24h) or "2:30 PM" (12h), according to the user's preference. */
export function formatTime(value: string, format: TimeFormat): string {
    if (format === '24h') {
        return value;
    }

    const [hours, minutes] = value.split(':').map(Number);
    const suffix = hours >= 12 ? 'PM' : 'AM';
    const twelve = hours % 12 === 0 ? 12 : hours % 12;

    return `${twelve}:${String(minutes).padStart(2, '0')} ${suffix}`;
}

/** "2026-10-19" → "lunedì 19 ottobre" (Italian locale). */
export function formatDay(date: string, locale = intlLocale()): string {
    return new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date(`${date}T00:00:00`));
}

/** Today's local date in YYYY-MM-DD format. */
export function today(): string {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}
