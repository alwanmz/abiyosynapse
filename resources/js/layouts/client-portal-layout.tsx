import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ClientPortalSidebar } from '@/components/client-portal-sidebar';
import { FlashMessage } from '@/components/flash-message';
import { FloatingClientChat } from '@/components/floating-client-chat';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

export default function ClientPortalLayout({
    children,
    breadcrumbs = [],
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    return (
        <AppShell variant="sidebar">
            <ClientPortalSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
            <FlashMessage />
            <FloatingClientChat />
        </AppShell>
    );
}
