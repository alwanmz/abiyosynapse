import { usePage } from '@inertiajs/react';
import { CheckCircle, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

interface FlashProps extends Record<string, unknown> {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

export function FlashMessage() {
    const { flash } = usePage<FlashProps>().props;
    const current = flash?.success
        ? { type: 'success' as const, message: flash.success }
        : flash?.error
          ? { type: 'error' as const, message: flash.error }
          : null;
    const flashKey = current ? `${current.type}:${current.message}` : null;
    const [dismissedKey, setDismissedKey] = useState<string | null>(null);

    useEffect(() => {
        if (!flashKey) return;
        const timer = setTimeout(() => setDismissedKey(flashKey), 4000);
        return () => clearTimeout(timer);
    }, [flashKey]);

    if (!current || dismissedKey === flashKey) return null;

    const isSuccess = current.type === 'success';

    return (
        <div
            className={`fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-lg border px-4 py-3 shadow-lg transition-all duration-300 ${
                isSuccess
                    ? 'border-nx-andon-run/30 bg-nx-andon-run-bg text-nx-andon-run'
                    : 'border-nx-andon-stop/30 bg-nx-andon-stop-bg text-nx-andon-stop'
            }`}
        >
            {isSuccess ? (
                <CheckCircle className="h-4 w-4 shrink-0" />
            ) : (
                <XCircle className="h-4 w-4 shrink-0" />
            )}
            <span className="text-sm font-medium">{current.message}</span>
            <button
                type="button"
                onClick={() => setDismissedKey(flashKey)}
                className="ml-2 opacity-60 hover:opacity-100"
            >
                ✕
            </button>
        </div>
    );
}
