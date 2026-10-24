import InputError from '@/components/input-error';
import { TemplatePreview, templateEndDate } from '@/components/template-preview';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { t } from '@/lib/i18n';
import { projectSchema, type ProjectFormData } from '@/lib/schemas/project';
import { statusOptions } from '@/lib/status';
import { flattenZodErrors } from '@/lib/zod-errors';
import { type AssigneeOption, type ClientOption, type PersonOption, type ProjectDetail, type SharedData, type TemplateOption } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

interface ProjectFormSheetProps {
    open: boolean;
    project: ProjectDetail | null;
    clients: ClientOption[];
    users: PersonOption[];
    /** Only at creation, and only for who can create activities (not HR). */
    templates?: TemplateOption[];
    assignees?: AssigneeOption[];
    onClose: () => void;
}

const fieldClass =
    'border-input bg-background focus-visible:ring-ring h-9 w-full rounded-md border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none disabled:opacity-60';

const toggle = (list: number[], id: number) => (list.includes(id) ? list.filter((current) => current !== id) : [...list, id]);

/** Side drawer to create or edit a project. The customer cannot be changed after creation. */
export function ProjectFormSheet({ open, project, clients, users, templates = [], assignees = [], onClose }: ProjectFormSheetProps) {
    const { calendar } = usePage<SharedData>().props;
    const { data, setData, post, put, processing, errors, setError, clearErrors, reset } = useForm<ProjectFormData>({
        client_id: '',
        name: '',
        description: '',
        starts_on: '',
        due_on: '',
        planned_hours: '',
        status: 'da_fare',
        member_ids: [],
        contact_ids: [],
        template_id: '',
        template_assignees: {},
    });

    const client = clients.find((candidate) => String(candidate.id) === data.client_id);
    const errorFor = (prefix: string) => Object.entries(errors).find(([key]) => key.startsWith(prefix))?.[1];

    useEffect(() => {
        if (open) {
            clearErrors();
            setData({
                client_id: project ? String(project.client_id) : '',
                name: project?.name ?? '',
                description: project?.description ?? '',
                starts_on: project?.starts_on ?? '',
                due_on: project?.due_on ?? '',
                planned_hours: project ? String(project.planned_hours) : '',
                status: project?.status ?? 'da_fare',
                member_ids: project?.members.map((member) => member.id) ?? [],
                contact_ids: project?.contacts.map((contact) => contact.id) ?? [],
                template_id: '',
                template_assignees: {},
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, project]);

    const template = templates.find((candidate) => String(candidate.id) === data.template_id);

    const changeClient = (clientId: string) => {
        setData((current) => ({ ...current, client_id: clientId, contact_ids: [] }));
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        const result = projectSchema.safeParse(data);
        const fieldErrors: Record<string, string> = result.success ? {} : (flattenZodErrors<string>(result.error) as Record<string, string>);

        if (template) {
            const end = templateEndDate(template, data.starts_on, calendar);
            if (end !== null && /^\d{4}-\d{2}-\d{2}$/.test(data.due_on) && end > data.due_on) {
                fieldErrors.template_id = t(
                    'Le attività del template finiscono oltre la scadenza del progetto: allunga la scadenza o scegli un altro template.',
                );
            }

            template.activities.forEach((activity) => {
                if ((data.template_assignees[String(activity.id)] ?? []).length === 0) {
                    fieldErrors[`template_assignees.${activity.id}`] = t('Scegli almeno una persona');
                }
            });
        }

        if (Object.keys(fieldErrors).length > 0) {
            Object.entries(fieldErrors).forEach(([field, message]) => setError(field as keyof ProjectFormData, message));

            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        };

        if (project) {
            put(route('projects.update', project.id), options);
        } else {
            post(route('projects.store'), options);
        }
    };

    return (
        <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
            <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-lg">
                <form onSubmit={submit} className="flex h-full flex-col gap-6" noValidate>
                    <SheetHeader>
                        <SheetTitle>{project ? t('Modifica progetto') : t('Nuovo progetto')}</SheetTitle>
                        <SheetDescription>{t('Un progetto appartiene a un cliente e raccoglie le attività.')}</SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-4 px-4">
                        <div className="grid gap-2">
                            <Label htmlFor="project-client">{t('Cliente')}</Label>
                            <select
                                id="project-client"
                                className={fieldClass}
                                value={data.client_id}
                                disabled={project !== null}
                                onChange={(event) => changeClient(event.target.value)}
                            >
                                <option value="">{t('Scegli un cliente')}</option>
                                {clients.map((candidate) => (
                                    <option key={candidate.id} value={candidate.id}>
                                        {candidate.company_name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.client_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="project-name">{t('Nome')}</Label>
                            <Input
                                id="project-name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                aria-invalid={Boolean(errors.name)}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="project-description">{t('Descrizione')}</Label>
                            <textarea
                                id="project-description"
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                rows={3}
                                className="border-input bg-background focus-visible:ring-ring rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="project-starts-on">{t('Inizio')}</Label>
                                <Input
                                    id="project-starts-on"
                                    type="date"
                                    value={data.starts_on}
                                    onChange={(event) => setData('starts_on', event.target.value)}
                                    aria-invalid={Boolean(errors.starts_on)}
                                />
                                <InputError message={errors.starts_on} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="project-due-on">{t('Scadenza')}</Label>
                                <Input
                                    id="project-due-on"
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
                                <Label htmlFor="project-hours">{t('Ore previste')}</Label>
                                <Input
                                    id="project-hours"
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
                                <Label htmlFor="project-status">{t('Stato')}</Label>
                                <select
                                    id="project-status"
                                    className={fieldClass}
                                    value={data.status}
                                    onChange={(event) => setData('status', event.target.value)}
                                >
                                    {statusOptions
                                        .filter((option) => option.value !== 'archiviato' || project?.status === 'archiviato')
                                        .map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {t(option.label)}
                                            </option>
                                        ))}
                                </select>
                                <InputError message={errors.status} />
                            </div>
                        </div>

                        {project === null && templates.length > 0 && (
                            <div className="grid gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="project-template">{t('Template (facoltativo)')}</Label>
                                    <select
                                        id="project-template"
                                        className={fieldClass}
                                        value={data.template_id}
                                        onChange={(event) =>
                                            setData((current) => ({ ...current, template_id: event.target.value, template_assignees: {} }))
                                        }
                                    >
                                        <option value="">{t('Nessuno')}</option>
                                        {templates.map((candidate) => (
                                            <option key={candidate.id} value={candidate.id}>
                                                {candidate.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.template_id} />
                                </div>
                                {template && (
                                    <TemplatePreview
                                        calendar={calendar}
                                        template={template}
                                        startsOn={data.starts_on}
                                        dueOn={data.due_on}
                                        assignees={assignees}
                                        selected={data.template_assignees}
                                        onChange={(selected) => setData('template_assignees', selected)}
                                        errorFor={(key) => (errors as Record<string, string | undefined>)[key]}
                                    />
                                )}
                            </div>
                        )}

                        <fieldset className="grid gap-2">
                            <legend className="text-sm leading-none font-medium">{t('Referenti del cliente')}</legend>
                            {!client ? (
                                <p className="text-muted-foreground text-sm">{t('Scegli prima un cliente.')}</p>
                            ) : client.contacts.length === 0 ? (
                                <p className="text-muted-foreground text-sm">{t('Il cliente non ha referenti.')}</p>
                            ) : (
                                <div className="border-sidebar-border/70 grid gap-1 rounded-md border p-2">
                                    {client.contacts.map((contact) => (
                                        <label key={contact.id} className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={data.contact_ids.includes(contact.id)}
                                                onChange={() => setData('contact_ids', toggle(data.contact_ids, contact.id))}
                                            />
                                            {contact.name}
                                        </label>
                                    ))}
                                </div>
                            )}
                            <InputError message={errorFor('contact_ids')} />
                        </fieldset>

                        <fieldset className="grid gap-2">
                            <legend className="text-sm leading-none font-medium">{t('Membri')}</legend>
                            <div className="border-sidebar-border/70 grid max-h-48 gap-1 overflow-y-auto rounded-md border p-2">
                                {users.map((person) => (
                                    <label key={person.id} className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={data.member_ids.includes(person.id)}
                                            onChange={() => setData('member_ids', toggle(data.member_ids, person.id))}
                                        />
                                        {person.name}
                                    </label>
                                ))}
                            </div>
                            <InputError message={errorFor('member_ids')} />
                        </fieldset>
                    </div>

                    <SheetFooter className="flex-row justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>
                            {t('Annulla')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {t('Salva')}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
