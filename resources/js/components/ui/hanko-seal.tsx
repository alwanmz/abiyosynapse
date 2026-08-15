import * as React from 'react';

import { cn } from '@/lib/utils';

export interface HankoSealProps extends Omit<React.ComponentProps<'div'>, 'children'> {
    /** Approved and shown solid + rotated, or still pending and shown dashed/neutral. */
    status: 'approved' | 'pending';
    /** Size in pixels — defaults to the mockup's inline size (76px). */
    size?: number;
    /** Vertical label inside the seal. Defaults to 承認 (approved) / 未決 (pending). */
    label?: string;
}

/**
 * Hanko approval seal (Nexumi design system) — the one place the brand is
 * allowed to be decorative. Only render on records that are genuinely
 * approved/pending; never as a purely cosmetic flourish. Pair visually with
 * approver name + timestamp in text, since the seal alone isn't an audit
 * trail — see docs/design/nexumi-design-system.html.
 */
function HankoSeal({
    status,
    size = 76,
    label,
    className,
    ...props
}: HankoSealProps) {
    const isApproved = status === 'approved';
    const text = label ?? (isApproved ? '承認' : '未決');

    return (
        <div
            role="img"
            aria-label={isApproved ? 'Disetujui' : 'Menunggu persetujuan'}
            data-slot="hanko-seal"
            data-status={status}
            style={{ width: size, height: size }}
            className={cn(
                'grid flex-none place-items-center rounded-full border-2 font-ja font-bold',
                isApproved
                    ? 'border-nx-hanko-500 text-nx-hanko-500 -rotate-6 shadow-[inset_0_0_0_1px_rgba(193,18,31,0.2)] motion-safe:animate-in motion-safe:zoom-in-125 motion-safe:duration-300'
                    : 'border-dashed border-nx-n-300 text-nx-n-400',
                className,
            )}
            {...props}
        >
            <span
                className="text-center leading-none tracking-wide"
                style={{
                    writingMode: 'vertical-rl',
                    fontSize: Math.round(size * 0.25),
                }}
            >
                {text}
            </span>
        </div>
    );
}

export { HankoSeal };
