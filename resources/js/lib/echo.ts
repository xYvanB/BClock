import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echo: Echo<'reverb'> | null = null;

/**
 * Connection to the WebSocket server (Reverb), created on first request. Without a configured key or if the connection
 * does not start it returns null: the app works anyway, just without real-time updates.
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
