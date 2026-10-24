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

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: { success: string | null };
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
    can_update: boolean;
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
