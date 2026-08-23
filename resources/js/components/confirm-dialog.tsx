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
 * popup. Follows the Nexumi "Hanko" principle (see
 * docs/design/nexumi-design-system.html §prinsip): red is reserved for the
 * confirm button itself on destructive actions — the header stays flat and
 * neutral so there's never more than one red element on screen.
 *
 * Defaults to a destructive (Hanko red) confirm button. Pass
 * variant="default" for non-destructive confirmations (e.g. "Lanjutkan
 * publish?").
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
            <AlertDialogContent className="overflow-hidden p-0 sm:rounded-lg">
                <div className="flex items-center gap-3 border-b border-border px-6 py-4">
                    <div
                        className={cn(
                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-full',
                            isDestructive
                                ? 'bg-nx-hanko-50 text-nx-hanko-500 dark:bg-nx-hanko-50'
                                : 'bg-nx-cyan-50 text-nx-cyan-700',
                        )}
                    >
                        {isDestructive ? (
                            <IconTrash className="h-5 w-5" />
                        ) : (
                            <IconAlertTriangle className="h-5 w-5" />
                        )}
                    </div>
                    <AlertDialogHeader className="space-y-0 text-left">
                        <AlertDialogTitle className="text-base font-semibold tracking-tight">
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
                        <AlertDialogCancel
                            disabled={loading}
                            className="border-nx-cancel bg-background text-nx-cancel hover:bg-nx-cancel-bg hover:text-nx-cancel"
                        >
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
                                    'bg-destructive text-white hover:bg-nx-hanko-700 focus-visible:ring-destructive/40',
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
