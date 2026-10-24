import { ClientFormSheet } from '@/components/client-form-sheet';
import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/lib/i18n';
import { statusLabel } from '@/lib/status';
import { type BreadcrumbItem, type ClientDetail } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';

export default function ClientsShow({ client }: { client: ClientDetail }) {
    const [sheetOpen, setSheetOpen] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Clienti', href: '/clients' },
        { title: client.company_name, href: `/clients/${client.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={client.company_name} />

            <div className="flex flex-col gap-6 p-4">
                <FlashMessage />

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">{client.company_name}</h1>
                    <Button variant="outline" onClick={() => setSheetOpen(true)}>
                        <Pencil /> {t('Modifica')}
                    </Button>
                </div>

                <section className="flex flex-col gap-2">
                    <h2 className="text-lg font-medium">{t('Referenti')}</h2>
                    {client.contacts.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('Nessun referente.')}</p>
                    ) : (
                        <ul className="border-sidebar-border/70 divide-sidebar-border/70 divide-y rounded-xl border text-sm">
                            {client.contacts.map((contact) => (
                                <li key={contact.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                                    <span className="font-medium">
                                        {contact.name}
                                        {contact.is_primary && (
                                            <span className="text-muted-foreground ml-2 text-xs font-normal">{t('principale')}</span>
                                        )}
                                    </span>
                                    <span className="text-muted-foreground">{[contact.email, contact.phone].filter(Boolean).join(' · ') || '—'}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="flex flex-col gap-2">
                    <h2 className="text-lg font-medium">{t('Progetti')}</h2>
                    {client.projects.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('Nessun progetto collegato.')}</p>
                    ) : (
                        <ul className="border-sidebar-border/70 divide-sidebar-border/70 divide-y rounded-xl border text-sm">
                            {client.projects.map((project) => (
                                <li key={project.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                                    <Link href={route('projects.show', project.id)} className="font-medium hover:underline">
                                        {project.name}
                                    </Link>
                                    <span className="text-muted-foreground">
                                        {statusLabel(project.status)}
                                        {project.due_on && ` · scadenza ${project.due_on}`}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <ClientFormSheet open={sheetOpen} client={client} onClose={() => setSheetOpen(false)} />
        </AppLayout>
    );
}
