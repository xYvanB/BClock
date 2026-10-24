import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { formatNotificationDate } from '@/lib/notifications';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';

/**
 * Campanello nella topbar: pallino col numero delle notifiche non lette e pannello con le ultime.
 * Un clic su una notifica la segna come letta e apre la pagina collegata.
 */
export function NotificationBell() {
    const { notifications } = usePage<SharedData>().props;

    if (notifications === null) {
        return null;
    }

    const unread = notifications.unread_count;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="relative gap-1" aria-label={`Notifiche: ${unread} non lette`}>
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
                    <DropdownMenuLabel>Notifiche</DropdownMenuLabel>
                    {unread > 0 && (
                        <Button variant="link" size="sm" onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}>
                            Segna tutte come lette
                        </Button>
                    )}
                </div>
                <DropdownMenuSeparator />
                {notifications.items.length === 0 ? (
                    <p className="text-muted-foreground px-2 py-3 text-sm">Nessuna notifica.</p>
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
                                        {!notification.is_read && <span aria-label="Non letta">● </span>}
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
                    Vedi tutte le notifiche
                </Link>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
