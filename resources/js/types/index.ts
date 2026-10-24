import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface ActiveTimer {
    id: number;
    activity_id: number;
    activity_name: string;
    project_name: string;
    description: string | null;
    /** Istante di avvio, ISO 8601 con offset. */
    started_at: string;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: { success: string | null };
    timers: ActiveTimer[];
    timer_warning_minutes: number;
    /** Ora del server (ISO 8601): corregge l'orologio del browser nel tempo trascorso. */
    server_now: string;
    [key: string]: unknown;
}

export type Role = 'capo' | 'capo_area' | 'hr' | 'dipendente';

export type TimeFormat = '24h' | '12h';

export type Palette = 'wireframe' | 'scuro' | 'chiaro' | 'personalizzata';

export type Locale = 'it' | 'en';

export interface User {
    id: number;
    name: string;
    surname: string;
    email: string;
    role: Role;
    area_id: number | null;
    daily_capacity: string;
    locale: Locale;
    time_format: TimeFormat;
    palette: Palette;
    custom_colors: Record<string, string> | null;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface Area {
    id: number;
    name: string;
    description: string | null;
    users_count: number;
}

export interface ClientContact {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    is_primary: boolean;
    projects_count: number;
}

export interface ClientListItem {
    id: number;
    company_name: string;
    active_projects_count: number;
    primary_contact: string | null;
    contacts: ClientContact[];
}

export interface ClientDetail {
    id: number;
    company_name: string;
    contacts: ClientContact[];
    projects: { id: number; name: string; status: string; due_on: string | null }[];
}

export interface PersonOption {
    id: number;
    name: string;
}

export interface ProjectOption {
    id: number;
    name: string;
    client_name: string;
    due_on: string;
}

export interface ActivityListItem {
    id: number;
    name: string;
    description: string | null;
    project_id: number;
    project_name: string;
    project_due_on: string;
    client_name: string;
    assignees: PersonOption[];
    starts_on: string;
    due_on: string;
    planned_hours: number;
    used_minutes: number;
    status: string;
    dependency_ids: number[];
    can_update: boolean;
}

/** Attività tra cui scegliere "dipende da" (stesso progetto). */
export interface DependencyOption {
    id: number;
    name: string;
    project_id: number;
}

export interface ClientOption {
    id: number;
    company_name: string;
    contacts: PersonOption[];
}

export interface ProjectListItem {
    id: number;
    name: string;
    client_name: string;
    status: string;
    starts_on: string;
    due_on: string;
    planned_hours: number;
    used_minutes: number;
}

export interface ProjectDetail {
    id: number;
    name: string;
    description: string | null;
    client_id: number;
    client_name: string;
    status: string;
    starts_on: string;
    due_on: string;
    planned_hours: number;
    used_minutes: number;
    template_name: string | null;
    members: PersonOption[];
    contacts: { id: number; name: string; email: string | null; phone: string | null }[];
}

export interface ProjectTimeEntry {
    id: number;
    date: string;
    user_name: string;
    activity_name: string;
    duration_minutes: number;
}

export interface AssigneeOption extends PersonOption {
    area_id: number | null;
}

export interface TemplateActivityItem {
    name: string;
    description: string | null;
    planned_hours: number;
    start_day: number;
    end_day: number;
    suggested_area_id: number | null;
    suggested_role: Role | null;
}

export interface TemplateListItem {
    id: number;
    name: string;
    activities: TemplateActivityItem[];
}

/** Template come opzione nel drawer del progetto: ogni attività ha l'id, serve per scegliere gli assegnatari. */
export interface TemplateOption {
    id: number;
    name: string;
    activities: (TemplateActivityItem & { id: number })[];
}

export interface ActivityOption {
    id: number;
    name: string;
    project_id: number;
    project_name: string;
}

export interface TimeEntryItem {
    id: number;
    activity_id: number;
    activity_name: string;
    project_name: string;
    client_name: string;
    started_at: string;
    ended_at: string;
    duration_minutes: number;
    description: string | null;
    source: 'manuale' | 'timer';
    activity_over_budget: boolean;
    project_over_budget: boolean;
}

export interface TimeEntryDay {
    date: string;
    total_minutes: number;
    entries: TimeEntryItem[];
}

export interface ManagedUser {
    id: number;
    name: string;
    surname: string;
    email: string;
    role: Role;
    area_id: number | null;
    area_name: string | null;
    daily_capacity: number;
    status: 'attivo' | 'invito_in_attesa';
    can_edit: boolean;
    is_self: boolean;
}

export interface AreaOption {
    id: number;
    name: string;
}

export type AbsenceType = 'ferie' | 'permesso';

export type AbsenceStatus = 'in_attesa' | 'confermata' | 'rifiutata';

export interface Absence {
    id: number;
    user_name: string;
    type: AbsenceType;
    starts_on: string;
    ends_on: string;
    starts_time: string | null;
    ends_time: string | null;
    hours: number | null;
    needs_area_head: boolean;
    hr_approved: boolean;
    /** Chi dà la conferma finale: "HR", o "Capo" per le assenze dell'HR. */
    approver_label: string;
    area_head_approved: boolean;
    note: string | null;
    rejection_reason: string | null;
    is_own: boolean;
    status: AbsenceStatus;
    can_update: boolean;
    can_delete: boolean;
    can_confirm: boolean;
    can_approve: boolean;
    can_reject: boolean;
}

export interface Holiday {
    id: number;
    date: string;
    name: string;
}

export interface AppNotification {
    id: string;
    message: string;
}

export type LoadLevel = 'sotto_capacita' | 'pieno' | 'sovraccarico';

export interface CalendarDay {
    date: string;
    is_weekend: boolean;
    holiday: string | null;
    is_today: boolean;
}

export interface CalendarItem {
    allocation_id: number;
    can_move: boolean;
    activity_starts_on: string;
    activity_due_on: string;
    project_due_on: string;
    activity_id: number;
    activity: string;
    project: string;
    hours: number;
}

export interface CalendarCell {
    capacity: number;
    load: number;
    level: LoadLevel | null;
    absence: AbsenceType | null;
    items: CalendarItem[];
}

export interface CalendarPerson {
    id: number;
    name: string;
    area_name: string | null;
    is_self: boolean;
    days: Record<string, CalendarCell>;
}

export interface GanttActivity {
    id: number;
    name: string;
    status: string;
    starts_on: string;
    due_on: string;
    assignees: string[];
    dependency_ids: number[];
    can_move: boolean;
}

export interface GanttProject {
    id: number;
    name: string;
    starts_on: string;
    due_on: string;
    activities: GanttActivity[];
}

export interface GanttFilters {
    project_id: number | null;
    area_id: number | null;
    user_id: number | null;
    status: string | null;
}

export type GanttShiftMode = 'move' | 'resize_start' | 'resize_end';

export interface GanttPreview {
    activity_id: number;
    /** La richiesta a cui si riferisce l'anteprima: serve a scartare quelle rimaste dalla richiesta precedente. */
    requested: { mode: GanttShiftMode; start: string; due: string };
    mode: GanttShiftMode;
    shift: number;
    changes: {
        activity_id: number;
        name: string;
        project: string;
        starts_on: string;
        due_on: string;
        new_starts_on: string;
        new_due_on: string;
    }[];
    projects: { project_id: number; name: string; due_on: string; new_due_on: string }[];
    blockers: string[];
}

export interface DashboardFilters {
    from: string;
    to: string;
    project_id: number | null;
    day: string;
    user_ids: number[];
    user_id: number | null;
    area_id: number | null;
    granularity: 'day' | 'week' | 'month';
}

export interface ProjectHours {
    total_hours: number;
    projects: { id: number; name: string; client: string; planned_hours: number; period_hours: number; total_hours: number }[];
    selected: {
        project_id: number;
        activities: { id: number; name: string; planned_hours: number; hours: number }[];
        people: { id: number; name: string; hours: number }[];
    } | null;
    options: { id: number; name: string }[];
}

export interface DayHours {
    day: string;
    people: {
        id: number;
        name: string;
        capacity_hours: number;
        total_minutes: number;
        entries: {
            id: number;
            activity: string;
            project: string;
            started_at: string;
            ended_at: string;
            minutes: number;
            description: string | null;
        }[];
    }[];
    options: { id: number; name: string }[];
}

export interface WorkedHours {
    granularity: 'day' | 'week' | 'month';
    buckets: { label: string; from: string; to: string }[];
    rows: { id: number; name: string; minutes: number[]; total: number }[];
    column_totals: number[];
    grand_total: number;
    people: { id: number; name: string }[];
    areas: { id: number; name: string }[];
}
