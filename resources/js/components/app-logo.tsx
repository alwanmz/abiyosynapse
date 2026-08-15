import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { LayoutDashboard } from 'lucide-react';

export default function AppLogo() {
    const { name } = usePage<SharedData>().props;

    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg bg-black text-white shadow-sm dark:bg-white dark:text-black">
                <LayoutDashboard size={18} strokeWidth={2.5} />
            </div>
            <div className="ml-2 grid min-w-0 flex-1 text-left text-sm group-data-[collapsible=icon]:hidden" title={name}>
                <span className="truncate leading-tight font-bold tracking-tight">
                    {name}
                </span>
            </div>
        </>
    );
}
