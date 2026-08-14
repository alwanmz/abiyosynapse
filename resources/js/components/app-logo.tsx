import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { LayoutDashboard } from 'lucide-react';

export default function AppLogo() {
    const { company } = usePage<SharedData>().props;

    const namaPerusahaan = company?.nama_perusahaan?.trim();
    const title = namaPerusahaan ? `Teamboard - ${namaPerusahaan}` : 'Teamboard SKI';
    const logoUrl = company?.logo_path ? `/storage/${company.logo_path}` : null;
    const primary = namaPerusahaan ? 'Teamboard' : 'Teamboard SKI';

    return (
        <>
            <div
                className={
                    'flex aspect-square size-8 items-center justify-center overflow-hidden rounded-lg shadow-sm ' +
                    (logoUrl
                        ? 'bg-white dark:bg-white'
                        : 'bg-black text-white dark:bg-white dark:text-black')
                }
            >
                {logoUrl ? (
                    <img src={logoUrl} alt={title} className="h-full w-full object-contain" />
                ) : (
                    <LayoutDashboard size={18} strokeWidth={2.5} />
                )}
            </div>
            {/* Single-line rows: a wrapping company name used to overflow the
                fixed-height sidebar header and push the footer out of view. */}
            <div className="ml-2 grid min-w-0 flex-1 text-left text-sm group-data-[collapsible=icon]:hidden" title={title}>
                <span className="truncate leading-tight font-bold tracking-tight">
                    {primary}
                </span>
                {namaPerusahaan && (
                    <span className="truncate text-xs font-medium leading-snug text-muted-foreground">
                        {namaPerusahaan}
                    </span>
                )}
            </div>
        </>
    );
}
