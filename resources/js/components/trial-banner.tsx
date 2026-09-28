import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { IconClockHour4 } from '@tabler/icons-react';

export function TrialBanner() {
    const { trial } = usePage<SharedData>().props;

    if (!trial) return null;

    const urgent = trial.days_left <= 2;
    const label = trial.days_left <= 0 ? 'Masa trial berakhir hari ini' : `Masa trial berakhir dalam ${trial.days_left} hari`;

    return (
        <div
            className={
                urgent
                    ? 'flex flex-wrap items-center justify-center gap-x-3 gap-y-1 border-b bg-nx-andon-stop-bg px-4 py-2 text-sm text-nx-andon-stop'
                    : 'flex flex-wrap items-center justify-center gap-x-3 gap-y-1 border-b bg-nx-andon-info-bg px-4 py-2 text-sm text-nx-andon-info'
            }
        >
            <span className="flex items-center gap-1.5 font-medium">
                <IconClockHour4 className="size-4" />
                {label}
            </span>
            <Link href="/billing" className="font-semibold underline underline-offset-4">
                Pilih paket sekarang
            </Link>
        </div>
    );
}
