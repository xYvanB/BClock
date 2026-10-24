import { FlashMessage } from '@/components/flash-message';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { intlLocale, t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'App collegate', href: '/settings/connected-apps' }];

interface ConnectedApp {
    id: string;
    name: string;
    connected_at: string;
    last_used_at: string | null;
}

interface Props {
    apps: ConnectedApp[];
    server_url: string;
}

const formatDateTime = (value: string) =>
    new Intl.DateTimeFormat(intlLocale(), { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));

/** The AI apps authorized through OAuth, with the address to give them and the way to revoke each one. */
export default function ConnectedApps({ apps, server_url }: Props) {
    const [toRevoke, setToRevoke] = useState<ConnectedApp | null>(null);
    const [processing, setProcessing] = useState(false);

    const revoke = () => {
        if (toRevoke === null) {
            return;
        }

        router.delete(route('connected-apps.destroy', toRevoke.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setToRevoke(null);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('App collegate')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <FlashMessage />
                    <HeadingSmall title={t('App collegate')} description={t('Le app AI che hanno accesso a BClock a tuo nome.')} />

                    {apps.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('Non hai collegato nessuna app')}</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground text-left">
                                    <th className="py-2 font-normal">{t('App')}</th>
                                    <th className="py-2 font-normal">{t('Collegata il')}</th>
                                    <th className="py-2 font-normal">{t('Ultimo uso')}</th>
                                    <th className="py-2 text-right font-normal">{t('Azioni')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {apps.map((app) => (
                                    <tr key={app.id} className="border-t">
                                        <td className="py-3">{app.name}</td>
                                        <td>{formatDateTime(app.connected_at)}</td>
                                        <td>{app.last_used_at ? formatDateTime(app.last_used_at) : t('Mai')}</td>
                                        <td className="text-right">
                                            <Button type="button" variant="outline" size="sm" onClick={() => setToRevoke(app)}>
                                                {t('Revoca accesso')}
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}

                    <p className="text-muted-foreground text-xs">
                        {t("Indirizzo del server MCP da inserire nell'app AI:")} <code className="text-foreground">{server_url}</code>
                    </p>
                </div>

                <Dialog open={toRevoke !== null} onOpenChange={(open) => !open && setToRevoke(null)}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t('Revoca accesso')}</DialogTitle>
                            <DialogDescription>
                                {t("Revocare l'accesso di :app? L'app non potrà più usare BClock a tuo nome.", { app: toRevoke?.name ?? '' })}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setToRevoke(null)}>
                                {t('Annulla')}
                            </Button>
                            <Button type="button" variant="destructive" disabled={processing} onClick={revoke}>
                                {t('Revoca')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </SettingsLayout>
        </AppLayout>
    );
}
