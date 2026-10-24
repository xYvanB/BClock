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
