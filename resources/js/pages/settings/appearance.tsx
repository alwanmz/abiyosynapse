import { Head } from '@inertiajs/react';
import { type ReactElement } from 'react';

import AppearanceTabs from '@/components/appearance-tabs';
import HeadingSmall from '@/components/heading-small';

import AppLayout from '@/layouts/app-layout';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editAppearance } from '@/routes/appearance';

function Appearance() {
    useBreadcrumbs([
        {
            title: 'Pengaturan Tampilan',
            href: editAppearance().url,
        },
    ]);

    return (
        <>
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
        </>
    );
}

Appearance.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default Appearance;
