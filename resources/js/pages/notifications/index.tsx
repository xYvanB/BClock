import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/lib/i18n';
import { formatNotificationDate } from '@/lib/notifications';
import { type AppNotification, type BreadcrumbItem, type Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifiche', href: '/notifications' }];

interface Props {
    list: Paginated<AppNotification>;
    filter: 'all' | 'unread';
}

/** Full list of the person's notifications, with an "unread" filter and pagination. */
export default function NotificationsIndex({ list, filter }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Notifiche')} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-2xl font-semibold">{t('Notifiche')}</h1>
                    <div className="flex items-center gap-2">
                        <Button variant={filter === 'all' ? 'default' : 'outline'} size="sm" asChild>
                            <Link href={route('notifications.index')}>{t('Tutte')}</Link>
                        </Button>
                        <Button variant={filter === 'unread' ? 'default' : 'outline'} size="sm" asChild>
                            <Link href={route('notifications.index', { filter: 'unread' })}>{t('Non lette')}</Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}
                        >
                            {t('Segna tutte come lette')}
                        </Button>
                    </div>
                </div>

                {list.data.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        {filter === 'unread' ? t('Nessuna notifica da leggere.') : t('Nessuna notifica.')}
                    </p>
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

                {list.last_page > 1 && (
                    <nav className="flex items-center justify-between text-sm" aria-label={t('Pagine')}>
                        {list.prev_page_url ? (
                            <Link href={list.prev_page_url} className="underline">
                                {t('← Più recenti')}
                            </Link>
                        ) : (
                            <span />
                        )}
                        <span className="text-muted-foreground">
                            {t('Pagina :current di :last', { current: list.current_page, last: list.last_page })}
                        </span>
                        {list.next_page_url ? (
                            <Link href={list.next_page_url} className="underline">
                                {t('Meno recenti →')}
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
