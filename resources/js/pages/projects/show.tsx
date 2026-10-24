import { ActivityFormSheet } from '@/components/activity-form-sheet';
import { FlashMessage } from '@/components/flash-message';
import { HoursProgress } from '@/components/hours-progress';
import { ProjectFormSheet } from '@/components/project-form-sheet';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { statusLabel } from '@/lib/status';
import { formatDay, formatMinutes } from '@/lib/time';
import {
    type ActivityListItem,
    type BreadcrumbItem,
    type ClientOption,
    type PersonOption,
    type ProjectDetail,
    type ProjectTimeEntry,
    type SharedData,
} from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Archive, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface ProjectsShowProps {
    project: ProjectDetail;
    activities: ActivityListItem[];
    time_entries: ProjectTimeEntry[];
    clients: ClientOption[];
    users: PersonOption[];
    assignees: PersonOption[];
    can_create_activity: boolean;
}

const tabs = [
    { id: 'overview', label: 'Panoramica' },
    { id: 'activities', label: 'Attività' },
    { id: 'members', label: 'Membri' },
    { id: 'hours', label: 'Ore' },
] as const;

type TabId = (typeof tabs)[number]['id'];

type Confirming = 'archive' | 'delete' | null;

export default function ProjectsShow({ project, activities, time_entries, clients, users, assignees, can_create_activity }: ProjectsShowProps) {
    const { errors } = usePage<SharedData>().props as unknown as { errors: Record<string, string | undefined> };
    const [tab, setTab] = useState<TabId>('overview');
    const [editOpen, setEditOpen] = useState(false);
    const [activityOpen, setActivityOpen] = useState(false);
    const [editingActivity, setEditingActivity] = useState<ActivityListItem | null>(null);
    const [confirming, setConfirming] = useState<Confirming>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Progetti', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
    ];

    const isArchived = project.status === 'archiviato';
    const hasHours = project.used_minutes > 0;

    const openNewActivity = () => {
        setEditingActivity(null);
        setActivityOpen(true);
    };

    const openEditActivity = (activity: ActivityListItem) => {
        setEditingActivity(activity);
        setActivityOpen(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={project.name} />

            <div className="flex flex-col gap-6 p-4">
                <FlashMessage />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{project.name}</h1>
                        <p className="text-muted-foreground text-sm">
                            {project.client_name} · {statusLabel(project.status)}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={() => setEditOpen(true)}>
                            <Pencil /> Modifica
                        </Button>
                        {!isArchived && (
                            <Button variant="outline" onClick={() => setConfirming('archive')}>
                                <Archive /> Archivia
                            </Button>
                        )}
                        {!hasHours && (
                            <Button variant="outline" onClick={() => setConfirming('delete')}>
                                <Trash2 /> Elimina
                            </Button>
                        )}
                    </div>
                </div>

                {errors.project && (
                    <div role="alert" className="border-destructive/50 bg-destructive/10 rounded-md border p-3 text-sm">
                        {errors.project}
                    </div>
                )}

                {confirming === 'archive' && (
                    <div role="alert" className="border-destructive/50 bg-destructive/10 grid gap-2 rounded-md border p-3 text-sm">
                        <p>Archiviando il progetto vengono archiviate anche le sue attività e non si potranno più registrare ore su di esse.</p>
                        <div className="flex justify-end gap-2">
                            <Button variant="outline" size="sm" onClick={() => setConfirming(null)}>
                                Annulla
                            </Button>
                            <Button variant="destructive" size="sm" onClick={() => router.post(route('projects.archive', project.id))}>
                                Archivia
                            </Button>
                        </div>
                    </div>
                )}

                {confirming === 'delete' && (
                    <div role="alert" className="border-destructive/50 bg-destructive/10 grid gap-2 rounded-md border p-3 text-sm">
                        <p>
                            Eliminare definitivamente il progetto{activities.length > 0 && ` e le sue ${activities.length} attività`}? L'operazione
                            non si può annullare.
                        </p>
                        <div className="flex justify-end gap-2">
                            <Button variant="outline" size="sm" onClick={() => setConfirming(null)}>
                                Annulla
                            </Button>
                            <Button variant="destructive" size="sm" onClick={() => router.delete(route('projects.destroy', project.id))}>
                                Elimina
                            </Button>
                        </div>
                    </div>
                )}

                <div role="tablist" className="border-sidebar-border/70 flex gap-1 border-b">
                    {tabs.map((item) => (
                        <button
                            key={item.id}
                            role="tab"
                            type="button"
                            aria-selected={tab === item.id}
                            onClick={() => setTab(item.id)}
                            className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium ${tab === item.id ? 'border-primary' : 'text-muted-foreground border-transparent'}`}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                {tab === 'overview' && (
                    <section className="grid gap-6">
                        <dl className="grid gap-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground">Cliente</dt>
                                <dd className="font-medium">{project.client_name}</dd>
                            </div>
                            {project.template_name && (
                                <div>
                                    <dt className="text-muted-foreground">Template di origine</dt>
                                    <dd className="font-medium">{project.template_name}</dd>
                                </div>
                            )}
                            <div>
                                <dt className="text-muted-foreground">Date</dt>
                                <dd className="font-medium">
                                    {formatDay(project.starts_on)} → {formatDay(project.due_on)}
                                </dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-muted-foreground">Descrizione</dt>
                                <dd>{project.description ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Avanzamento ore</dt>
                                <dd>
                                    <HoursProgress usedMinutes={project.used_minutes} plannedHours={project.planned_hours} />
                                </dd>
                            </div>
                        </dl>

                        <div className="flex flex-col gap-2">
                            <h2 className="text-lg font-medium">Referenti</h2>
                            {project.contacts.length === 0 ? (
                                <p className="text-muted-foreground text-sm">Nessun referente scelto.</p>
                            ) : (
                                <ul className="border-sidebar-border/70 divide-sidebar-border/70 divide-y rounded-xl border text-sm">
                                    {project.contacts.map((contact) => (
                                        <li key={contact.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                                            <span className="font-medium">{contact.name}</span>
                                            <span className="text-muted-foreground">
                                                {[contact.email, contact.phone].filter(Boolean).join(' · ') || '—'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                )}

                {tab === 'activities' && (
                    <section className="flex flex-col gap-3">
                        {can_create_activity && !isArchived && (
                            <div className="flex justify-end">
                                <Button onClick={openNewActivity}>
                                    <Plus /> Nuova attività
                                </Button>
                            </div>
                        )}
                        {activities.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Nessuna attività in questo progetto.</p>
                        ) : (
                            <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">Nome</th>
                                            <th className="px-4 py-3 font-medium">Assegnatari</th>
                                            <th className="px-4 py-3 font-medium">Date</th>
                                            <th className="px-4 py-3 font-medium">Ore</th>
                                            <th className="px-4 py-3 font-medium">Stato</th>
                                            <th className="px-4 py-3" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {activities.map((activity) => (
                                            <tr key={activity.id} className="border-sidebar-border/70 border-t">
                                                <td className="px-4 py-3 font-medium">{activity.name}</td>
                                                <td className="px-4 py-3">{activity.assignees.map((person) => person.name).join(', ')}</td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {formatDay(activity.starts_on)} → {formatDay(activity.due_on)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <HoursProgress usedMinutes={activity.used_minutes} plannedHours={activity.planned_hours} />
                                                </td>
                                                <td className="px-4 py-3">{statusLabel(activity.status)}</td>
                                                <td className="px-4 py-3 text-right">
                                                    {activity.can_update && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => openEditActivity(activity)}
                                                            aria-label={`Modifica ${activity.name}`}
                                                        >
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
                    </section>
                )}

                {tab === 'members' && (
                    <section>
                        {project.members.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Nessun membro.</p>
                        ) : (
                            <ul className="border-sidebar-border/70 divide-sidebar-border/70 divide-y rounded-xl border text-sm">
                                {project.members.map((member) => (
                                    <li key={member.id} className="px-4 py-3 font-medium">
                                        {member.name}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}

                {tab === 'hours' && (
                    <section>
                        {time_entries.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Nessuna ora registrata.</p>
                        ) : (
                            <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">Data</th>
                                            <th className="px-4 py-3 font-medium">Persona</th>
                                            <th className="px-4 py-3 font-medium">Attività</th>
                                            <th className="px-4 py-3 font-medium">Durata</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {time_entries.map((entry) => (
                                            <tr key={entry.id} className="border-sidebar-border/70 border-t">
                                                <td className="px-4 py-3 whitespace-nowrap">{formatDay(entry.date)}</td>
                                                <td className="px-4 py-3">{entry.user_name}</td>
                                                <td className="px-4 py-3">{entry.activity_name}</td>
                                                <td className="px-4 py-3">{formatMinutes(entry.duration_minutes)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                )}
            </div>

            <ProjectFormSheet open={editOpen} project={project} clients={clients} users={users} onClose={() => setEditOpen(false)} />
            <ActivityFormSheet
                open={activityOpen}
                activity={editingActivity}
                projects={[{ id: project.id, name: project.name, client_name: project.client_name, due_on: project.due_on }]}
                assignees={assignees}
                dependencyOptions={activities.map((candidate) => ({ id: candidate.id, name: candidate.name, project_id: candidate.project_id }))}
                defaultProjectId={project.id}
                onClose={() => setActivityOpen(false)}
            />
        </AppLayout>
    );
}
