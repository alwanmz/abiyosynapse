import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { ChartArea, Kanban, LogOut, Tickets, Wrench } from 'lucide-react';

const portalNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/portal',
        icon: ChartArea,
    },
    {
        title: 'Kanban',
        href: '/portal/kanban',
        icon: Kanban,
    },
    {
        title: 'Tiket Saya',
        href: '/portal/tickets',
        icon: Tickets,
    },
    {
        title: 'Laporan Maintenance',
        href: '/portal/maintenance-reports',
        icon: Wrench,
    },
];

export function ClientPortalSidebar() {
    const { auth } = usePage<SharedData>().props;
    const client = auth.client;

    const handleLogout = () => {
        router.post('/portal/logout');
    };

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
                            <Link href="/portal" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={portalNavItems} label="Portal Klien" />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <div className="flex flex-col gap-0.5 px-2 py-1.5 group-data-[collapsible=icon]:hidden">
                            <span
                                className="line-clamp-2 break-words text-sm font-semibold leading-snug"
                                title={client?.nama ?? 'Klien'}
                            >
                                {client?.nama ?? 'Klien'}
                            </span>
                            {client?.email && (
                                <span
                                    className="truncate text-xs text-muted-foreground"
                                    title={client.email}
                                >
                                    {client.email}
                                </span>
                            )}
                        </div>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            onClick={handleLogout}
                            tooltip={{ children: 'Keluar' }}
                        >
                            <LogOut />
                            <span>Keluar</span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}
