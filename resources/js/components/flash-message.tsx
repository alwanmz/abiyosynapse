import { usePage } from '@inertiajs/react';
import { CheckCircle, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

export function FlashMessage() {
    const { flash } = usePage().props as any;
    const [visible, setVisible] = useState(false);
    const [current, setCurrent] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

    useEffect(() => {
        if (flash?.success) {
            setCurrent({ type: 'success', message: flash.success });
            setVisible(true);
        } else if (flash?.error) {
            setCurrent({ type: 'error', message: flash.error });
            setVisible(true);
        }
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        if (!visible) return;
        const timer = setTimeout(() => setVisible(false), 4000);
        return () => clearTimeout(timer);
    }, [visible]);

    if (!visible || !current) return null;

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
                onClick={() => setVisible(false)}
                className="ml-2 opacity-60 hover:opacity-100"
            >
                ✕
            </button>
        </div>
    );
}
