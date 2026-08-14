import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { cn } from '@/lib/utils';
import { IconAlertTriangle, IconTrash } from '@tabler/icons-react';
import { ReactNode } from 'react';

type Variant = 'destructive' | 'default';

interface ConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: Variant;
    loading?: boolean;
    onConfirm: () => void;
}

/**
 * App-themed confirmation dialog. Replaces the browser-native `confirm()`
 * popup so destructive/important actions follow the blue/neon design system.
 *
 * Defaults to a destructive (red) confirm button. Pass variant="default" for
 * non-destructive confirmations (e.g. "Lanjutkan publish?").
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Hapus',
    cancelLabel = 'Batal',
    variant = 'destructive',
    loading = false,
    onConfirm,
}: ConfirmDialogProps) {
    const isDestructive = variant === 'destructive';

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="overflow-hidden p-0 sm:rounded-xl">
                {/* Themed neon header */}
                <div
                    className={cn(
                        'relative flex items-center gap-3 overflow-hidden border-b px-6 py-4 text-white',
                        isDestructive
                            ? 'border-rose-400/40 shadow-[0_8px_24px_-12px_rgba(244,63,94,0.55)]'
                            : 'border-blue-400/40 shadow-[0_8px_24px_-12px_rgba(59,130,246,0.55)]',
                    )}
                    style={{
                        backgroundImage: isDestructive
                            ? 'linear-gradient(110deg, #881337 0%, #e11d48 50%, #fb7185 100%)'
                            : 'linear-gradient(110deg, #1e3a8a 0%, #2563eb 45%, #0ea5e9 100%)',
                    }}
                >
                    <span
                        aria-hidden
                        className="pointer-events-none absolute inset-x-0 top-0 h-px bg-white/40"
                    />
                    <span
                        aria-hidden
                        className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.18),transparent_55%)]"
                    />

                    <div className="relative flex h-9 w-9 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/30 backdrop-blur-sm">
                        {isDestructive ? (
                            <IconTrash className="h-5 w-5" />
                        ) : (
                            <IconAlertTriangle className="h-5 w-5" />
                        )}
                    </div>
                    <AlertDialogHeader className="relative space-y-0 text-left">
                        <AlertDialogTitle className="text-base font-semibold tracking-tight text-white">
                            {title}
                        </AlertDialogTitle>
                    </AlertDialogHeader>
                </div>

                <div className="space-y-4 px-6 py-5">
                    {description && (
                        <AlertDialogDescription className="text-sm leading-relaxed text-muted-foreground">
                            {description}
                        </AlertDialogDescription>
                    )}

                    <AlertDialogFooter className="gap-2">
                        <AlertDialogCancel disabled={loading}>
                            {cancelLabel}
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={(e) => {
                                e.preventDefault();
                                onConfirm();
                            }}
                            disabled={loading}
                            className={cn(
                                isDestructive &&
                                    'bg-rose-600 text-white hover:bg-rose-700 focus-visible:ring-rose-500',
                            )}
                        >
                            {loading ? 'Memproses...' : confirmLabel}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </div>
            </AlertDialogContent>
        </AlertDialog>
    );
}
