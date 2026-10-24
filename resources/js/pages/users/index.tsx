import { FlashMessage } from '@/components/flash-message';
import { roleLabels, UserFormSheet } from '@/components/user-form-sheet';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
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
            <Head title="Utenti" />

            <div className="flex flex-col gap-4 p-4">
                <FlashMessage />

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Utenti</h1>
                    <Button onClick={openCreate}>
                        <Plus /> Nuovo utente
                    </Button>
                </div>

                {users.length === 0 ? (
                    <div className="border-sidebar-border/70 flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <p className="text-muted-foreground">Nessun utente – invita il primo</p>
                        <Button onClick={openCreate}>Invita utente</Button>
                    </div>
                ) : (
                    <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Nome</th>
                                    <th className="px-4 py-3 font-medium">Email</th>
                                    <th className="px-4 py-3 font-medium">Ruolo</th>
                                    <th className="px-4 py-3 font-medium">Area</th>
                                    <th className="px-4 py-3 font-medium">Capacità</th>
                                    <th className="px-4 py-3 font-medium">Stato</th>
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
                                        <td className="px-4 py-3">{roleLabels[user.role]}</td>
                                        <td className="px-4 py-3">{user.area_name ?? '—'}</td>
                                        <td className="px-4 py-3 tabular-nums">{user.daily_capacity} h</td>
                                        <td className="px-4 py-3">{user.status === 'attivo' ? '✅ Attivo' : '⏳ Invito in attesa'}</td>
                                        <td className="px-4 py-3 text-right">
                                            {user.can_edit && (
                                                <Button variant="ghost" size="sm" onClick={() => openEdit(user)} aria-label={`Modifica ${user.name} ${user.surname}`}>
                                                    <Pencil /> Modifica
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
