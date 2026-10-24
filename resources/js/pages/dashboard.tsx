import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { decimalSeparator, t } from '@/lib/i18n';
import { formatMinutes } from '@/lib/time';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type DashboardFilters, type DayHours, type ProjectHours, type WorkedHours } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { ReactNode, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

interface DashboardProps {
    filters: DashboardFilters;
    project_hours: ProjectHours | null;
    day_hours: DayHours | null;
    worked_hours: WorkedHours;
    can_export: boolean;
}

const fieldClass =
    'border-input bg-background focus-visible:ring-ring h-9 rounded-md border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none';

const formatDate = (iso: string) => iso.split('-').reverse().join('/');

const formatHours = (hours: number) => `${String(Number(hours.toFixed(2))).replace('.', decimalSeparator())} h`;

function Card({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <section className="border-sidebar-border/70 flex flex-col gap-4 rounded-xl border p-4">
            <div>
                <h2 className="text-lg font-semibold">{title}</h2>
                {description && <p className="text-muted-foreground text-sm">{description}</p>}
            </div>
            {children}
        </section>
    );
}

function Empty({ children }: { children: ReactNode }) {
    return <p className="text-muted-foreground border-sidebar-border/70 rounded-md border border-dashed p-6 text-center text-sm">{children}</p>;
}

/** Horizontal bar: filled part = `value` out of `max`; red if it exceeds the maximum. */
function Bar({ value, max, label }: { value: number; max: number; label: string }) {
    const ratio = max > 0 ? value / max : value > 0 ? 1 : 0;

    return (
        <div role="img" aria-label={label} className="bg-muted h-2.5 w-full overflow-hidden rounded-full">
            <div className={cn('h-full rounded-full', ratio > 1 ? 'bg-red-500' : 'bg-primary')} style={{ width: `${Math.min(100, ratio * 100)}%` }} />
        </div>
    );
}

/** Dashboard (F11): different cards per role, each with its own filters; the CSV export is by period, project and area. */
export default function Dashboard({ filters, project_hours, day_hours, worked_hours, can_export }: DashboardProps) {
    const [form, setForm] = useState({
        from: filters.from,
        to: filters.to,
        project_id: filters.project_id === null ? '' : String(filters.project_id),
        day: filters.day,
        user_ids: filters.user_ids,
        user_id: filters.user_id === null ? '' : String(filters.user_id),
        area_id: filters.area_id === null ? '' : String(filters.area_id),
        granularity: filters.granularity,
    });

    const params = () => Object.fromEntries(Object.entries(form).filter(([, value]) => (Array.isArray(value) ? value.length > 0 : value !== '')));

    const apply = () => router.get(route('dashboard'), params(), { preserveState: true, preserveScroll: true });

    const toggleUser = (id: number) =>
        setForm({ ...form, user_ids: form.user_ids.includes(id) ? form.user_ids.filter((current) => current !== id) : [...form.user_ids, id] });

    const period = (
        <div className="flex flex-wrap items-end gap-2">
            <label className="grid gap-1 text-sm">
                <span className="text-muted-foreground">{t('Dal')}</span>
                <Input type="date" value={form.from} onChange={(event) => setForm({ ...form, from: event.target.value })} />
            </label>
            <label className="grid gap-1 text-sm">
                <span className="text-muted-foreground">{t('Al')}</span>
                <Input type="date" value={form.to} onChange={(event) => setForm({ ...form, to: event.target.value })} />
            </label>
        </div>
    );

    const maxMinutes = Math.max(0, ...worked_hours.column_totals);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Dashboard')} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">{t('Dashboard')}</h1>
                    {can_export && (
                        <Button variant="outline" asChild>
                            <a href={route('dashboard.export', params())}>
                                <Download /> {t('Esporta CSV')}
                            </a>
                        </Button>
                    )}
                </div>

                {project_hours && (
                    <Card
                        title={t('Ore per progetto')}
                        description={t('Ore registrate nel periodo, e quelle utilizzate in totale rispetto alle previste.')}
                    >
                        <div className="flex flex-wrap items-end gap-2">
                            {period}
                            <label className="grid gap-1 text-sm">
                                <span className="text-muted-foreground">{t('Progetto')}</span>
                                <select
                                    className={fieldClass}
                                    value={form.project_id}
                                    onChange={(event) => setForm({ ...form, project_id: event.target.value })}
                                >
                                    <option value="">{t('Tutti')}</option>
                                    {project_hours.options.map((option) => (
                                        <option key={option.id} value={option.id}>
                                            {option.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <Button onClick={apply}>{t('Applica')}</Button>
                        </div>

                        {project_hours.projects.length === 0 ? (
                            <Empty>{t('Nessuna ora registrata nel periodo')}</Empty>
                        ) : (
                            <>
                                <p className="text-sm">
                                    {t('Totale nel periodo:')} <strong>{formatHours(project_hours.total_hours)}</strong>
                                </p>
                                <ul className="grid gap-3">
                                    {project_hours.projects.map((project) => (
                                        <li key={project.id} className="grid gap-1">
                                            <div className="flex flex-wrap justify-between gap-2 text-sm">
                                                <span className="font-medium">
                                                    {project.name} <span className="text-muted-foreground font-normal">– {project.client}</span>
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {t('nel periodo :period · utilizzate :used su :planned previste', {
                                                        period: formatHours(project.period_hours),
                                                        used: formatHours(project.total_hours),
                                                        planned: formatHours(project.planned_hours),
                                                    })}
                                                </span>
                                            </div>
                                            <Bar
                                                value={project.total_hours}
                                                max={project.planned_hours}
                                                label={t(':name: :used su :planned previste', {
                                                    name: project.name,
                                                    used: formatHours(project.total_hours),
                                                    planned: formatHours(project.planned_hours),
                                                })}
                                            />
                                        </li>
                                    ))}
                                </ul>

                                {project_hours.selected && (
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <h3 className="mb-1 text-sm font-medium">{t('Per attività')}</h3>
                                            <table className="w-full text-sm">
                                                <thead className="text-muted-foreground text-left">
                                                    <tr>
                                                        <th className="py-1 font-medium">{t('Attività')}</th>
                                                        <th className="py-1 text-right font-medium">{t('Periodo')}</th>
                                                        <th className="py-1 text-right font-medium">{t('Previste')}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {project_hours.selected.activities.map((activity) => (
                                                        <tr key={activity.id} className="border-t">
                                                            <td className="py-1">{activity.name}</td>
                                                            <td className="py-1 text-right">{formatHours(activity.hours)}</td>
                                                            <td className="py-1 text-right">{formatHours(activity.planned_hours)}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                        <div>
                                            <h3 className="mb-1 text-sm font-medium">{t('Per persona')}</h3>
                                            <ul className="grid gap-2 text-sm">
                                                {project_hours.selected.people.map((person) => (
                                                    <li key={person.id} className="grid gap-1">
                                                        <div className="flex justify-between">
                                                            <span>{person.name}</span>
                                                            <span>{formatHours(person.hours)}</span>
                                                        </div>
                                                        <Bar
                                                            value={person.hours}
                                                            max={Math.max(...project_hours.selected!.people.map((p) => p.hours))}
                                                            label={`${person.name}: ${formatHours(person.hours)}`}
                                                        />
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    </div>
                                )}
                            </>
                        )}
                    </Card>
                )}

                {day_hours && (
                    <Card
                        title={t('Ore per persona in una giornata')}
                        description={t('Le registrazioni del giorno e il totale rispetto alla capacità.')}
                    >
                        <div className="flex flex-wrap items-end gap-3">
                            <label className="grid gap-1 text-sm">
                                <span className="text-muted-foreground">{t('Giorno')}</span>
                                <Input type="date" value={form.day} onChange={(event) => setForm({ ...form, day: event.target.value })} />
                            </label>
                            {day_hours.options.length > 1 && (
                                <fieldset className="grid gap-1 text-sm">
                                    <legend className="text-muted-foreground">{t('Persone (nessuna = chi ha registrato)')}</legend>
                                    <div className="border-sidebar-border/70 flex max-h-28 flex-wrap gap-x-4 gap-y-1 overflow-y-auto rounded-md border p-2">
                                        {day_hours.options.map((option) => (
                                            <label key={option.id} className="flex items-center gap-1.5">
                                                <input
                                                    type="checkbox"
                                                    checked={form.user_ids.includes(option.id)}
                                                    onChange={() => toggleUser(option.id)}
                                                />
                                                {option.name}
                                            </label>
                                        ))}
                                    </div>
                                </fieldset>
                            )}
                            <Button onClick={apply}>{t('Applica')}</Button>
                        </div>

                        {day_hours.people.length === 0 ? (
                            <Empty>{t('Nessuna registrazione il :date', { date: formatDate(day_hours.day) })}</Empty>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2">
                                {day_hours.people.map((person) => (
                                    <div key={person.id} className="border-sidebar-border/70 rounded-lg border p-3">
                                        <div className="flex flex-wrap justify-between gap-2 text-sm">
                                            <strong>{person.name}</strong>
                                            <span
                                                className={cn(
                                                    person.total_minutes / 60 > person.capacity_hours && 'font-medium text-red-600 dark:text-red-400',
                                                )}
                                            >
                                                {t(':worked su :capacity', {
                                                    worked: formatMinutes(person.total_minutes),
                                                    capacity: formatHours(person.capacity_hours),
                                                })}
                                            </span>
                                        </div>
                                        <Bar
                                            value={person.total_minutes / 60}
                                            max={person.capacity_hours}
                                            label={t(':name: :worked su :capacity', {
                                                name: person.name,
                                                worked: formatMinutes(person.total_minutes),
                                                capacity: formatHours(person.capacity_hours),
                                            })}
                                        />
                                        {person.entries.length === 0 ? (
                                            <p className="text-muted-foreground mt-2 text-sm">{t('Nessuna registrazione.')}</p>
                                        ) : (
                                            <ul className="mt-2 divide-y text-sm">
                                                {person.entries.map((entry) => (
                                                    <li key={entry.id} className="flex justify-between gap-3 py-1">
                                                        <span>
                                                            {entry.activity} <span className="text-muted-foreground">– {entry.project}</span>
                                                            <span className="text-muted-foreground block text-xs">
                                                                {entry.started_at}–{entry.ended_at}
                                                                {entry.description ? ` · ${entry.description}` : ''}
                                                            </span>
                                                        </span>
                                                        <span className="shrink-0">{formatMinutes(entry.minutes)}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                )}

                <Card title={t('Ore lavorate')} description={t('Persone per periodo, con i totali.')}>
                    <div className="flex flex-wrap items-end gap-2">
                        {period}
                        <label className="grid gap-1 text-sm">
                            <span className="text-muted-foreground">{t('Raggruppa per')}</span>
                            <select
                                className={fieldClass}
                                value={form.granularity}
                                onChange={(event) => setForm({ ...form, granularity: event.target.value as DashboardFilters['granularity'] })}
                            >
                                <option value="day">{t('Giorno')}</option>
                                <option value="week">{t('Settimana')}</option>
                                <option value="month">{t('Mese')}</option>
                            </select>
                        </label>
                        {worked_hours.people.length > 1 && (
                            <label className="grid gap-1 text-sm">
                                <span className="text-muted-foreground">{t('Persona')}</span>
                                <select
                                    className={fieldClass}
                                    value={form.user_id}
                                    onChange={(event) => setForm({ ...form, user_id: event.target.value })}
                                >
                                    <option value="">{t('Tutte')}</option>
                                    {worked_hours.people.map((person) => (
                                        <option key={person.id} value={person.id}>
                                            {person.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                        {worked_hours.areas.length > 0 && (
                            <label className="grid gap-1 text-sm">
                                <span className="text-muted-foreground">{t('Area')}</span>
                                <select
                                    className={fieldClass}
                                    value={form.area_id}
                                    onChange={(event) => setForm({ ...form, area_id: event.target.value })}
                                >
                                    <option value="">{t('Tutte')}</option>
                                    {worked_hours.areas.map((area) => (
                                        <option key={area.id} value={area.id}>
                                            {area.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        )}
                        <Button onClick={apply}>{t('Applica')}</Button>
                    </div>

                    {worked_hours.rows.length === 0 ? (
                        <Empty>{t('Nessuna ora lavorata nel periodo')}</Empty>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground text-left">
                                        <th className="bg-background sticky left-0 py-2 pr-3 font-medium">{t('Persona')}</th>
                                        {worked_hours.buckets.map((bucket) => (
                                            <th key={bucket.from} className="px-2 py-2 text-right font-medium whitespace-nowrap">
                                                {t(bucket.label)}
                                            </th>
                                        ))}
                                        <th className="py-2 pl-3 text-right font-medium">{t('Totale')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {worked_hours.rows.map((row) => (
                                        <tr key={row.id} className="border-t">
                                            <th
                                                scope="row"
                                                className="bg-background sticky left-0 py-1.5 pr-3 text-left font-medium whitespace-nowrap"
                                            >
                                                {row.name}
                                            </th>
                                            {row.minutes.map((minutes, index) => (
                                                <td key={index} className={cn('px-2 py-1.5 text-right', minutes === 0 && 'text-muted-foreground')}>
                                                    {minutes === 0 ? '–' : formatMinutes(minutes)}
                                                </td>
                                            ))}
                                            <td className="py-1.5 pl-3 text-right font-semibold">{formatMinutes(row.total)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot>
                                    <tr className="border-t-2 font-semibold">
                                        <th scope="row" className="bg-background sticky left-0 py-2 pr-3 text-left">
                                            {t('Totale')}
                                        </th>
                                        {worked_hours.column_totals.map((minutes, index) => (
                                            <td key={index} className="px-2 py-2 text-right">
                                                {minutes === 0 ? '–' : formatMinutes(minutes)}
                                            </td>
                                        ))}
                                        <td className="py-2 pl-3 text-right">{formatMinutes(worked_hours.grand_total)}</td>
                                    </tr>
                                </tfoot>
                            </table>
                            {maxMinutes > 0 && worked_hours.buckets.length > 1 && (
                                <figure className="mt-6">
                                    <figcaption className="mb-2 text-sm font-medium">{t('Ore totali per periodo')}</figcaption>
                                    <div className="flex items-end gap-1" role="img" aria-label={t('Ore totali per periodo')}>
                                        {worked_hours.column_totals.map((minutes, index) => (
                                            <div
                                                key={worked_hours.buckets[index].from}
                                                className="flex min-w-0 flex-1 flex-col items-center justify-end gap-1"
                                                title={`${worked_hours.buckets[index].label}: ${formatMinutes(minutes)}`}
                                            >
                                                {worked_hours.buckets.length <= 14 && (
                                                    <span className="text-xs">{minutes === 0 ? '–' : formatMinutes(minutes)}</span>
                                                )}
                                                <div
                                                    className="bg-primary/70 w-full rounded-t"
                                                    style={{ height: `${Math.max(2, (minutes / maxMinutes) * 80)}px` }}
                                                />
                                                {worked_hours.buckets.length <= 14 && (
                                                    <span className="text-muted-foreground w-full truncate text-center text-xs">
                                                        {t(worked_hours.buckets[index].label)}
                                                    </span>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </figure>
                            )}
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
