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
import {
    dashboard,
    manageUsers,
    projects,
    teams,
    tickets,
    timelines,
} from '@/routes';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Briefcase,
    Building2,
    Calendar,
    ChartArea,
    ChartPie,
    ClipboardList,
    Contact,
    FileSpreadsheet,
    FolderRoot,
    Handshake,
    ListChecks,
    ShieldCheck,
    Tickets,
    Users,
    Wrench,
} from 'lucide-react';
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
        title: 'Perusahaan',
        permission: 'companies.view',
        href: '/master/company',
        icon: Building2,
    },
    {
        title: 'Klien',
        permission: 'clients.view',
        href: '/master/clients',
        icon: Contact,
    },
    {
        title: 'Tim',
        permission: 'teams.view',
        href: teams(),
        icon: Handshake,
    },
    {
        title: 'Proyek',
        permission: 'projects.view',
        href: projects(),
        icon: FolderRoot,
    },
    {
        title: 'Linimasa',
        permission: 'timelines.view',
        href: timelines(),
        icon: Calendar,
    },
    {
        title: 'Jenis Tugas',
        permission: 'task-types.view',
        href: '/master/task-types',
        icon: ListChecks,
    },
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
];

const jobsNavItems: NavItem[] = [
    {
        title: 'Tiket',
        permission: 'tickets.view',
        href: tickets(),
        icon: Tickets,
    },
    {
        title: 'Catatan Harian',
        permission: 'daily-logs.view',
        href: '/daily-logs',
        icon: Briefcase,
    },
    {
        title: 'Notulensi',
        permission: 'minutes.view',
        href: '/minutes',
        icon: ClipboardList,
    },
    {
        title: 'Guidebook',
        permission: 'guidebooks.view',
        href: '/guidebooks',
        icon: BookOpen,
    },
];

const reportNavItems: NavItem[] = [
    {
        title: 'Analitik',
        permission: 'analytics.view',
        href: '/analytics',
        icon: ChartPie,
    },
    {
        title: 'Laporan Teamboard',
        permission: 'reports.view',
        href: '/reports',
        icon: FileSpreadsheet,
    },
    {
        title: 'Laporan Maintenance',
        permission: 'maintenance-reports.view',
        href: '/maintenance-reports',
        icon: Wrench,
    },
];

const footerNavItems: NavItem[] = [
    // Hidden for now — re-enable when public repo / docs are ready.
    // {
    //     title: 'Repository',
    //     href: 'https://github.com/alwanmz/teamboard',
    //     icon: Folder,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits#react',
    //     icon: BookOpen,
    // },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth.user?.role?.name;

    const isSuperAdmin = userRole === 'super_admin' || userRole === 'admin';
    const permissions = auth.user?.role?.permissions?.map((permission) => permission.name) ?? [];
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
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={overviewNavItems} label="Ringkasan" />
                <NavMain items={filterByPermission(masterNavItems)} label="Data Master" />
                <NavMain items={filterByPermission(jobsNavItems)} label="Ruang Kerja" />
                <NavMain items={filterByPermission(reportNavItems)} label="Laporan" />
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
