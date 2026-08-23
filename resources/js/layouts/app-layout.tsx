import { AiCopilotBubble } from '@/components/ai-copilot-bubble';
import { Toaster } from '@/components/ui/toast';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type ReactNode } from 'react';

interface AppLayoutProps {
    children: ReactNode;
}

export default function AppLayout({ children }: AppLayoutProps) {
    return (
        <AppLayoutTemplate>
            {children}
            <AiCopilotBubble />
            <Toaster />
        </AppLayoutTemplate>
    );
}
