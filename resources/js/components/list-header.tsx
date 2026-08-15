import { ReactNode } from 'react';

interface ListHeaderProps {
    title: string;
    /** Optional right-side content (badge, button, etc.) */
    action?: ReactNode;
    /** Optional subtitle under the title */
    description?: string;
}

/**
 * Shared header bar used at the top of every list/table card so all
 * master & list pages look consistent. Follows the Nexumi "Ma" principle
 * (see docs/design/nexumi-design-system.html §pola) — a flat surface with a
 * hairline border does the separating, not a gradient or glow.
 */
export function ListHeader({ title, action, description }: ListHeaderProps) {
    return (
        <div className="flex items-center justify-between gap-4 border-b border-border bg-nx-n-50 px-5 py-3.5 dark:bg-card">
            <div>
                <h2 className="font-display text-sm font-semibold tracking-tight text-foreground">
                    {title}
                </h2>
                {description && (
                    <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>
                )}
            </div>
            {action && <div className="flex items-center gap-2">{action}</div>}
        </div>
    );
}
