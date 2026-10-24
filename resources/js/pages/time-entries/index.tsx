import { FlashMessage } from '@/components/flash-message';
import { TimeEntryFormSheet } from '@/components/time-entry-form-sheet';
import { TimerStartSheet } from '@/components/timer-start-sheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatDay, formatMinutes, formatTime } from '@/lib/time';
import { type ActivityOption, type BreadcrumbItem, type SharedData, type TimeEntryDay, type TimeEntryItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Play, Plus } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Le mie ore', href: '/time-entries' }];

interface Filters {
    from: string;
    to: string;
    project_id: number | null;
    activity_id: number | null;
}

interface Props {
    days: TimeEntryDay[];
    filters: Filters;
    activities: ActivityOption[];
}

export default function TimeEntriesIndex({ days, filters, activities }: Props) {
    const { auth } = usePage<SharedData>().props;
    const [sheetOpen, setSheetOpen] = useState(false);
    const [timerOpen, setTimerOpen] = useState(false);
    const [editing, setEditing] = useState<(TimeEntryItem & { date: string }) | null>(null);

    const applyFilters = (next: Partial<Filters>) => {
        router.get(route('time-entries.index'), { ...filters, ...next }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const openCreate = () => {
        setEditing(null);
        setSheetOpen(true);
    };

    const openEdit = (entry: TimeEntryItem, date: string) => {
        setEditing({ ...entry, date });
        setSheetOpen(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Le mie ore" />

            <div className="flex flex-col gap-4 p-4">
                <FlashMessage />

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Le mie ore</h1>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setTimerOpen(true)}>
                            <Play /> Avvia timer
                        </Button>
                        <Button onClick={openCreate}>
                            <Plus /> Nuova registrazione
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-end gap-4">
                    <div className="grid gap-1">
                        <Label htmlFor="filter-from">Dal</Label>
                        <Input id="filter-from" type="date" value={filters.from} onChange={(event) => applyFilters({ from: event.target.value })} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="filter-to">Al</Label>
                        <Input id="filter-to" type="date" value={filters.to} onChange={(event) => applyFilters({ to: event.target.value })} />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor="filter-activity">Attività</Label>
                        <select
                            id="filter-activity"
                            className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                            value={filters.activity_id ?? ''}
                            onChange={(event) => applyFilters({ activity_id: event.target.value ? Number(event.target.value) : null })}
                        >
                            <option value="">Tutte</option>
                            {activities.map((activity) => (
                                <option key={activity.id} value={activity.id}>
                                    {activity.project_name} › {activity.name}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {days.length === 0 ? (
                    <div className="border-sidebar-border/70 flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <p className="text-muted-foreground">Nessuna registrazione nel periodo</p>
                        <Button onClick={openCreate}>Registra ore</Button>
                    </div>
                ) : (
                    <div className="border-sidebar-border/70 overflow-hidden rounded-xl border">
                        {days.map((day) => (
                            <section key={day.date}>
                                <header className="bg-muted/50 flex items-center justify-between px-4 py-2 text-sm font-semibold">
                                    <span className="capitalize">{formatDay(day.date)}</span>
                                    <span>Totale giorno: {formatMinutes(day.total_minutes)}</span>
                                </header>
                                <ul>
                                    {day.entries.map((entry) => (
                                        <li key={entry.id} className="border-sidebar-border/70 border-t">
                                            <button
                                                type="button"
                                                onClick={() => openEdit(entry, day.date)}
                                                className="hover:bg-muted/40 flex w-full flex-wrap items-center gap-x-6 gap-y-1 px-4 py-3 text-left text-sm"
                                            >
                                                <span className="w-40 shrink-0 tabular-nums">
                                                    {formatTime(entry.started_at, auth.user.time_format)} –{' '}
                                                    {formatTime(entry.ended_at, auth.user.time_format)}
                                                </span>
                                                <span className="min-w-48 flex-1 font-medium">
                                                    {entry.project_name} › {entry.activity_name}
                                                    {entry.activity_over_budget && (
                                                        <span className="ml-2 font-normal" title="Ore previste dell'attività superate">
                                                            ⚠️ attività oltre le ore previste
                                                        </span>
                                                    )}
                                                    {entry.project_over_budget && (
                                                        <span className="ml-2 font-normal" title="Ore previste del progetto superate">
                                                            ⚠️ progetto oltre le ore previste
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="text-muted-foreground min-w-40 flex-1">{entry.description}</span>
                                                <span className="w-16 text-right tabular-nums">{formatMinutes(entry.duration_minutes)}</span>
                                                <span className="text-muted-foreground w-20 text-right">
                                                    {entry.source === 'timer' ? '⏱ Timer' : '✎ Manuale'}
                                                </span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}
            </div>

            <TimeEntryFormSheet open={sheetOpen} entry={editing} activities={activities} onClose={() => setSheetOpen(false)} />
            <TimerStartSheet open={timerOpen} activities={activities} onClose={() => setTimerOpen(false)} />
        </AppLayout>
    );
}
