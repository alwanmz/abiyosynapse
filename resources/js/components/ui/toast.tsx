import * as React from 'react';
import { cn } from '@/lib/utils';

interface Toast {
    id: number;
    title: string;
    description?: string;
    variant?: 'default' | 'destructive';
}

let toastId = 0;
const listeners: Set<(toast: Toast) => void> = new Set();

export function toast(options: Omit<Toast, 'id'>) {
    const t: Toast = { ...options, id: ++toastId };
    listeners.forEach((fn) => fn(t));
}

toast.error = (title: string, opts?: { description?: string }) => {
    toast({ title, description: opts?.description, variant: 'destructive' });
};

toast.success = (title: string, opts?: { description?: string }) => {
    toast({ title, description: opts?.description, variant: 'default' });
};

export function Toaster() {
    const [toasts, setToasts] = React.useState<Toast[]>([]);

    React.useEffect(() => {
        const handler = (t: Toast) => {
            setToasts((prev) => [...prev, t]);
            setTimeout(() => {
                setToasts((prev) => prev.filter((x) => x.id !== t.id));
            }, 4000);
        };
        listeners.add(handler);
        return () => { listeners.delete(handler); };
    }, []);

    if (toasts.length === 0) return null;

    return (
        <div className="fixed top-4 right-4 z-[9999] flex flex-col gap-2">
            {toasts.map((t) => (
                <div
                    key={t.id}
                    className={cn(
                        'animate-in slide-in-from-top-2 fade-in min-w-[260px] max-w-sm rounded-lg border bg-background p-4 shadow-lg transition-all',
                        t.variant === 'destructive' && 'border-destructive/60'
                    )}
                >
                    <p className={cn(
                        'text-sm font-semibold',
                        t.variant === 'destructive' && 'text-destructive'
                    )}>
                        {t.title}
                    </p>
                    {t.description && (
                        <p className="mt-1 text-xs text-muted-foreground">{t.description}</p>
                    )}
                </div>
            ))}
        </div>
    );
}
