import { SidebarProvider } from '@/components/ui/sidebar';
import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

interface AppShellProps {
    children: React.ReactNode;
    variant?: 'header' | 'sidebar';
}

export function AppShell({ children, variant = 'header' }: AppShellProps) {
    const isOpen = usePage<SharedData>().props.sidebarOpen;

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    }

    // AppShell now sits inside the persistent Inertia layout (see
    // Page.layout on each page component), so this only mounts once per
    // full page load — defaultOpen reflects the cookie at that load and
    // subsequent client-side toggles/navigations don't re-read it.
    return <SidebarProvider defaultOpen={isOpen}>{children}</SidebarProvider>;
}
