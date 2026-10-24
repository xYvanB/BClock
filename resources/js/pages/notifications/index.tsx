import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatNotificationDate } from '@/lib/notifications';
import { type AppNotification, type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifiche', href: '/notifications' }];

interface Props {
    list: Paginated<AppNotification>;
    filter: 'all' | 'unread';
}

/** Elenco completo delle notifiche della persona, con filtro "non lette" e paginazione. */
export default function NotificationsIndex({ list, filter }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifiche" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-2xl font-semibold">Notifiche</h1>
                    <div className="flex items-center gap-2">
                        <Button variant={filter === 'all' ? 'default' : 'outline'} size="sm" asChild>
                            <Link href={route('notifications.index')}>Tutte</Link>
                        </Button>
                        <Button variant={filter === 'unread' ? 'default' : 'outline'} size="sm" asChild>
                            <Link href={route('notifications.index', { filter: 'unread' })}>Non lette</Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}
                        >
                            Segna tutte come lette
                        </Button>
                    </div>
                </div>

                {list.data.length === 0 ? (
                    <p className="text-muted-foreground text-sm">{filter === 'unread' ? 'Nessuna notifica da leggere.' : 'Nessuna notifica.'}</p>
                ) : (
                    <ul className="grid gap-2">
                        {list.data.map((notification) => (
                            <li key={notification.id}>
                                <button
                                    type="button"
                                    onClick={() => router.post(route('notifications.read', notification.id))}
                                    className={`border-sidebar-border/70 w-full rounded-md border p-3 text-left ${notification.is_read ? 'opacity-70' : 'bg-accent/40'}`}
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

                {list.last_page > 1 && (
                    <nav className="flex items-center justify-between text-sm" aria-label="Pagine">
                        {list.prev_page_url ? (
                            <Link href={list.prev_page_url} className="underline">
                                ← Più recenti
                            </Link>
                        ) : (
                            <span />
                        )}
                        <span className="text-muted-foreground">
                            Pagina {list.current_page} di {list.last_page}
                        </span>
                        {list.next_page_url ? (
                            <Link href={list.next_page_url} className="underline">
                                Meno recenti →
                            </Link>
                        ) : (
                            <span />
                        )}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
