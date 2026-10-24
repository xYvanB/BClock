import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { getEcho } from '@/lib/echo';
import { t } from '@/lib/i18n';
import { formatNotificationDate } from '@/lib/notifications';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect } from 'react';

/**
 * Topbar bell: dot with the number of unread notifications and a panel with the latest ones.
 * A click on a notification marks it as read and opens the linked page.
 */
export function NotificationBell() {
    const { notifications, auth } = usePage<SharedData>().props;
    const userId = auth.user?.id;

    // Real time: when the server announces new notifications, the bell reloads at once.
    useEffect(() => {
        const echo = userId === undefined ? null : getEcho();

        if (echo === null || userId === undefined) {
            return;
        }

        const channel = echo.private(`App.Models.User.${userId}`).listen('.notifications.changed', () => router.reload({ only: ['notifications'] }));

        return () => {
            channel.stopListening('.notifications.changed');
            echo.leave(`App.Models.User.${userId}`);
        };
    }, [userId]);

    if (notifications === null) {
        return null;
    }

    const unread = notifications.unread_count;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="relative gap-1" aria-label={t('Notifiche: :count non lette', { count: unread })}>
                    <Bell className="size-4" />
                    {unread > 0 && (
                        <span className="bg-primary text-primary-foreground inline-flex min-w-5 items-center justify-center rounded-full px-1.5 text-xs">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-96 max-w-[calc(100vw-2rem)] p-2">
                <div className="flex items-center justify-between">
                    <DropdownMenuLabel>{t('Notifiche')}</DropdownMenuLabel>
                    {unread > 0 && (
                        <Button variant="link" size="sm" onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}>
                            {t('Segna tutte come lette')}
                        </Button>
                    )}
                </div>
                <DropdownMenuSeparator />
                {notifications.items.length === 0 ? (
                    <p className="text-muted-foreground px-2 py-3 text-sm">{t('Nessuna notifica.')}</p>
                ) : (
                    <ul className="grid gap-2">
                        {notifications.items.map((notification) => (
                            <li key={notification.id}>
                                <button
                                    type="button"
                                    onClick={() => router.post(route('notifications.read', notification.id))}
                                    className={`border-sidebar-border/70 w-full rounded-md border p-2 text-left ${notification.is_read ? 'opacity-70' : 'bg-accent/40'}`}
                                >
                                    <p className={`text-sm ${notification.is_read ? '' : 'font-medium'}`}>
                                        {!notification.is_read && <span aria-label={t('Non letta')}>● </span>}
                                        {notification.message}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {formatNotificationDate(notification.created_at)}
                                        {notification.actor_name && ` · da ${notification.actor_name}`}
                                    </p>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
                <DropdownMenuSeparator />
                <Link href={route('notifications.index')} className="text-primary block px-2 py-2 text-sm underline-offset-4 hover:underline">
                    {t('Vedi tutte le notifiche')}
                </Link>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
