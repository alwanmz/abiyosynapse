import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { FloatingAiChat } from '@/components/floating-ai-chat';
import { Toaster } from '@/components/ui/toast';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default ({ children, breadcrumbs, ...props }: AppLayoutProps) => (
    <AppLayoutTemplate breadcrumbs={breadcrumbs} {...props}>
        {children}
        <FloatingAiChat />
        <Toaster />
    </AppLayoutTemplate>
);
