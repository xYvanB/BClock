import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type AreaOption, type BreadcrumbItem, type CalendarCell, type CalendarDay, type CalendarPerson, type LoadLevel } from '@/types';
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

const formatHours = (hours: number) => String(Number(hours.toFixed(2))).replace('.', ',');

const formatDate = (iso: string) => iso.split('-').reverse().join('/');

const weekday = (iso: string) => new Date(`${iso}T00:00:00`).toLocaleDateString('it-IT', { weekday: 'short' });

const rangeTitle = (view: 'week' | 'month', from: string, to: string, date: string) =>
    view === 'month'
        ? new Date(`${date}T00:00:00`).toLocaleDateString('it-IT', { month: 'long', year: 'numeric' })
        : `${formatDate(from)} – ${formatDate(to)}`;

/** Calendario del carico (F9): per persona e giorno le ore assegnate rispetto a quelle disponibili. Sola lettura. */
export default function CalendarIndex({ view, date, from, to, previous, next, area_id, areas, days, people }: CalendarProps) {
    const [selected, setSelected] = useState<{ person: CalendarPerson; day: CalendarDay } | null>(null);

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

    const today = days.find((day) => day.is_today)?.date;
    const selectedCell: CalendarCell | undefined = selected?.person.days[selected.day.date];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calendario" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">Calendario</h1>

                    <div className="flex flex-wrap items-center gap-2">
                        {areas.length > 0 && (
                            <select
                                aria-label="Area"
                                className={fieldClass}
                                value={area_id ?? ''}
                                onChange={(event) => go({ area_id: event.target.value === '' ? null : Number(event.target.value) })}
                            >
                                <option value="">Tutte le aree</option>
                                {areas.map((area) => (
                                    <option key={area.id} value={area.id}>
                                        {area.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        <div role="group" aria-label="Vista" className="flex gap-1">
                            <Button size="sm" variant={view === 'week' ? 'default' : 'outline'} onClick={() => go({ view: 'week' })}>
                                Settimana
                            </Button>
                            <Button size="sm" variant={view === 'month' ? 'default' : 'outline'} onClick={() => go({ view: 'month' })}>
                                Mese
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button size="icon" variant="outline" aria-label="Periodo precedente" onClick={() => go({ date: previous })}>
                        <ChevronLeft />
                    </Button>
                    <Button size="icon" variant="outline" aria-label="Periodo successivo" onClick={() => go({ date: next })}>
                        <ChevronRight />
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={today !== undefined}
                        onClick={() => router.get(route('calendar.index'), { view, area_id: area_id ?? undefined }, { preserveScroll: true })}
                    >
                        Oggi
                    </Button>
                    <p className="ml-2 font-medium capitalize">{rangeTitle(view, from, to, date)}</p>
                </div>

                <ul className="text-muted-foreground flex flex-wrap gap-4 text-sm" aria-label="Legenda">
                    {(Object.keys(levelLabels) as LoadLevel[]).map((level) => (
                        <li key={level} className="flex items-center gap-2">
                            <span className={cn('inline-block size-3 rounded-sm', levelStyles[level])} aria-hidden /> {levelLabels[level]}
                        </li>
                    ))}
                    <li className="flex items-center gap-2">
                        <span className="bg-muted inline-block size-3 rounded-sm" aria-hidden /> Weekend, festivo o assenza
                    </li>
                </ul>

                {people.length === 0 ? (
                    <div className="border-sidebar-border/70 rounded-xl border border-dashed p-12 text-center">
                        <p className="text-muted-foreground">Nessuna persona da mostrare</p>
                    </div>
                ) : (
                    <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="bg-muted/50">
                                    <th className="bg-muted sticky left-0 z-10 min-w-40 px-3 py-2 text-left font-medium">Persona</th>
                                    {days.map((day) => (
                                        <th
                                            key={day.date}
                                            title={day.holiday ?? undefined}
                                            className={cn(
                                                'min-w-14 px-2 py-2 text-center font-medium',
                                                (day.is_weekend || day.holiday) && 'text-muted-foreground',
                                                day.is_today && 'text-primary underline',
                                            )}
                                        >
                                            <span className="block text-xs capitalize">{weekday(day.date)}</span>
                                            {day.date.slice(8)}
                                            {day.holiday && <span className="block text-xs font-normal">Festivo</span>}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {people.map((person) => (
                                    <tr key={person.id} className="border-sidebar-border/70 border-t">
                                        <th scope="row" className="bg-background sticky left-0 z-10 px-3 py-2 text-left font-medium">
                                            {person.name}
                                            {person.is_self && <span className="text-muted-foreground font-normal"> (tu)</span>}
                                            {person.area_name && (
                                                <span className="text-muted-foreground block text-xs font-normal">{person.area_name}</span>
                                            )}
                                        </th>
                                        {days.map((day) => {
                                            const cell = person.days[day.date];
                                            const isSelected = selected?.person.id === person.id && selected.day.date === day.date;
                                            const off = cell.level === null;

                                            return (
                                                <td key={day.date} className="p-0.5 align-top">
                                                    <button
                                                        type="button"
                                                        onClick={() => setSelected(isSelected ? null : { person, day })}
                                                        aria-pressed={isSelected}
                                                        aria-label={`${person.name}, ${formatDate(day.date)}: ${formatHours(cell.load)} ore su ${formatHours(cell.capacity)}${cell.level ? `, ${levelLabels[cell.level]}` : ''}`}
                                                        className={cn(
                                                            'focus-visible:ring-ring flex min-h-12 w-full flex-col items-center justify-center rounded-md px-1 py-1 text-xs focus-visible:ring-2 focus-visible:outline-none',
                                                            cell.level ? levelStyles[cell.level] : 'bg-muted text-muted-foreground',
                                                            isSelected && 'ring-primary ring-2',
                                                        )}
                                                    >
                                                        {cell.absence && <span className="font-medium">{absenceLabels[cell.absence]}</span>}
                                                        {!(off && cell.load === 0) && (
                                                            <span>
                                                                {formatHours(cell.load)} / {formatHours(cell.capacity)}
                                                            </span>
                                                        )}
                                                        {view === 'week' &&
                                                            cell.items.map((item) => (
                                                                <span
                                                                    key={item.activity_id}
                                                                    className="mt-0.5 max-w-24 truncate opacity-80"
                                                                    title={`${item.activity} – ${item.project}`}
                                                                >
                                                                    {item.activity}
                                                                </span>
                                                            ))}
                                                    </button>
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {selected && selectedCell && (
                    <section aria-live="polite" className="border-sidebar-border/70 rounded-xl border p-4">
                        <h2 className="font-semibold">
                            {selected.person.name} – {formatDate(selected.day.date)}
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            {selectedCell.level ? `${levelLabels[selectedCell.level]}: ` : ''}
                            {formatHours(selectedCell.load)} ore assegnate su {formatHours(selectedCell.capacity)} disponibili
                            {selected.day.holiday ? ` · ${selected.day.holiday}` : ''}
                            {selectedCell.absence ? ` · ${absenceLabels[selectedCell.absence]}` : ''}
                        </p>
                        {selectedCell.items.length === 0 ? (
                            <p className="mt-2 text-sm">Nessuna attività in questo giorno.</p>
                        ) : (
                            <ul className="mt-2 divide-y text-sm">
                                {selectedCell.items.map((item) => (
                                    <li key={item.activity_id} className="flex items-center justify-between gap-4 py-1.5">
                                        <span>
                                            {item.activity} <span className="text-muted-foreground">– {item.project}</span>
                                        </span>
                                        <span className="font-medium">{formatHours(item.hours)} h</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
