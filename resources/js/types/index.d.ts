import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Role {
    id: number;
    name: string;
    display_name: string;
    description?: string;
    permissions?: { id: number; name: string }[];
}

export interface Auth {
    user: User;
    client?: ClientPortalAuth | null;
}

export interface ClientPortalAuth {
    id: number;
    kode: string;
    nama: string;
    username: string | null;
    email: string | null;
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
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    permission?: string;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    company: {
        nama_perusahaan: string | null;
        logo_path: string | null;
    };
    ai: {
        enabled: boolean;
    };
    portalAi?: {
        enabled: boolean;
        limit: number;
        remaining: number;
    } | null;
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    avatar_url?: string | null;
    avatar_path?: string | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    role?: Role;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}
export interface DailyLog {
    id: number;
    user_id: number;
    ticket_id?: number;
    log_date: string;
    category?: string | null;
    description: string;
    is_automated: boolean;
    created_at: string;
    ticket?: {
        id: number;
        title: string;
        ticket_number: string;
    };
    minute?: {
        id: number;
        title: string;
    } | null;
}
