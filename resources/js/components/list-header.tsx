import { ReactNode } from 'react';

interface ListHeaderProps {
    title: string;
    /** Optional right-side content (badge, button, etc.) */
    action?: ReactNode;
    /** Optional subtitle under the title */
    description?: string;
}

/**
 * Shared neon-blue gradient header bar used at the top of every list/table card
 * so all master & list pages look consistent.
 *
 * The "neon" feel comes from:
 *  - a multi-stop gradient (deep blue -> bright blue -> cyan accent),
 *  - a subtle inner highlight at the top (white/15),
 *  - a soft outer glow ring underneath (blue-500/30 shadow),
 *  - a faint diagonal sheen overlay.
 */
export function ListHeader({ title, action, description }: ListHeaderProps) {
    return (
        <div
            className="relative flex items-center justify-between gap-4 overflow-hidden rounded-t-xl border-b border-blue-400/40 px-5 py-4 text-white shadow-[0_8px_24px_-12px_rgba(59,130,246,0.55)]"
            style={{
                backgroundImage:
                    'linear-gradient(110deg, #1e3a8a 0%, #2563eb 45%, #0ea5e9 100%)',
            }}
        >
            {/* Top inner highlight for the "neon" sheen */}
            <span
                aria-hidden
                className="pointer-events-none absolute inset-x-0 top-0 h-px bg-white/40"
            />
            {/* Diagonal soft sheen overlay */}
            <span
                aria-hidden
                className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.18),transparent_55%)]"
            />

            <div className="relative">
                <h2 className="text-base font-semibold tracking-tight drop-shadow-[0_1px_8px_rgba(255,255,255,0.25)]">
                    {title}
                </h2>
                {description && (
                    <p className="text-xs text-blue-100/90">{description}</p>
                )}
            </div>
            {action && (
                <div className="relative flex items-center gap-2">{action}</div>
            )}
        </div>
    );
}
