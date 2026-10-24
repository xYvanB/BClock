import { ActivityFormSheet } from '@/components/activity-form-sheet';
import { FlashMessage } from '@/components/flash-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { statusLabel } from '@/lib/status';
import { formatDay, formatMinutes } from '@/lib/time';
import { type ActivityListItem, type BreadcrumbItem, type PersonOption, type ProjectOption } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Attività', href: '/activities' }];

interface ActivitiesIndexProps {
    activities: ActivityListItem[];
    projects: ProjectOption[];
    assignees: PersonOption[];
    can_create: boolean;
}

export default function ActivitiesIndex({ activities, projects, assignees, can_create }: ActivitiesIndexProps) {
    const [sheetOpen, setSheetOpen] = useState(false);
    const [editing, setEditing] = useState<ActivityListItem | null>(null);

    const openCreate = () => {
        setEditing(null);
        setSheetOpen(true);
    };

    const openEdit = (activity: ActivityListItem) => {
        setEditing(activity);
        setSheetOpen(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Attività" />

            <div className="flex flex-col gap-4 p-4">
                <FlashMessage />

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Attività</h1>
                    {can_create && (
                        <Button onClick={openCreate}>
                            <Plus /> Nuova attività
                        </Button>
                    )}
                </div>

                {activities.length === 0 ? (
                    <div className="border-sidebar-border/70 flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <p className="text-muted-foreground">Nessuna attività{can_create && ' – crea la prima'}</p>
                        {can_create && <Button onClick={openCreate}>Crea attività</Button>}
                    </div>
                ) : (
                    <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Nome</th>
                                    <th className="px-4 py-3 font-medium">Progetto</th>
                                    <th className="px-4 py-3 font-medium">Cliente</th>
                                    <th className="px-4 py-3 font-medium">Assegnatari</th>
                                    <th className="px-4 py-3 font-medium">Date</th>
                                    <th className="px-4 py-3 font-medium">Ore (usate / previste)</th>
                                    <th className="px-4 py-3 font-medium">Stato</th>
                                    <th className="px-4 py-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {activities.map((activity) => {
                                    const overBudget = activity.used_minutes > activity.planned_hours * 60;

                                    return (
                                        <tr key={activity.id} className="border-sidebar-border/70 border-t">
                                            <td className="px-4 py-3 font-medium">{activity.name}</td>
                                            <td className="px-4 py-3">
                                                <Link href={route('projects.show', activity.project_id)} className="hover:underline">
                                                    {activity.project_name}
                                                </Link>
                                            </td>
                                            <td className="text-muted-foreground px-4 py-3">{activity.client_name}</td>
                                            <td className="px-4 py-3">{activity.assignees.map((person) => person.name).join(', ')}</td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {formatDay(activity.starts_on)} → {formatDay(activity.due_on)}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <span className="inline-flex items-center gap-1">
                                                    {overBudget && <AlertTriangle className="size-4" aria-label="Ore previste superate" />}
                                                    {formatMinutes(activity.used_minutes)} / {formatMinutes(activity.planned_hours * 60)}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">{statusLabel(activity.status)}</td>
                                            <td className="px-4 py-3 text-right">
                                                {activity.can_update && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => openEdit(activity)}
                                                        aria-label={`Modifica ${activity.name}`}
                                                    >
                                                        <Pencil /> Modifica
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <ActivityFormSheet open={sheetOpen} activity={editing} projects={projects} assignees={assignees} onClose={() => setSheetOpen(false)} />
        </AppLayout>
    );
}
