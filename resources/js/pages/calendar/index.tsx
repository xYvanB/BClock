import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { decimalSeparator, intlLocale, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import {
    type AreaOption,
    type BreadcrumbItem,
    type CalendarCell,
    type CalendarDay,
    type CalendarItem,
    type CalendarPerson,
    type LoadLevel,
} from '@/types';
import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Calendario', href: '/calendar' }];

interface CalendarProps {
    view: 'week' | 'month';
    date: string;
    from: string;
    to: string;
    previous: string;
    next: string;
    area_id: number | null;
    areas: AreaOption[];
    days: CalendarDay[];
    people: CalendarPerson[];
}

/** Allocation move awaiting confirmation (beyond the activity or project due date). */
interface PendingMove {
    item: CalendarItem;
    toDate: string;
    /** The target day already has an allocation of the same activity: the hours add up and one chooses how many to move. */
    merges: boolean;
}

const levelStyles: Record<LoadLevel, string> = {
    sotto_capacita: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
    pieno: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    sovraccarico: 'bg-red-100 text-red-900 dark:bg-red-950 dark:text-red-200',
};

const levelLabels: Record<LoadLevel, string> = {
    sotto_capacita: 'Sotto capacità',
    pieno: 'Pieno',
    sovraccarico: 'Sovraccarico',
};

const absenceLabels = { ferie: 'Ferie', permesso: 'Permesso' } as const;

const fieldClass =
    'border-input bg-background focus-visible:ring-ring h-9 rounded-md border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none';

const formatHours = (hours: number) => String(Number(hours.toFixed(2))).replace('.', decimalSeparator());

const formatDate = (iso: string) => iso.split('-').reverse().join('/');

const weekday = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString(intlLocale(), { weekday: 'short' });

const rangeTitle = (view: 'week' | 'month', from: string, to: string, date: string) =>
    view === 'month'
        ? new Date(`${date}T00:00:00`).toLocaleDateString(intlLocale(), { month: 'long', year: 'numeric' })
        : `${formatDate(from)} – ${formatDate(to)}`;

/** Workload calendar (F9): per person and day, the assigned hours against the available ones; allocations can be moved. */
export default function CalendarIndex({ view, date, from, to, previous, next, area_id, areas, days, people }: CalendarProps) {
    const [selected, setSelected] = useState<{ personId: number; date: string } | null>(null);
    const [dragged, setDragged] = useState<{ item: CalendarItem; personId: number; fromDate: string } | null>(null);
    const [pending, setPending] = useState<PendingMove | null>(null);
    const [moveError, setMoveError] = useState<string | null>(null);
    const [targets, setTargets] = useState<Record<number, string>>({});
    const [partialHours, setPartialHours] = useState<Record<number, string>>({});
    const [moveHours, setMoveHours] = useState('');

    const go = (params: { view?: string; date?: string; area_id?: number | null }) => {
        setSelected(null);
        router.get(
            route('calendar.index'),
            {
                view: params.view ?? view,
                date: params.date ?? date,
                area_id: params.area_id === undefined ? (area_id ?? undefined) : (params.area_id ?? undefined),
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const submitMove = (item: CalendarItem, toDate: string, extendActivity: boolean, extendProject: boolean, hours: string) =>
        router.post(
            route('allocations.move', item.allocation_id),
            { date: toDate, extend_activity: extendActivity, extend_project: extendProject, hours: hours === '' ? null : hours.replace(',', '.') },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setPending(null);
                    setMoveError(null);
                    setSelected(null);
                },
                onError: (errors) => {
                    setPending(null);
                    setMoveError(Object.values(errors)[0] ?? t('Spostamento non riuscito.'));
                },
            },
        );

    /**
     * Moves the allocation (all of it, or `hours` hours). First asks for confirmation if the chosen day is beyond the activity
     * or project due date, or if there is already an allocation of the same activity (to choose how many hours to move).
     */
    const requestMove = (item: CalendarItem, fromDate: string, toDate: string, personId: number, hours = '') => {
        setMoveError(null);

        if (!item.can_move || toDate === '' || toDate === fromDate) {
            return;
        }

        const merges =
            people.find((person) => person.id === personId)?.days[toDate]?.items.some((other) => other.activity_id === item.activity_id) ?? false;
        setMoveHours(hours);

        if (merges || toDate > item.activity_due_on || toDate > item.project_due_on) {
            setPending({ item, toDate, merges });

            return;
        }

        submitMove(item, toDate, false, false, hours);
    };

    const today = days.find((day) => day.is_today)?.date;
    const selectedPerson = selected ? people.find((person) => person.id === selected.personId) : undefined;
    const selectedDay = selected ? days.find((day) => day.date === selected.date) : undefined;
    const selectedCell: CalendarCell | undefined = selectedPerson && selectedDay ? selectedPerson.days[selectedDay.date] : undefined;
    const beyondProject = pending !== null && pending.toDate > pending.item.project_due_on;
    const beyondActivity = pending !== null && pending.toDate > pending.item.activity_due_on;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Calendario')} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">{t('Calendario')}</h1>

                    <div className="flex flex-wrap items-center gap-2">
                        {areas.length > 0 && (
                            <select
                                aria-label={t('Area')}
                                className={fieldClass}
                                value={area_id ?? ''}
                                onChange={(event) => go({ area_id: event.target.value === '' ? null : Number(event.target.value) })}
                            >
                                <option value="">{t('Tutte le aree')}</option>
                                {areas.map((area) => (
                                    <option key={area.id} value={area.id}>
                                        {area.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        <div role="group" aria-label={t('Vista')} className="flex gap-1">
                            <Button size="sm" variant={view === 'week' ? 'default' : 'outline'} onClick={() => go({ view: 'week' })}>
                                {t('Settimana')}
                            </Button>
                            <Button size="sm" variant={view === 'month' ? 'default' : 'outline'} onClick={() => go({ view: 'month' })}>
                                {t('Mese')}
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button size="icon" variant="outline" aria-label={t('Periodo precedente')} onClick={() => go({ date: previous })}>
                        <ChevronLeft />
                    </Button>
                    <Button size="icon" variant="outline" aria-label={t('Periodo successivo')} onClick={() => go({ date: next })}>
                        <ChevronRight />
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={today !== undefined}
                        onClick={() => router.get(route('calendar.index'), { view, area_id: area_id ?? undefined }, { preserveScroll: true })}
                    >
                        {t('Oggi')}
                    </Button>
                    <p className="ml-2 font-medium capitalize">{rangeTitle(view, from, to, date)}</p>
                </div>

                <ul className="text-muted-foreground flex flex-wrap gap-4 text-sm" aria-label={t('Legenda')}>
                    {(Object.keys(levelLabels) as LoadLevel[]).map((level) => (
                        <li key={level} className="flex items-center gap-2">
                            <span className={cn('inline-block size-3 rounded-sm', levelStyles[level])} aria-hidden /> {t(levelLabels[level])}
                        </li>
                    ))}
                    <li className="flex items-center gap-2">
                        <span className="bg-muted inline-block size-3 rounded-sm" aria-hidden /> {t('Weekend, festivo o assenza')}
                    </li>
                </ul>

                {moveError && (
                    <div role="alert" className="border-destructive/50 bg-destructive/10 rounded-md border p-3 text-sm">
                        {moveError}
                    </div>
                )}

                {people.length === 0 ? (
                    <div className="border-sidebar-border/70 rounded-xl border border-dashed p-12 text-center">
                        <p className="text-muted-foreground">{t('Nessuna persona da mostrare')}</p>
                    </div>
                ) : (
                    <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="bg-muted/50">
                                    <th className="bg-muted sticky left-0 z-10 min-w-40 px-3 py-2 text-left font-medium">{t('Persona')}</th>
                                    {days.map((day) => (
                                        <th
                                            key={day.date}
                                            title={day.holiday ?? undefined}
                                            className={cn(
                                                'min-w-14 px-2 py-2 text-center font-medium',
                                                (day.is_off || day.holiday) && 'text-muted-foreground',
                                                day.is_today && 'text-primary underline',
                                            )}
                                        >
                                            <span className="block text-xs capitalize">{weekday(day.date)}</span>
                                            {day.date.slice(8)}
                                            {day.holiday && <span className="block text-xs font-normal">{t('Festivo')}</span>}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {people.map((person) => (
                                    <tr key={person.id} className="border-sidebar-border/70 border-t">
                                        <th scope="row" className="bg-background sticky left-0 z-10 px-3 py-2 text-left font-medium">
                                            {person.name}
                                            {person.is_self && <span className="text-muted-foreground font-normal"> {t('(tu)')}</span>}
                                            {person.area_name && (
                                                <span className="text-muted-foreground block text-xs font-normal">{person.area_name}</span>
                                            )}
                                        </th>
                                        {days.map((day) => {
                                            const cell = person.days[day.date];
                                            const isSelected = selected?.personId === person.id && selected.date === day.date;
                                            const dropAllowed = dragged !== null && dragged.personId === person.id && !day.is_off && !day.holiday;

                                            return (
                                                <td
                                                    key={day.date}
                                                    className="p-0.5 align-top"
                                                    onDragOver={(event) => {
                                                        if (dropAllowed) {
                                                            event.preventDefault();
                                                            event.dataTransfer.dropEffect = 'move';
                                                        }
                                                    }}
                                                    onDrop={(event) => {
                                                        event.preventDefault();
                                                        if (dropAllowed) {
                                                            requestMove(dragged.item, dragged.fromDate, day.date, dragged.personId);
                                                        }
                                                        setDragged(null);
                                                    }}
                                                >
                                                    <button
                                                        type="button"
                                                        onClick={() => setSelected(isSelected ? null : { personId: person.id, date: day.date })}
                                                        aria-pressed={isSelected}
                                                        aria-label={`${person.name}, ${formatDate(day.date)}: ${t(':load ore su :capacity', { load: formatHours(cell.load), capacity: formatHours(cell.capacity) })}${cell.level ? `, ${t(levelLabels[cell.level])}` : ''}`}
                                                        className={cn(
                                                            'focus-visible:ring-ring flex min-h-12 w-full flex-col items-center justify-center rounded-md px-1 py-1 text-xs focus-visible:ring-2 focus-visible:outline-none',
                                                            cell.level ? levelStyles[cell.level] : 'bg-muted text-muted-foreground',
                                                            isSelected && 'ring-primary ring-2',
                                                            dropAllowed && 'outline-primary outline-dashed',
                                                        )}
                                                    >
                                                        {cell.absence && <span className="font-medium">{t(absenceLabels[cell.absence])}</span>}
                                                        {!(cell.level === null && cell.load === 0) && (
                                                            <span>
                                                                {formatHours(cell.load)} / {formatHours(cell.capacity)}
                                                            </span>
                                                        )}
                                                    </button>
                                                    {view === 'week' &&
                                                        cell.items.map((item) => (
                                                            <div
                                                                key={item.allocation_id}
                                                                draggable={item.can_move}
                                                                onDragStart={(event) => {
                                                                    event.dataTransfer.setData('text/plain', String(item.allocation_id));
                                                                    event.dataTransfer.effectAllowed = 'move';
                                                                    setDragged({ item, personId: person.id, fromDate: day.date });
                                                                }}
                                                                onDragEnd={() => setDragged(null)}
                                                                title={`${item.activity} – ${item.project}${item.can_move ? ` ${t('(trascina per spostare)')}` : ''}`}
                                                                className={cn(
                                                                    'bg-background border-sidebar-border/70 mt-1 max-w-28 truncate rounded border px-1 py-0.5 text-xs',
                                                                    item.can_move && 'cursor-grab',
                                                                )}
                                                            >
                                                                {item.activity} · {formatHours(item.hours)} h
                                                            </div>
                                                        ))}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {selectedPerson && selectedDay && selectedCell && (
                    <section aria-live="polite" className="border-sidebar-border/70 rounded-xl border p-4">
                        <h2 className="font-semibold">
                            {selectedPerson.name} – {formatDate(selectedDay.date)}
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            {selectedCell.level ? `${levelLabels[selectedCell.level]}: ` : ''}
                            {t(':load ore assegnate su :capacity disponibili', {
                                load: formatHours(selectedCell.load),
                                capacity: formatHours(selectedCell.capacity),
                            })}
                            {selectedDay.holiday ? ` · ${selectedDay.holiday}` : ''}
                            {selectedCell.absence ? ` · ${absenceLabels[selectedCell.absence]}` : ''}
                        </p>
                        {selectedCell.items.length === 0 ? (
                            <p className="mt-2 text-sm">{t('Nessuna attività in questo giorno.')}</p>
                        ) : (
                            <ul className="mt-2 divide-y text-sm">
                                {selectedCell.items.map((item) => (
                                    <li key={item.allocation_id} className="flex flex-wrap items-center justify-between gap-3 py-2">
                                        <span>
                                            {item.activity} <span className="text-muted-foreground">– {item.project}</span>
                                        </span>
                                        <span className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">{formatHours(item.hours)} h</span>
                                            {item.can_move && (
                                                <>
                                                    <label className="sr-only" htmlFor={`move-${item.allocation_id}`}>
                                                        {t('Sposta :activity al giorno', { activity: item.activity })}
                                                    </label>
                                                    <Input
                                                        id={`move-${item.allocation_id}`}
                                                        type="date"
                                                        className="h-8 w-40"
                                                        value={targets[item.allocation_id] ?? ''}
                                                        onChange={(event) => setTargets({ ...targets, [item.allocation_id]: event.target.value })}
                                                    />
                                                    <label className="sr-only" htmlFor={`hours-${item.allocation_id}`}>
                                                        {t('Ore da spostare di :activity', { activity: item.activity })}
                                                    </label>
                                                    <Input
                                                        id={`hours-${item.allocation_id}`}
                                                        type="number"
                                                        min="0.25"
                                                        max={item.hours}
                                                        step="0.25"
                                                        placeholder={t('Tutte le ore')}
                                                        className="h-8 w-28"
                                                        value={partialHours[item.allocation_id] ?? ''}
                                                        onChange={(event) =>
                                                            setPartialHours({ ...partialHours, [item.allocation_id]: event.target.value })
                                                        }
                                                    />
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={!targets[item.allocation_id]}
                                                        onClick={() =>
                                                            requestMove(
                                                                item,
                                                                selectedDay.date,
                                                                targets[item.allocation_id],
                                                                selectedPerson.id,
                                                                partialHours[item.allocation_id] ?? '',
                                                            )
                                                        }
                                                    >
                                                        {t('Sposta')}
                                                    </Button>
                                                </>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <InputError message={moveError ?? undefined} />
                    </section>
                )}
            </div>

            <Dialog open={pending !== null} onOpenChange={(open) => !open && setPending(null)}>
                <DialogContent>
                    {pending && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {beyondProject
                                        ? t('Sei sicuro?')
                                        : beyondActivity
                                          ? t("Estendere la data di fine dell'attività?")
                                          : t('Quante ore spostare?')}
                                </DialogTitle>
                                <DialogDescription>
                                    {pending.merges &&
                                        `${t("Il :date c'è già una quota di «:activity»: le ore spostate si sommano.", { date: formatDate(pending.toDate), activity: pending.item.activity })} `}
                                    {beyondProject &&
                                        t(
                                            "Il :date è oltre la scadenza del progetto (:projectDue). Confermando si estendono a quella data sia la scadenza del progetto sia quella dell'attività «:activity».",
                                            {
                                                date: formatDate(pending.toDate),
                                                projectDue: formatDate(pending.item.project_due_on),
                                                activity: pending.item.activity,
                                            },
                                        )}
                                    {!beyondProject &&
                                        beyondActivity &&
                                        t("L'attività «:activity» finisce il :activityDue. Confermando, la sua data di fine diventa il :date.", {
                                            activity: pending.item.activity,
                                            activityDue: formatDate(pending.item.activity_due_on),
                                            date: formatDate(pending.toDate),
                                        })}
                                </DialogDescription>
                            </DialogHeader>
                            <div className="grid gap-2">
                                <label htmlFor="move-hours" className="text-sm font-medium">
                                    {t('Ore da spostare (su :hours del giorno)', { hours: formatHours(pending.item.hours) })}
                                </label>
                                <Input
                                    id="move-hours"
                                    type="number"
                                    min="0.25"
                                    max={pending.item.hours}
                                    step="0.25"
                                    placeholder={t('Tutte')}
                                    value={moveHours}
                                    onChange={(event) => setMoveHours(event.target.value)}
                                />
                                <p className="text-muted-foreground text-sm">{t('Lascia vuoto per spostarle tutte.')}</p>
                            </div>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => setPending(null)}>
                                    {t('Annulla')}
                                </Button>
                                <Button
                                    onClick={() =>
                                        submitMove(pending.item, pending.toDate, beyondActivity || beyondProject, beyondProject, moveHours)
                                    }
                                >
                                    {beyondActivity || beyondProject ? t('Estendi e sposta') : t('Sposta')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
