import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Role {
    id: number;
    name: string;
    display_name: string;
    description?: string;
    permissions?: { id: number; name: string }[];
}

export interface Company {
    id: number;
    name: string;
    code: string;
    logo_path?: string | null;
    pivot?: {
        role_id: number;
        is_default: boolean;
    };
}

export interface Auth {
    user: User;
    role?: Role | null;
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
    locale: string;
    quote: { message: string; author: string };
    auth: Auth;
    ai: {
        enabled: boolean;
    };
    sidebarOpen: boolean;
    currentCompany: Company | null;
    companies: Company[];
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
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}
