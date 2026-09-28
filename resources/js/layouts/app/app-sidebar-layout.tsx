import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { FlashMessage } from '@/components/flash-message';
import { TrialBanner } from '@/components/trial-banner';
import { BreadcrumbsProvider } from '@/hooks/use-breadcrumbs';
import { type PropsWithChildren } from 'react';

export default function AppSidebarLayout({ children }: PropsWithChildren) {
    return (
        <BreadcrumbsProvider>
            <AppShell variant="sidebar">
                <AppSidebar />
                <AppContent variant="sidebar" className="overflow-x-hidden">
                    <TrialBanner />
                    <AppSidebarHeader />
                    {children}
                </AppContent>
                <FlashMessage />
            </AppShell>
        </BreadcrumbsProvider>
    );
}
