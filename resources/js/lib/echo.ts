import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echo: Echo<'reverb'> | null = null;

/**
 * Connessione al server WebSocket (Reverb), creata alla prima richiesta. Senza chiave configurata o se la connessione
 * non parte restituisce null: l'app funziona lo stesso, solo senza aggiornamenti in tempo reale.
 */
export function getEcho(): Echo<'reverb'> | null {
    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (!key) {
        return null;
    }

    if (echo === null) {
        try {
            (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;
            const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';

            echo = new Echo({
                broadcaster: 'reverb',
                key,
                wsHost: import.meta.env.VITE_REVERB_HOST,
                wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
                wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
                forceTLS: scheme === 'https',
                enabledTransports: ['ws', 'wss'],
            });
        } catch {
            return null;
        }
    }

    return echo;
}
