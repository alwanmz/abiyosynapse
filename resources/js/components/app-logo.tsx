import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    const { name } = usePage<SharedData>().props;

    return (
        <>
            <div className="flex aspect-square size-7 shrink-0 items-center justify-center">
                <AppLogoIcon className="size-full" />
            </div>
            <div className="ml-2 grid min-w-0 flex-1 text-left text-sm group-data-[collapsible=icon]:hidden" title={name}>
                <span className="truncate font-display leading-tight font-extrabold tracking-tight text-nx-navy-700 dark:text-foreground">
                    {name}
                </span>
            </div>
        </>
    );
}
