/** Data e ora di una notifica nel fuso del browser, es. "24/10, 09:12". */
export function formatNotificationDate(iso: string): string {
    return new Date(iso).toLocaleString('it-IT', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}
