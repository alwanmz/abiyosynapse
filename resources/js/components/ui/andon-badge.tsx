import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

/**
 * Andon status vocabulary (Nexumi design system) — Running / Caution /
 * Stopped / Idle / Info, used for process/equipment/document state instead
 * of the generic success/warning/error/info naming that `Badge` uses for
 * CRUD/tag contexts. Only `stop` pulses, so the motion itself carries
 * meaning (readable even without color) — see docs/design/nexumi-design-system.html.
 */
const andonBadgeVariants = cva(
    'inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-tight whitespace-nowrap',
    {
        variants: {
            variant: {
                run: 'bg-nx-andon-run-bg text-nx-andon-run',
                caution: 'bg-nx-andon-caution-bg text-nx-andon-caution',
                stop: 'bg-nx-andon-stop-bg text-nx-andon-stop',
                idle: 'bg-nx-andon-idle-bg text-nx-andon-idle',
                info: 'bg-nx-andon-info-bg text-nx-andon-info',
            },
        },
        defaultVariants: {
            variant: 'idle',
        },
    },
);

export interface AndonBadgeProps
    extends React.ComponentProps<'span'>,
        VariantProps<typeof andonBadgeVariants> {}

function AndonBadge({ className, variant, children, ...props }: AndonBadgeProps) {
    return (
        <span
            data-slot="andon-badge"
            className={cn(andonBadgeVariants({ variant }), className)}
            {...props}
        >
            <i
                aria-hidden="true"
                className={cn(
                    'size-1.5 shrink-0 rounded-full bg-current',
                    variant === 'stop' && 'motion-safe:animate-pulse',
                )}
            />
            {children}
        </span>
    );
}

export { AndonBadge, andonBadgeVariants };
