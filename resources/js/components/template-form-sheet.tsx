import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { roleLabels } from '@/components/user-form-sheet';
import { templateSchema, type TemplateActivityFormData, type TemplateFormData } from '@/lib/schemas/template';
import { flattenZodErrors } from '@/lib/zod-errors';
import { type AreaOption, type TemplateListItem } from '@/types';
import { useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useEffect } from 'react';

interface TemplateFormSheetProps {
    open: boolean;
    template: TemplateListItem | null;
    areas: AreaOption[];
    onClose: () => void;
}

const fieldClass =
    'border-input bg-background focus-visible:ring-ring h-9 w-full rounded-md border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none disabled:opacity-60';

const emptyActivity = (): TemplateActivityFormData => ({
    name: '',
    description: '',
    planned_hours: '',
    start_day: '1',
    end_day: '1',
    suggested_area_id: '',
    suggested_role: '',
});

/** Side drawer to create or edit a project template (7.9): activities can be reordered with the up/down buttons. */
export function TemplateFormSheet({ open, template, areas, onClose }: TemplateFormSheetProps) {
    const { data, setData, post, put, processing, errors, setError, clearErrors, reset } = useForm<TemplateFormData>({
        name: '',
        activities: [],
    });

    // The server's nested errors (e.g. `activities.0.name`) are not in the form's errors type.
    const fieldErrors = errors as Record<string, string | undefined>;

    useEffect(() => {
        if (open) {
            clearErrors();
            setData({
                name: template?.name ?? '',
                activities: template
                    ? template.activities.map((activity) => ({
                          name: activity.name,
                          description: activity.description ?? '',
                          planned_hours: String(activity.planned_hours),
                          start_day: String(activity.start_day),
                          end_day: String(activity.end_day),
                          suggested_area_id: activity.suggested_area_id ? String(activity.suggested_area_id) : '',
                          suggested_role: activity.suggested_role ?? '',
                      }))
                    : [emptyActivity()],
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, template]);

    const updateActivity = (index: number, changes: Partial<TemplateActivityFormData>) =>
        setData(
            'activities',
            data.activities.map((activity, position) => (position === index ? { ...activity, ...changes } : activity)),
        );

    const moveActivity = (index: number, direction: -1 | 1) => {
        const target = index + direction;
        if (target < 0 || target >= data.activities.length) {
            return;
        }

        const reordered = [...data.activities];
        [reordered[index], reordered[target]] = [reordered[target], reordered[index]];
        // The server errors are indexed by position: after the move they no longer match.
        clearErrors();
        setData('activities', reordered);
    };

    const removeActivity = (index: number) => {
        clearErrors();
        setData(
            'activities',
            data.activities.filter((_, position) => position !== index),
        );
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        const result = templateSchema.safeParse(data);
        if (!result.success) {
            const zodErrors = flattenZodErrors<string>(result.error);
            Object.entries(zodErrors).forEach(([field, message]) => setError(field as keyof TemplateFormData, message as string));

            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        };

        if (template) {
            put(route('templates.update', template.id), options);
        } else {
            post(route('templates.store'), options);
        }
    };

    return (
        <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
            <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-2xl">
                <form onSubmit={submit} className="flex h-full flex-col gap-6" noValidate>
                    <SheetHeader>
                        <SheetTitle>{template ? 'Modifica template' : 'Nuovo template'}</SheetTitle>
                        <SheetDescription>
                            Le attività usano giorni lavorativi (lun–ven) dall'inizio del progetto: il giorno 1 è la data di inizio.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-4 px-4">
                        <div className="grid gap-2">
                            <Label htmlFor="template-name">Nome</Label>
                            <Input
                                id="template-name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                aria-invalid={Boolean(errors.name)}
                                autoFocus
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-3">
                            <div className="flex items-center justify-between">
                                <Label>Attività</Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setData('activities', [...data.activities, emptyActivity()])}
                                >
                                    <Plus /> Aggiungi attività
                                </Button>
                            </div>
                            <InputError message={fieldErrors.activities} />

                            {data.activities.map((activity, index) => (
                                <fieldset key={index} className="border-sidebar-border/70 grid gap-3 rounded-lg border p-3">
                                    <legend className="px-1 text-sm font-medium">Attività {index + 1}</legend>

                                    <div className="grid gap-2">
                                        <Label htmlFor={`template-activity-name-${index}`}>Nome</Label>
                                        <Input
                                            id={`template-activity-name-${index}`}
                                            value={activity.name}
                                            onChange={(event) => updateActivity(index, { name: event.target.value })}
                                            aria-invalid={Boolean(fieldErrors[`activities.${index}.name`])}
                                        />
                                        <InputError message={fieldErrors[`activities.${index}.name`]} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor={`template-activity-description-${index}`}>Descrizione</Label>
                                        <textarea
                                            id={`template-activity-description-${index}`}
                                            value={activity.description}
                                            onChange={(event) => updateActivity(index, { description: event.target.value })}
                                            rows={2}
                                            className="border-input bg-background focus-visible:ring-ring rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                                        />
                                        <InputError message={fieldErrors[`activities.${index}.description`]} />
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <div className="grid gap-2">
                                            <Label htmlFor={`template-activity-hours-${index}`}>Ore previste</Label>
                                            <Input
                                                id={`template-activity-hours-${index}`}
                                                type="number"
                                                min="0"
                                                step="0.5"
                                                value={activity.planned_hours}
                                                onChange={(event) => updateActivity(index, { planned_hours: event.target.value })}
                                                aria-invalid={Boolean(fieldErrors[`activities.${index}.planned_hours`])}
                                            />
                                            <InputError message={fieldErrors[`activities.${index}.planned_hours`]} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor={`template-activity-start-${index}`}>Dal giorno</Label>
                                            <Input
                                                id={`template-activity-start-${index}`}
                                                type="number"
                                                min="1"
                                                value={activity.start_day}
                                                onChange={(event) => updateActivity(index, { start_day: event.target.value })}
                                                aria-invalid={Boolean(fieldErrors[`activities.${index}.start_day`])}
                                            />
                                            <InputError message={fieldErrors[`activities.${index}.start_day`]} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor={`template-activity-end-${index}`}>Al giorno</Label>
                                            <Input
                                                id={`template-activity-end-${index}`}
                                                type="number"
                                                min="1"
                                                value={activity.end_day}
                                                onChange={(event) => updateActivity(index, { end_day: event.target.value })}
                                                aria-invalid={Boolean(fieldErrors[`activities.${index}.end_day`])}
                                            />
                                            <InputError message={fieldErrors[`activities.${index}.end_day`]} />
                                        </div>
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor={`template-activity-area-${index}`}>Area suggerita</Label>
                                            <select
                                                id={`template-activity-area-${index}`}
                                                className={fieldClass}
                                                value={activity.suggested_area_id}
                                                onChange={(event) => updateActivity(index, { suggested_area_id: event.target.value })}
                                            >
                                                <option value="">Nessuna</option>
                                                {areas.map((area) => (
                                                    <option key={area.id} value={area.id}>
                                                        {area.name}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={fieldErrors[`activities.${index}.suggested_area_id`]} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor={`template-activity-role-${index}`}>Ruolo suggerito</Label>
                                            <select
                                                id={`template-activity-role-${index}`}
                                                className={fieldClass}
                                                value={activity.suggested_role}
                                                onChange={(event) => updateActivity(index, { suggested_role: event.target.value })}
                                            >
                                                <option value="">Nessuno</option>
                                                {Object.entries(roleLabels).map(([value, label]) => (
                                                    <option key={value} value={value}>
                                                        {label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={fieldErrors[`activities.${index}.suggested_role`]} />
                                        </div>
                                    </div>

                                    <div className="flex justify-end gap-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => moveActivity(index, -1)}
                                            disabled={index === 0}
                                            aria-label={`Sposta su l'attività ${index + 1}`}
                                        >
                                            <ArrowUp />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => moveActivity(index, 1)}
                                            disabled={index === data.activities.length - 1}
                                            aria-label={`Sposta giù l'attività ${index + 1}`}
                                        >
                                            <ArrowDown />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => removeActivity(index)}
                                            aria-label={`Rimuovi l'attività ${index + 1}`}
                                        >
                                            <Trash2 /> Rimuovi
                                        </Button>
                                    </div>
                                </fieldset>
                            ))}
                        </div>
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
