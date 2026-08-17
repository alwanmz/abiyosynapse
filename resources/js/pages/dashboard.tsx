import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import { Head } from '@inertiajs/react';
import { type ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

function Dashboard() {
    const { t } = useTranslation('dashboard');

    useBreadcrumbs([
        {
            title: t('breadcrumb'),
            href: '/dashboard',
        },
    ]);

    return (
        <>
            <Head title={t('head_title')} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('welcome')}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {t('placeholder_description')}
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('empty_title')}</CardTitle>
                        <CardDescription>
                            {t('empty_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent />
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default Dashboard;
