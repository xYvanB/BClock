import { FlashMessage } from '@/components/flash-message';
import { HoursProgress } from '@/components/hours-progress';
import { ProjectFormSheet } from '@/components/project-form-sheet';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/lib/i18n';
import { statusLabel, statusOptions } from '@/lib/status';
import { formatDay } from '@/lib/time';
import {
    type AreaOption,
    type AssigneeOption,
    type BreadcrumbItem,
    type ClientOption,
    type PersonOption,
    type ProjectListItem,
    type TemplateOption,
} from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Progetti', href: '/projects' }];

interface Filters {
    client_id: string;
    area_id: string;
    status: string;
    archived: boolean;
}

interface ProjectsIndexProps {
    projects: ProjectListItem[];
    filters: Filters;
    clients: ClientOption[];
    users: PersonOption[];
    areas: AreaOption[];
    templates: TemplateOption[];
    assignees: AssigneeOption[];
}

const fieldClass =
    'border-input bg-background focus-visible:ring-ring h-9 rounded-md border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none';

export default function ProjectsIndex({ projects, filters, clients, users, areas, templates, assignees }: ProjectsIndexProps) {
    const [sheetOpen, setSheetOpen] = useState(false);

    const applyFilters = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };

        router.get(
            route('projects.index'),
            {
                client_id: next.client_id || undefined,
                area_id: next.area_id || undefined,
                status: next.status || undefined,
                archived: next.archived ? 1 : undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Progetti')} />

            <div className="flex flex-col gap-4 p-4">
                <FlashMessage />

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">{t('Progetti')}</h1>
                    <Button onClick={() => setSheetOpen(true)}>
                        <Plus /> {t('Nuovo progetto')}
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <select
                        aria-label={t('Filtra per cliente')}
                        className={fieldClass}
                        value={filters.client_id}
                        onChange={(event) => applyFilters({ client_id: event.target.value })}
                    >
                        <option value="">{t('Tutti i clienti')}</option>
                        {clients.map((client) => (
                            <option key={client.id} value={client.id}>
                                {client.company_name}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label={t('Filtra per area')}
                        className={fieldClass}
                        value={filters.area_id}
                        onChange={(event) => applyFilters({ area_id: event.target.value })}
                    >
                        <option value="">{t('Tutte le aree')}</option>
                        {areas.map((area) => (
                            <option key={area.id} value={area.id}>
                                {area.name}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label={t('Filtra per stato')}
                        className={fieldClass}
                        value={filters.status}
                        onChange={(event) => applyFilters({ status: event.target.value })}
                    >
                        <option value="">{t('Tutti gli stati')}</option>
                        {statusOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {t(option.label)}
                            </option>
                        ))}
                    </select>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={filters.archived} onChange={(event) => applyFilters({ archived: event.target.checked })} />

                        {t('Mostra archiviati')}
                    </label>
                </div>

                {projects.length === 0 ? (
                    <div className="border-sidebar-border/70 flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <p className="text-muted-foreground">
                            {filters.client_id || filters.area_id || filters.status
                                ? t('Nessun progetto con questi filtri')
                                : t('Nessun progetto – crea il primo')}
                        </p>
                        <Button onClick={() => setSheetOpen(true)}>{t('Crea progetto')}</Button>
                    </div>
                ) : (
                    <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left">
                                <tr>
                                    <th className="px-4 py-3 font-medium">{t('Nome')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Cliente')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Stato')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Date')}</th>
                                    <th className="px-4 py-3 font-medium">{t('Ore (usate / previste)')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {projects.map((project) => (
                                    <tr key={project.id} className="border-sidebar-border/70 border-t">
                                        <td className="px-4 py-3 font-medium">
                                            <Link href={route('projects.show', project.id)} className="hover:underline">
                                                {project.name}
                                            </Link>
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">{project.client_name}</td>
                                        <td className="px-4 py-3">{statusLabel(project.status)}</td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {formatDay(project.starts_on)} → {formatDay(project.due_on)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <HoursProgress usedMinutes={project.used_minutes} plannedHours={project.planned_hours} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <ProjectFormSheet
                open={sheetOpen}
                project={null}
                clients={clients}
                users={users}
                templates={templates}
                assignees={assignees}
                onClose={() => setSheetOpen(false)}
            />
        </AppLayout>
    );
}
