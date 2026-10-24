import { AreaFormSheet } from '@/components/area-form-sheet';
import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/lib/i18n';
import { type Area, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Aree', href: '/areas' }];

export default function AreasIndex({ areas }: { areas: Area[] }) {
    const [sheetOpen, setSheetOpen] = useState(false);
    const [editing, setEditing] = useState<Area | null>(null);

    const openCreate = () => {
        setEditing(null);
        setSheetOpen(true);
    };

    const openEdit = (area: Area) => {
        setEditing(area);
        setSheetOpen(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Aree')} />

            <div className="flex flex-col gap-4 p-4">
                <FlashMessage />

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">{t('Aree')}</h1>
                    <Button onClick={openCreate}>
                        <Plus /> {t('Nuova area')}
                    </Button>
                </div>

                {areas.length === 0 ? (
                    <div className="border-sidebar-border/70 flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <p className="text-muted-foreground">{t('Nessuna area – crea la prima')}</p>
                        <Button onClick={openCreate}>{t('Crea area')}</Button>
                    </div>
                ) : (
                    <div className="border-sidebar-border/70 overflow-hidden rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">{t('Nome')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Descrizione')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Persone')}</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {areas.map((area) => (
                                    <tr key={area.id} className="border-sidebar-border/70 border-t">
                                        <td className="px-4 py-3 font-medium">{area.name}</td>
                                        <td className="text-muted-foreground px-4 py-3">{area.description}</td>
                                        <td className="px-4 py-3">{area.users_count}</td>
                                        <td className="px-4 py-3 text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => openEdit(area)}
                                                aria-label={t('Modifica :name', { name: area.name })}
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

            <AreaFormSheet open={sheetOpen} area={editing} onClose={() => setSheetOpen(false)} />
        </AppLayout>
    );
}
