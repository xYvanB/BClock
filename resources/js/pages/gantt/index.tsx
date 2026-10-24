import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { statusLabel, statusOptions } from '@/lib/status';
import { cn } from '@/lib/utils';
import {
    type AreaOption,
    type BreadcrumbItem,
    type GanttActivity,
    type GanttFilters,
    type GanttPreview,
    type GanttProject,
    type GanttShiftMode,
    type PersonOption,
} from '@/types';
import { Head, router } from '@inertiajs/react';
import { PointerEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Gantt', href: '/gantt' }];

type Zoom = 'day' | 'week' | 'month';

interface GanttProps {
    zoom: Zoom;
    filters: GanttFilters;
    from: string;
    to: string;
    today: string;
    projects: GanttProject[];
    filter_options: { projects: { id: number; name: string }[]; areas: AreaOption[]; people: PersonOption[] };
    preview?: GanttPreview | null;
}

/** Spostamento richiesto, in attesa dell'anteprima e della conferma. */
interface ShiftRequest {
    activity: GanttActivity;
    mode: GanttShiftMode;
    startsOn: string;
    dueOn: string;
}

const PX_PER_DAY: Record<Zoom, number> = { day: 36, week: 14, month: 5 };
const ROW_HEIGHT = 36;
const LABEL_WIDTH = 260;
const HEADER_HEIGHT = 56;
const DAY_MS = 86_400_000;

const statusColors: Record<string, string> = {
    da_fare: 'bg-slate-400 dark:bg-slate-500',
    in_corso: 'bg-sky-500',
    in_attesa_di_cliente: 'bg-amber-500',
    completato: 'bg-emerald-500',
    archiviato: 'bg-zinc-300 dark:bg-zinc-600',
};

const fieldClass =
    'border-input bg-background focus-visible:ring-ring h-9 rounded-md border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none';

const toUtc = (iso: string) => Date.UTC(Number(iso.slice(0, 4)), Number(iso.slice(5, 7)) - 1, Number(iso.slice(8, 10)));
const diffDays = (from: string, to: string) => Math.round((toUtc(to) - toUtc(from)) / DAY_MS);
const addDays = (iso: string, days: number) => new Date(toUtc(iso) + days * DAY_MS).toISOString().slice(0, 10);
const formatDate = (iso: string) => iso.split('-').reverse().join('/');
const monthName = (iso: string) => new Date(toUtc(iso)).toLocaleDateString('it-IT', { month: 'long', year: 'numeric', timeZone: 'UTC' });
const isWeekend = (iso: string) => [0, 6].includes(new Date(toUtc(iso)).getUTCDay());

/** Segmenti dei mesi per l'intestazione: etichetta, posizione e larghezza in pixel. */
function buildMonths(from: string, totalDays: number, px: number): { label: string; left: number; width: number }[] {
    const months: { label: string; left: number; width: number }[] = [];

    for (let index = 0; index < totalDays; index++) {
        const date = addDays(from, index);
        const last = months[months.length - 1];

        if (!last || date.slice(0, 7) !== addDays(from, index - 1).slice(0, 7)) {
            months.push({ label: monthName(date), left: index * px, width: px });
        } else {
            last.width += px;
        }
    }

    return months;
}

/** Gantt (F10): attività per progetto con dipendenze; le barre si spostano e si ridimensionano, con anteprima della catena. */
export default function GanttIndex({ zoom, filters, from, to, today, projects, filter_options, preview }: GanttProps) {
    const px = PX_PER_DAY[zoom];
    const totalDays = diffDays(from, to) + 1;
    const timelineWidth = totalDays * px;

    const [drag, setDrag] = useState<{ id: number; mode: GanttShiftMode; startX: number; dx: number } | null>(null);
    const [request, setRequest] = useState<ShiftRequest | null>(null);
    const [selected, setSelected] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [panel, setPanel] = useState<{ move: string; start: string; end: string }>({ move: '', start: '', end: '' });

    const query = (extra: Record<string, unknown> = {}) => ({
        ...Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== null)),
        zoom,
        ...extra,
    });

    const navigate = (extra: Record<string, unknown>) =>
        router.get(route('gantt.index'), query(extra), { preserveState: true, preserveScroll: true });

    // Righe: una per progetto e una per attività; servono per le frecce e per le linee di scadenza.
    const rows: ({ kind: 'project'; project: GanttProject } | { kind: 'activity'; activity: GanttActivity; project: GanttProject })[] = [];
    projects.forEach((project) => {
        rows.push({ kind: 'project', project });
        project.activities.forEach((activity) => rows.push({ kind: 'activity', activity, project }));
    });
    const rowOf = new Map<number, number>();
    rows.forEach((row, index) => row.kind === 'activity' && rowOf.set(row.activity.id, index));
    const barOf = (activity: GanttActivity) => ({
        left: diffDays(from, activity.starts_on) * px,
        width: (diffDays(activity.starts_on, activity.due_on) + 1) * px,
    });

    const requestShift = (activity: GanttActivity, mode: GanttShiftMode, startsOn: string, dueOn: string) => {
        setError(null);
        setRequest({ activity, mode, startsOn, dueOn });
        router.get(route('gantt.index'), query({ preview_activity: activity.id, preview_mode: mode, preview_start: startsOn, preview_due: dueOn }), {
            only: ['preview'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const closePreview = () => {
        setRequest(null);
        router.get(route('gantt.index'), query(), { only: ['preview'], preserveState: true, preserveScroll: true, replace: true });
    };

    const applyShift = () => {
        if (!request || !preview) {
            return;
        }

        router.post(
            route('gantt.shift', request.activity.id),
            { ...query(), mode: request.mode, starts_on: request.startsOn, due_on: request.dueOn, confirm_projects: preview.projects.length > 0 },
            {
                preserveScroll: true,
                onSuccess: () => setRequest(null),
                onError: (errors) => {
                    setRequest(null);
                    setError(Object.values(errors)[0] ?? 'Spostamento non riuscito.');
                },
            },
        );
    };

    const onPointerDown = (event: PointerEvent, activity: GanttActivity, mode: GanttShiftMode) => {
        if (!activity.can_move) {
            return;
        }

        event.currentTarget.setPointerCapture(event.pointerId);
        setDrag({ id: activity.id, mode, startX: event.clientX, dx: 0 });
    };

    const onPointerMove = (event: PointerEvent, activity: GanttActivity) => {
        if (drag?.id === activity.id) {
            setDrag({ ...drag, dx: event.clientX - drag.startX });
        }
    };

    const onPointerUp = (activity: GanttActivity) => {
        if (drag?.id !== activity.id) {
            return;
        }

        const days = Math.round(drag.dx / px);
        const { mode } = drag;
        setDrag(null);

        if (Math.abs(drag.dx) < 4 || days === 0) {
            setSelected(selected === activity.id ? null : activity.id);
            setPanel({ move: activity.starts_on, start: activity.starts_on, end: activity.due_on });

            return;
        }

        requestShift(
            activity,
            mode,
            mode === 'resize_end' ? activity.starts_on : addDays(activity.starts_on, days),
            mode === 'move' ? addDays(activity.due_on, days) : mode === 'resize_end' ? addDays(activity.due_on, days) : activity.due_on,
        );
    };

    // Intestazione: mesi sopra, giorni (zoom giorno) o lunedì (zoom settimana) sotto.
    const months = buildMonths(from, totalDays, px);
    const ticks = Array.from({ length: totalDays }, (_, index) => addDays(from, index)).filter(
        (date) => zoom === 'day' || (zoom === 'week' && new Date(toUtc(date)).getUTCDay() === 1),
    );

    const selectedActivity = projects.flatMap((project) => project.activities).find((activity) => activity.id === selected);
    const previewReady =
        request !== null &&
        preview?.activity_id === request.activity.id &&
        preview.requested.mode === request.mode &&
        preview.requested.start === request.startsOn &&
        preview.requested.due === request.dueOn;
    const todayLeft = diffDays(from, today) * px + px / 2;
    const bodyHeight = rows.length * ROW_HEIGHT;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Gantt" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">Gantt</h1>

                    <div className="flex flex-wrap items-center gap-2">
                        <select
                            aria-label="Progetto"
                            className={fieldClass}
                            value={filters.project_id ?? ''}
                            onChange={(event) => navigate({ project_id: event.target.value || undefined })}
                        >
                            <option value="">Tutti i progetti</option>
                            {filter_options.projects.map((project) => (
                                <option key={project.id} value={project.id}>
                                    {project.name}
                                </option>
                            ))}
                        </select>
                        {filter_options.areas.length > 0 && (
                            <select
                                aria-label="Area"
                                className={fieldClass}
                                value={filters.area_id ?? ''}
                                onChange={(event) => navigate({ area_id: event.target.value || undefined })}
                            >
                                <option value="">Tutte le aree</option>
                                {filter_options.areas.map((area) => (
                                    <option key={area.id} value={area.id}>
                                        {area.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        {filter_options.people.length > 0 && (
                            <select
                                aria-label="Persona"
                                className={fieldClass}
                                value={filters.user_id ?? ''}
                                onChange={(event) => navigate({ user_id: event.target.value || undefined })}
                            >
                                <option value="">Tutte le persone</option>
                                {filter_options.people.map((person) => (
                                    <option key={person.id} value={person.id}>
                                        {person.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        <select
                            aria-label="Stato"
                            className={fieldClass}
                            value={filters.status ?? ''}
                            onChange={(event) => navigate({ status: event.target.value || undefined })}
                        >
                            <option value="">Stati attivi</option>
                            {statusOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <div role="group" aria-label="Zoom" className="flex gap-1">
                            {(['day', 'week', 'month'] as Zoom[]).map((level) => (
                                <Button
                                    key={level}
                                    size="sm"
                                    variant={zoom === level ? 'default' : 'outline'}
                                    onClick={() => navigate({ zoom: level })}
                                >
                                    {{ day: 'Giorno', week: 'Settimana', month: 'Mese' }[level]}
                                </Button>
                            ))}
                        </div>
                    </div>
                </div>

                <ul className="text-muted-foreground flex flex-wrap gap-4 text-sm" aria-label="Legenda">
                    {statusOptions
                        .filter((option) => option.value !== 'archiviato')
                        .map((option) => (
                            <li key={option.value} className="flex items-center gap-2">
                                <span className={cn('inline-block size-3 rounded-sm', statusColors[option.value])} aria-hidden /> {option.label}
                            </li>
                        ))}
                    <li className="flex items-center gap-2">
                        <span className="bg-primary inline-block h-3 w-0.5" aria-hidden /> Oggi
                    </li>
                    <li className="flex items-center gap-2">
                        <span className="inline-block h-3 w-0.5 bg-red-500" aria-hidden /> Scadenza del progetto
                    </li>
                </ul>

                {error && (
                    <div role="alert" className="border-destructive/50 bg-destructive/10 rounded-md border p-3 text-sm">
                        {error}
                    </div>
                )}

                {rows.length === 0 ? (
                    <div className="border-sidebar-border/70 rounded-xl border border-dashed p-12 text-center">
                        <p className="text-muted-foreground">Nessuna attività da mostrare</p>
                    </div>
                ) : (
                    <div className="border-sidebar-border/70 max-h-[70vh] overflow-auto rounded-xl border">
                        <div className="relative" style={{ width: LABEL_WIDTH + timelineWidth }}>
                            <div className="bg-muted sticky top-0 z-30 flex" style={{ height: HEADER_HEIGHT }}>
                                <div
                                    className="bg-muted sticky left-0 z-40 flex shrink-0 items-center border-r px-3 text-sm font-medium"
                                    style={{ width: LABEL_WIDTH }}
                                >
                                    Progetto / attività
                                </div>
                                <div className="relative" style={{ width: timelineWidth }}>
                                    {months.map((month) => (
                                        <div
                                            key={month.left}
                                            className="absolute top-0 truncate border-l px-2 py-1 text-xs font-medium capitalize"
                                            style={{ left: month.left, width: month.width, height: HEADER_HEIGHT / 2 }}
                                        >
                                            {month.label}
                                        </div>
                                    ))}
                                    {ticks.map((date) => (
                                        <div
                                            key={date}
                                            className={cn(
                                                'text-muted-foreground absolute border-l text-center text-xs',
                                                isWeekend(date) && 'bg-muted-foreground/10',
                                            )}
                                            style={{
                                                left: diffDays(from, date) * px,
                                                width: zoom === 'day' ? px : px * 7,
                                                top: HEADER_HEIGHT / 2,
                                                height: HEADER_HEIGHT / 2,
                                                lineHeight: `${HEADER_HEIGHT / 2}px`,
                                            }}
                                        >
                                            {date.slice(8)}
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="relative">
                                {rows.map((row) =>
                                    row.kind === 'project' ? (
                                        <div key={`p${row.project.id}`} className="bg-muted/40 flex border-t" style={{ height: ROW_HEIGHT }}>
                                            <div
                                                className="bg-background sticky left-0 z-20 flex shrink-0 items-center border-r px-3 text-sm font-semibold"
                                                style={{ width: LABEL_WIDTH }}
                                            >
                                                <span className="truncate" title={`${row.project.name} – scade il ${formatDate(row.project.due_on)}`}>
                                                    {row.project.name}
                                                </span>
                                            </div>
                                            <div className="relative" style={{ width: timelineWidth }}>
                                                <div
                                                    className="bg-foreground/30 absolute top-4 h-1.5 rounded"
                                                    style={{
                                                        left: diffDays(from, row.project.starts_on) * px,
                                                        width: (diffDays(row.project.starts_on, row.project.due_on) + 1) * px,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    ) : (
                                        <div key={`a${row.activity.id}`} className="flex border-t" style={{ height: ROW_HEIGHT }}>
                                            <div
                                                className="bg-background sticky left-0 z-20 flex shrink-0 flex-col justify-center border-r px-3 pl-6 text-sm"
                                                style={{ width: LABEL_WIDTH }}
                                            >
                                                <span className="truncate" title={row.activity.name}>
                                                    {row.activity.name}
                                                </span>
                                                <span className="text-muted-foreground truncate text-xs">{row.activity.assignees.join(', ')}</span>
                                            </div>
                                            <div className="relative" style={{ width: timelineWidth }}>
                                                {(() => {
                                                    const base = barOf(row.activity);
                                                    const dx = drag?.id === row.activity.id ? drag.dx : 0;
                                                    const mode = drag?.id === row.activity.id ? drag.mode : null;
                                                    const left = base.left + (mode === 'move' || mode === 'resize_start' ? dx : 0);
                                                    const width = Math.max(
                                                        px,
                                                        base.width + (mode === 'resize_end' ? dx : mode === 'resize_start' ? -dx : 0),
                                                    );

                                                    return (
                                                        <div
                                                            role="button"
                                                            tabIndex={0}
                                                            aria-label={`${row.activity.name}, dal ${formatDate(row.activity.starts_on)} al ${formatDate(row.activity.due_on)}, ${statusLabel(row.activity.status)}`}
                                                            onKeyDown={(event) => {
                                                                if (event.key === 'Enter' || event.key === ' ') {
                                                                    event.preventDefault();
                                                                    setSelected(selected === row.activity.id ? null : row.activity.id);
                                                                    setPanel({
                                                                        move: row.activity.starts_on,
                                                                        start: row.activity.starts_on,
                                                                        end: row.activity.due_on,
                                                                    });
                                                                }
                                                            }}
                                                            onPointerDown={(event) => onPointerDown(event, row.activity, 'move')}
                                                            onPointerMove={(event) => onPointerMove(event, row.activity)}
                                                            onPointerUp={() => onPointerUp(row.activity)}
                                                            className={cn(
                                                                'focus-visible:ring-ring absolute top-2 flex h-5 touch-none items-center rounded text-xs text-white select-none focus-visible:ring-2 focus-visible:outline-none',
                                                                statusColors[row.activity.status],
                                                                row.activity.can_move && 'cursor-grab',
                                                                selected === row.activity.id && 'ring-primary ring-2',
                                                            )}
                                                            style={{ left, width }}
                                                        >
                                                            {row.activity.can_move && (
                                                                <>
                                                                    <span
                                                                        aria-hidden
                                                                        className="absolute inset-y-0 left-0 w-1.5 cursor-ew-resize"
                                                                        onPointerDown={(event) => {
                                                                            event.stopPropagation();
                                                                            onPointerDown(event, row.activity, 'resize_start');
                                                                        }}
                                                                    />
                                                                    <span
                                                                        aria-hidden
                                                                        className="absolute inset-y-0 right-0 w-1.5 cursor-ew-resize"
                                                                        onPointerDown={(event) => {
                                                                            event.stopPropagation();
                                                                            onPointerDown(event, row.activity, 'resize_end');
                                                                        }}
                                                                    />
                                                                </>
                                                            )}
                                                            <span className="truncate px-2">{width > 70 ? row.activity.name : ''}</span>
                                                        </div>
                                                    );
                                                })()}
                                            </div>
                                        </div>
                                    ),
                                )}

                                <svg
                                    className="pointer-events-none absolute z-10"
                                    style={{ left: LABEL_WIDTH, top: 0 }}
                                    width={timelineWidth}
                                    height={bodyHeight}
                                    aria-hidden
                                >
                                    <defs>
                                        <marker id="gantt-arrow" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto">
                                            <path d="M0,0 L6,3 L0,6 z" className="fill-muted-foreground" />
                                        </marker>
                                    </defs>
                                    {projects.map((project) => {
                                        const first = rows.findIndex((row) => row.kind === 'project' && row.project.id === project.id);
                                        const left = (diffDays(from, project.due_on) + 1) * px;

                                        return (
                                            <line
                                                key={`due${project.id}`}
                                                x1={left}
                                                x2={left}
                                                y1={first * ROW_HEIGHT}
                                                y2={(first + 1 + project.activities.length) * ROW_HEIGHT}
                                                stroke="#ef4444"
                                                strokeWidth={2}
                                                strokeDasharray="4 3"
                                            />
                                        );
                                    })}
                                    <line x1={todayLeft} x2={todayLeft} y1={0} y2={bodyHeight} className="stroke-primary" strokeWidth={2} />
                                    {projects.flatMap((project) =>
                                        project.activities.flatMap((activity) =>
                                            activity.dependency_ids.flatMap((dependencyId) => {
                                                const source = projects
                                                    .flatMap((p) => p.activities)
                                                    .find((candidate) => candidate.id === dependencyId);
                                                const sourceRow = rowOf.get(dependencyId);
                                                const targetRow = rowOf.get(activity.id);

                                                if (!source || sourceRow === undefined || targetRow === undefined) {
                                                    return [];
                                                }

                                                const x1 = barOf(source).left + barOf(source).width;
                                                const y1 = sourceRow * ROW_HEIGHT + ROW_HEIGHT / 2 + 1;
                                                const x2 = barOf(activity).left;
                                                const y2 = targetRow * ROW_HEIGHT + ROW_HEIGHT / 2 + 1;
                                                const midX = Math.max(x1 + 8, Math.min(x2 - 6, x1 + 8));

                                                return [
                                                    <path
                                                        key={`dep${dependencyId}-${activity.id}`}
                                                        d={`M${x1},${y1} H${midX} V${y2} H${x2 - 2}`}
                                                        fill="none"
                                                        className="stroke-muted-foreground"
                                                        strokeWidth={1.5}
                                                        markerEnd="url(#gantt-arrow)"
                                                    />,
                                                ];
                                            }),
                                        ),
                                    )}
                                </svg>
                            </div>
                        </div>
                    </div>
                )}

                {selectedActivity && (
                    <section aria-live="polite" className="border-sidebar-border/70 grid gap-3 rounded-xl border p-4 text-sm">
                        <div>
                            <h2 className="font-semibold">{selectedActivity.name}</h2>
                            <p className="text-muted-foreground">
                                {statusLabel(selectedActivity.status)} · dal {formatDate(selectedActivity.starts_on)} al{' '}
                                {formatDate(selectedActivity.due_on)}
                                {selectedActivity.assignees.length > 0 && ` · ${selectedActivity.assignees.join(', ')}`}
                            </p>
                        </div>
                        {selectedActivity.can_move ? (
                            <div className="grid gap-2 sm:grid-cols-3">
                                {(
                                    [
                                        { key: 'move', mode: 'move', label: 'Sposta con inizio il', date: panel.move },
                                        { key: 'start', mode: 'resize_start', label: 'Nuovo inizio', date: panel.start },
                                        { key: 'end', mode: 'resize_end', label: 'Nuova scadenza', date: panel.end },
                                    ] as const
                                ).map((field) => (
                                    <div key={field.key} className="flex items-end gap-2">
                                        <label className="grid flex-1 gap-1">
                                            <span className="text-muted-foreground">{field.label}</span>
                                            <Input
                                                type="date"
                                                value={field.date}
                                                onChange={(event) => setPanel({ ...panel, [field.key]: event.target.value })}
                                            />
                                        </label>
                                        <Button
                                            variant="outline"
                                            disabled={!field.date}
                                            onClick={() =>
                                                requestShift(
                                                    selectedActivity,
                                                    field.mode,
                                                    field.mode === 'resize_end' ? selectedActivity.starts_on : field.date,
                                                    field.mode === 'resize_end' ? field.date : selectedActivity.due_on,
                                                )
                                            }
                                        >
                                            Anteprima
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground">Non hai i permessi per spostare questa attività.</p>
                        )}
                    </section>
                )}
            </div>

            <Dialog open={request !== null} onOpenChange={(open) => !open && closePreview()}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{previewReady && preview.projects.length > 0 ? 'Sei sicuro?' : 'Spostare le attività?'}</DialogTitle>
                        <DialogDescription>
                            {!previewReady && 'Calcolo delle attività coinvolte…'}
                            {previewReady && preview.blockers.length > 0 && 'Lo spostamento non è possibile: non si sposta niente.'}
                            {previewReady && preview.blockers.length === 0 && preview.changes.length === 0 && 'Questo non cambia nessuna data.'}
                            {previewReady &&
                                preview.blockers.length === 0 &&
                                preview.changes.length > 0 &&
                                `Si ${preview.changes.length === 1 ? 'sposta 1 attività' : `spostano ${preview.changes.length} attività`}, a catena quelle che dipendono da questa.`}
                        </DialogDescription>
                    </DialogHeader>

                    {previewReady && (
                        <div className="grid max-h-72 gap-3 overflow-y-auto text-sm">
                            {preview.blockers.length > 0 && (
                                <ul role="alert" className="text-destructive list-disc pl-5">
                                    {preview.blockers.map((blocker) => (
                                        <li key={blocker}>{blocker}</li>
                                    ))}
                                </ul>
                            )}
                            {preview.blockers.length === 0 && (
                                <ul className="divide-y">
                                    {preview.changes.map((change) => (
                                        <li key={change.activity_id} className="py-1.5">
                                            <span className="font-medium">{change.name}</span>{' '}
                                            <span className="text-muted-foreground">– {change.project}</span>
                                            <br />
                                            {formatDate(change.starts_on)} – {formatDate(change.due_on)} → {formatDate(change.new_starts_on)} –{' '}
                                            {formatDate(change.new_due_on)}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {preview.blockers.length === 0 && preview.projects.length > 0 && (
                                <div className="rounded-md border border-amber-500/50 bg-amber-500/10 p-3">
                                    <p className="font-medium">Confermando si estende la scadenza di:</p>
                                    <ul className="list-disc pl-5">
                                        {preview.projects.map((project) => (
                                            <li key={project.project_id}>
                                                {project.name}: {formatDate(project.due_on)} → {formatDate(project.new_due_on)}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}

                    <DialogFooter>
                        <Button variant="outline" onClick={closePreview}>
                            {previewReady && preview.blockers.length > 0 ? 'Chiudi' : 'Annulla'}
                        </Button>
                        {previewReady && preview.blockers.length === 0 && preview.changes.length > 0 && (
                            <Button onClick={applyShift}>Conferma e sposta</Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
