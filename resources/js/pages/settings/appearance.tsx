import { Head } from '@inertiajs/react';

import AppearanceTabs from '@/components/appearance-tabs';
import HeadingSmall from '@/components/heading-small';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editAppearance } from '@/routes/appearance';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pengaturan Tampilan',
        href: editAppearance().url,
    },
];

export default function Appearance() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengaturan Tampilan" />

            <h1 className="sr-only">Pengaturan Tampilan</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Pengaturan Tampilan"
                        description="Perbarui pengaturan tampilan akun Anda"
                    />
                    <AppearanceTabs />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
