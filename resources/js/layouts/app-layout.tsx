import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { Toaster } from '@/components/ui/toast';
import { type ReactNode } from 'react';

interface AppLayoutProps {
    children: ReactNode;
}

export default function AppLayout({ children }: AppLayoutProps) {
    return (
        <AppLayoutTemplate>
            {children}
            <Toaster />
        </AppLayoutTemplate>
    );
}
