import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { activitySchema, type ActivityFormData } from '@/lib/schemas/activity';
import { statusOptions } from '@/lib/status';
import { flattenZodErrors } from '@/lib/zod-errors';
import { type ActivityListItem, type DependencyOption, type PersonOption, type ProjectOption } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { Archive, Trash2 } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

interface ActivityFormSheetProps {
    open: boolean;
    activity: ActivityListItem | null;
    projects: ProjectOption[];
    assignees: PersonOption[];
    /** Attività del progetto da cui questa può dipendere (fine → inizio). */
    dependencyOptions?: DependencyOption[];
    /** Progetto preselezionato alla creazione (es. dal dettaglio progetto). */
    defaultProjectId?: number;
    onClose: () => void;
}

const fieldClass =
    'border-input bg-background focus-visible:ring-ring h-9 w-full rounded-md border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none disabled:opacity-60';

const formatDate = (iso: string) => iso.split('-').reverse().join('/');

/** Drawer laterale per creare o modificare un'attività (F8). Il progetto non si cambia dopo la creazione. */
export function ActivityFormSheet({
    open,
    activity,
    projects,
    assignees,
    dependencyOptions = [],
    defaultProjectId,
    onClose,
}: ActivityFormSheetProps) {
    const { data, setData, post, put, processing, errors, setError, clearErrors, reset } = useForm<ActivityFormData>({
        project_id: '',
        name: '',
        description: '',
        starts_on: '',
        due_on: '',
        planned_hours: '',
        status: 'da_fare',
        assignee_ids: [],
        dependency_ids: [],
    });

    // Gli errori per singolo assegnatario del server hanno la chiave `assignee_ids.N`.
    const assigneeError = Object.entries(errors).find(([key]) => key.startsWith('assignee_ids'))?.[1];

    const project = projects.find((candidate) => String(candidate.id) === data.project_id);

    const [confirming, setConfirming] = useState<'archive' | 'delete' | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            clearErrors();
            setConfirming(null);
            setActionError(null);
            setData({
                project_id: activity ? String(activity.project_id) : defaultProjectId ? String(defaultProjectId) : '',
                name: activity?.name ?? '',
                description: activity?.description ?? '',
                starts_on: activity?.starts_on ?? '',
                due_on: activity?.due_on ?? '',
                planned_hours: activity ? String(activity.planned_hours) : '',
                status: activity?.status ?? 'da_fare',
                assignee_ids: activity?.assignees.map((person) => person.id) ?? [],
                dependency_ids: activity?.dependency_ids ?? [],
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, activity]);

    /** Archivia o elimina l'attività in modifica; un rifiuto del server (ore o timer) resta visibile nel drawer. */
    const runAction = (action: 'archive' | 'delete') => {
        if (!activity) {
            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (serverErrors: Record<string, string>) => {
                setConfirming(null);
                setActionError(serverErrors.activity ?? 'Operazione non riuscita.');
            },
        };

        if (action === 'archive') {
            router.post(route('activities.archive', activity.id), {}, options);
        } else {
            router.delete(route('activities.destroy', activity.id), options);
        }
    };

    const projectDependencyOptions = dependencyOptions.filter(
        (option) => String(option.project_id) === data.project_id && option.id !== activity?.id,
    );

    const toggleDependency = (id: number) =>
        setData(
            'dependency_ids',
            data.dependency_ids.includes(id) ? data.dependency_ids.filter((current) => current !== id) : [...data.dependency_ids, id],
        );

    const dependencyError = Object.entries(errors).find(([key]) => key.startsWith('dependency_ids'))?.[1];

    const toggleAssignee = (id: number) =>
        setData('assignee_ids', data.assignee_ids.includes(id) ? data.assignee_ids.filter((current) => current !== id) : [...data.assignee_ids, id]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        const result = activitySchema.safeParse(data);
        const fieldErrors: Partial<Record<keyof ActivityFormData, string>> = result.success
            ? {}
            : flattenZodErrors<keyof ActivityFormData>(result.error);

        if (!fieldErrors.due_on && project && data.due_on > project.due_on) {
            fieldErrors.due_on = `La scadenza non può superare quella del progetto (${formatDate(project.due_on)}).`;
        }

        if (Object.keys(fieldErrors).length > 0) {
            (Object.keys(fieldErrors) as (keyof ActivityFormData)[]).forEach((field) => setError(field, fieldErrors[field] as string));

            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        };

        if (activity) {
            put(route('activities.update', activity.id), options);
        } else {
            post(route('activities.store'), options);
        }
    };

    return (
        <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
            <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-lg">
                <form onSubmit={submit} className="flex h-full flex-col gap-6" noValidate>
                    <SheetHeader>
                        <SheetTitle>{activity ? 'Modifica attività' : 'Nuova attività'}</SheetTitle>
                        <SheetDescription>Un'attività appartiene a un progetto e ha una o più persone assegnate.</SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-4 px-4">
                        <div className="grid gap-2">
                            <Label htmlFor="activity-project">Progetto</Label>
                            <select
                                id="activity-project"
                                className={fieldClass}
                                value={data.project_id}
                                disabled={activity !== null}
                                onChange={(event) => setData('project_id', event.target.value)}
                            >
                                <option value="">Scegli un progetto</option>
                                {projects.map((candidate) => (
                                    <option key={candidate.id} value={candidate.id}>
                                        {candidate.name} – {candidate.client_name}
                                    </option>
                                ))}
                            </select>
                            {project && <p className="text-muted-foreground text-sm">Scadenza del progetto: {formatDate(project.due_on)}</p>}
                            <InputError message={errors.project_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="activity-name">Nome</Label>
                            <Input
                                id="activity-name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                aria-invalid={Boolean(errors.name)}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="activity-description">Descrizione</Label>
                            <textarea
                                id="activity-description"
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                rows={3}
                                className="border-input bg-background focus-visible:ring-ring rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="activity-starts-on">Inizio</Label>
                                <Input
                                    id="activity-starts-on"
                                    type="date"
                                    value={data.starts_on}
                                    onChange={(event) => setData('starts_on', event.target.value)}
                                    aria-invalid={Boolean(errors.starts_on)}
                                />
                                <InputError message={errors.starts_on} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="activity-due-on">Scadenza</Label>
                                <Input
                                    id="activity-due-on"
                                    type="date"
                                    value={data.due_on}
                                    onChange={(event) => setData('due_on', event.target.value)}
                                    aria-invalid={Boolean(errors.due_on)}
                                />
                                <InputError message={errors.due_on} />
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="activity-hours">Ore previste</Label>
                                <Input
                                    id="activity-hours"
                                    type="number"
                                    min="0"
                                    step="0.5"
                                    value={data.planned_hours}
                                    onChange={(event) => setData('planned_hours', event.target.value)}
                                    aria-invalid={Boolean(errors.planned_hours)}
                                />
                                <InputError message={errors.planned_hours} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="activity-status">Stato</Label>
                                <select
                                    id="activity-status"
                                    className={fieldClass}
                                    value={data.status}
                                    onChange={(event) => setData('status', event.target.value)}
                                >
                                    {statusOptions.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.status} />
                            </div>
                        </div>

                        <fieldset className="grid gap-2">
                            <legend className="text-sm leading-none font-medium">Assegnatari</legend>
                            <div className="border-sidebar-border/70 grid max-h-48 gap-1 overflow-y-auto rounded-md border p-2">
                                {assignees.map((person) => (
                                    <label key={person.id} className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={data.assignee_ids.includes(person.id)}
                                            onChange={() => toggleAssignee(person.id)}
                                        />
                                        {person.name}
                                    </label>
                                ))}
                            </div>
                            <InputError message={assigneeError} />
                        </fieldset>

                        {projectDependencyOptions.length > 0 && (
                            <fieldset className="grid gap-2">
                                <legend className="text-sm leading-none font-medium">Dipende da</legend>
                                <p className="text-muted-foreground text-sm">Se queste attività si spostano nel Gantt, questa si sposta con loro.</p>
                                <div className="border-sidebar-border/70 grid max-h-40 gap-1 overflow-y-auto rounded-md border p-2">
                                    {projectDependencyOptions.map((option) => (
                                        <label key={option.id} className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={data.dependency_ids.includes(option.id)}
                                                onChange={() => toggleDependency(option.id)}
                                            />
                                            {option.name}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={dependencyError} />
                            </fieldset>
                        )}

                        {activity && (
                            <div className="border-sidebar-border/70 grid gap-3 border-t pt-4">
                                <p className="text-sm font-medium">Azioni</p>

                                {actionError && (
                                    <div role="alert" className="border-destructive/50 bg-destructive/10 rounded-md border p-3 text-sm">
                                        {actionError}
                                    </div>
                                )}

                                <div className="flex flex-wrap gap-2">
                                    {activity.status !== 'archiviato' && (
                                        <Button type="button" variant="outline" size="sm" onClick={() => setConfirming('archive')}>
                                            <Archive /> Archivia
                                        </Button>
                                    )}
                                    {activity.used_minutes === 0 && (
                                        <Button type="button" variant="outline" size="sm" onClick={() => setConfirming('delete')}>
                                            <Trash2 /> Elimina
                                        </Button>
                                    )}
                                </div>
                                {activity.used_minutes > 0 && (
                                    <p className="text-muted-foreground text-sm">Ha ore registrate: non si può eliminare, solo archiviare.</p>
                                )}

                                {confirming && (
                                    <div role="alert" className="border-destructive/50 bg-destructive/10 grid gap-2 rounded-md border p-3 text-sm">
                                        <p>
                                            {confirming === 'archive'
                                                ? "Archiviando l'attività non si potranno più registrare ore né avviare timer su di essa."
                                                : "Eliminare definitivamente l'attività? L'operazione non si può annullare."}
                                        </p>
                                        <div className="flex justify-end gap-2">
                                            <Button type="button" variant="outline" size="sm" onClick={() => setConfirming(null)}>
                                                Annulla
                                            </Button>
                                            <Button type="button" variant="destructive" size="sm" onClick={() => runAction(confirming)}>
                                                {confirming === 'archive' ? 'Archivia' : 'Elimina'}
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    <SheetFooter className="flex-row justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>
                            Annulla
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Salva
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
