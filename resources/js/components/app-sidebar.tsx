import { CompanySwitcher } from '@/components/company-switcher';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, manageUsers } from '@/routes';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Building2, ChartArea, ShieldCheck, Users } from 'lucide-react';
import AppLogo from './app-logo';

const overviewNavItems: NavItem[] = [
    {
        title: 'Dasbor',
        href: dashboard(),
        icon: ChartArea,
    },
];

const masterNavItems: NavItem[] = [
    {
        title: 'Pengguna',
        permission: 'users.view',
        href: manageUsers(),
        icon: Users,
    },
    {
        title: 'Hak Akses',
        permission: 'roles.view',
        href: '/roles',
        icon: ShieldCheck,
    },
    {
        title: 'Perusahaan',
        permission: 'companies.view',
        href: '/companies',
        icon: Building2,
    },
];

const footerNavItems: NavItem[] = [
    // Hidden for now — re-enable when public repo / docs are ready.
    // {
    //     title: 'Repository',
    //     href: 'https://github.com/...',
    //     icon: Folder,
    // },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth.role?.name;

    const isSuperAdmin = userRole === 'super_admin' || userRole === 'admin';
    const permissions = auth.role?.permissions?.map((permission) => permission.name) ?? [];
    const canView = (permission?: string) =>
        !permission || isSuperAdmin || permissions.includes(permission);
    const filterByPermission = (items: NavItem[]) => items.filter((item) => canView(item.permission));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-auto min-h-12 items-center py-2"
                        >
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <CompanySwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={overviewNavItems} label="Ringkasan" />
                <NavMain items={filterByPermission(masterNavItems)} label="Data Master" />
            </SidebarContent>

            <SidebarFooter>
                {footerNavItems.length > 0 && (
                    <NavFooter items={footerNavItems} className="mt-auto" />
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
