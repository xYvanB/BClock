import InputError from '@/components/input-error';
import { dateOfWorkingDay } from '@/lib/template-days';
import { formatDay } from '@/lib/time';
import { type AssigneeOption, type TemplateOption, type WorkCalendar } from '@/types';

interface TemplatePreviewProps {
    template: TemplateOption;
    startsOn: string;
    dueOn: string;
    assignees: AssigneeOption[];
    selected: Record<string, number[]>;
    onChange: (selected: Record<string, number[]>) => void;
    /** Messaggio di errore per una chiave del server, es. `template_assignees.12`. */
    errorFor: (key: string) => string | undefined;
    /** Calendario aziendale per contare i giorni lavorativi (di norma quello condiviso dal server). */
    calendar?: WorkCalendar | null;
}

/** Data di fine dell'ultima attività del template, o null se non c'è una data di inizio valida. */
export function templateEndDate(template: TemplateOption, startsOn: string, calendar?: WorkCalendar | null): string | null {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(startsOn) || template.activities.length === 0) {
        return null;
    }

    return dateOfWorkingDay(startsOn, Math.max(...template.activities.map((activity) => activity.end_day)), calendar ?? undefined);
}

/** Anteprima delle attività che il template creerà, con le date calcolate e gli assegnatari obbligatori. */
export function TemplatePreview({ template, startsOn, dueOn, assignees, selected, onChange, errorFor, calendar }: TemplatePreviewProps) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(startsOn)) {
        return <p className="text-muted-foreground text-sm">Imposta la data di inizio per vedere l'anteprima delle attività.</p>;
    }

    const end = templateEndDate(template, startsOn);
    const exceedsDue = end !== null && /^\d{4}-\d{2}-\d{2}$/.test(dueOn) && end > dueOn;

    const toggle = (activityId: number, personId: number) => {
        const current = selected[String(activityId)] ?? [];
        const next = current.includes(personId) ? current.filter((id) => id !== personId) : [...current, personId];
        onChange({ ...selected, [String(activityId)]: next });
    };

    return (
        <div className="grid gap-3">
            <p className="text-sm font-medium">Anteprima: {template.activities.length} attività</p>

            {exceedsDue && (
                <div role="alert" className="border-destructive/50 bg-destructive/10 rounded-md border p-3 text-sm">
                    Le attività finiscono il {formatDay(end)}, oltre la scadenza del progetto: allunga la scadenza o scegli un altro template.
                </div>
            )}

            {template.activities.map((activity) => {
                // Le persone dell'area suggerita vengono prima.
                const ordered = [...assignees].sort(
                    (a, b) =>
                        Number(b.area_id === activity.suggested_area_id && activity.suggested_area_id !== null) -
                        Number(a.area_id === activity.suggested_area_id && activity.suggested_area_id !== null),
                );
                const error = errorFor(`template_assignees.${activity.id}`) ?? errorFor(`template_assignees.${activity.id}.0`);

                return (
                    <fieldset key={activity.id} className="border-sidebar-border/70 grid gap-2 rounded-lg border p-3">
                        <legend className="px-1 text-sm font-medium">{activity.name}</legend>
                        <p className="text-muted-foreground text-sm">
                            {formatDay(dateOfWorkingDay(startsOn, activity.start_day, calendar ?? undefined))} →{' '}
                            {formatDay(dateOfWorkingDay(startsOn, activity.end_day, calendar ?? undefined))} · {activity.planned_hours} h
                        </p>
                        <div className="grid gap-1">
                            <span className="text-sm">Assegnatari (almeno uno)</span>
                            <div className="border-sidebar-border/70 grid max-h-32 gap-1 overflow-y-auto rounded-md border p-2">
                                {ordered.map((person) => (
                                    <label key={person.id} className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={(selected[String(activity.id)] ?? []).includes(person.id)}
                                            onChange={() => toggle(activity.id, person.id)}
                                        />
                                        {person.name}
                                    </label>
                                ))}
                            </div>
                            <InputError message={error} />
                        </div>
                    </fieldset>
                );
            })}
        </div>
    );
}
