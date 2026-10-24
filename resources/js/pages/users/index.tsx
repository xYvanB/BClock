import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import { roleLabels, UserFormSheet } from '@/components/user-form-sheet';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/lib/i18n';
import { type AreaOption, type BreadcrumbItem, type ManagedUser, type Role } from '@/types';
import { Head } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Utenti', href: '/users' }];

interface Props {
    users: ManagedUser[];
    areas: AreaOption[];
    assignable_roles: Role[];
    fixed_area_id: number | null;
}

export default function UsersIndex({ users, areas, assignable_roles, fixed_area_id }: Props) {
    const [sheetOpen, setSheetOpen] = useState(false);
    const [editing, setEditing] = useState<ManagedUser | null>(null);

    const openCreate = () => {
        setEditing(null);
        setSheetOpen(true);
    };

    const openEdit = (user: ManagedUser) => {
        setEditing(user);
        setSheetOpen(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Utenti')} />

            <div className="flex flex-col gap-4 p-4">
                <FlashMessage />

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">{t('Utenti')}</h1>
                    <Button onClick={openCreate}>
                        <Plus /> {t('Nuovo utente')}
                    </Button>
                </div>

                {users.length === 0 ? (
                    <div className="border-sidebar-border/70 flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <p className="text-muted-foreground">{t('Nessun utente – invita il primo')}</p>
                        <Button onClick={openCreate}>{t('Invita utente')}</Button>
                    </div>
                ) : (
                    <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">{t('Nome')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Email')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Ruolo')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Area')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Capacità')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Stato')}</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {users.map((user) => (
                                    <tr key={user.id} className="border-sidebar-border/70 border-t">
                                        <td className="px-4 py-3 font-medium">
                                            {user.name} {user.surname}
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">{user.email}</td>
                                        <td className="px-4 py-3">{t(roleLabels[user.role])}</td>
                                        <td className="px-4 py-3">{user.area_name ?? '—'}</td>
                                        <td className="px-4 py-3 tabular-nums">{user.daily_capacity} h</td>
                                        <td className="px-4 py-3">{user.status === 'attivo' ? t('✅ Attivo') : t('⏳ Invito in attesa')}</td>
                                        <td className="px-4 py-3 text-right">
                                            {user.can_edit && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => openEdit(user)}
                                                    aria-label={t('Modifica :name', { name: `${user.name} ${user.surname}` })}
                                                >
                                                    <Pencil /> {t('Modifica')}
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <UserFormSheet
                open={sheetOpen}
                user={editing}
                areas={areas}
                assignableRoles={assignable_roles}
                fixedAreaId={fixed_area_id}
                onClose={() => setSheetOpen(false)}
            />
        </AppLayout>
    );
}
