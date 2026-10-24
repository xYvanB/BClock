import { ClientFormSheet } from '@/components/client-form-sheet';
import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/lib/i18n';
import { type BreadcrumbItem, type ClientListItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clienti', href: '/clients' }];

export default function ClientsIndex({ clients }: { clients: ClientListItem[] }) {
    const [sheetOpen, setSheetOpen] = useState(false);
    const [editing, setEditing] = useState<ClientListItem | null>(null);

    const openCreate = () => {
        setEditing(null);
        setSheetOpen(true);
    };

    const openEdit = (client: ClientListItem) => {
        setEditing(client);
        setSheetOpen(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Clienti')} />

            <div className="flex flex-col gap-4 p-4">
                <FlashMessage />

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">{t('Clienti')}</h1>
                    <Button onClick={openCreate}>
                        <Plus /> {t('Nuovo cliente')}
                    </Button>
                </div>

                {clients.length === 0 ? (
                    <div className="border-sidebar-border/70 flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <p className="text-muted-foreground">{t('Nessun cliente – crea il primo')}</p>
                        <Button onClick={openCreate}>{t('Crea cliente')}</Button>
                    </div>
                ) : (
                    <div className="border-sidebar-border/70 overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">{t('Ragione sociale')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Progetti attivi')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Referente principale')}</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {clients.map((client) => (
                                    <tr key={client.id} className="border-sidebar-border/70 border-t">
                                        <td className="px-4 py-3 font-medium">
                                            <Link href={route('clients.show', client.id)} className="hover:underline">
                                                {client.company_name}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3">{client.active_projects_count}</td>
                                        <td className="text-muted-foreground px-4 py-3">{client.primary_contact ?? '—'}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => openEdit(client)}
                                                aria-label={t('Modifica :name', { name: client.company_name })}
                                            >
                                                <Pencil /> {t('Modifica')}
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <ClientFormSheet open={sheetOpen} client={editing} onClose={() => setSheetOpen(false)} />
        </AppLayout>
    );
}
