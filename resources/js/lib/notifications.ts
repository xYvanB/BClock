import { intlLocale } from '@/lib/i18n';
/** Date and time of a notification in the browser's timezone, e.g. "24/10, 09:12". */
export function formatNotificationDate(iso: string): string {
    return new Date(iso).toLocaleString(intlLocale(), { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}
