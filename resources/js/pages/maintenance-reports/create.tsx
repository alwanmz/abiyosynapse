import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import type {
    MaintenanceReportClient,
    MaintenanceReportProject,
} from '@/types/maintenance-report';
import { Head } from '@inertiajs/react';
import { MaintenanceReportForm } from './components/maintenance-report-form';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Laporan Maintenance', href: '/maintenance-reports' },
    { title: 'Buat Laporan', href: '/maintenance-reports/create' },
];

interface Props {
    clients: MaintenanceReportClient[];
    projects: MaintenanceReportProject[];
    statusResultOptions: string[];
}

export default function MaintenanceReportCreate({
    clients,
    projects,
    statusResultOptions,
}: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Buat Laporan Maintenance" />

            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Buat Laporan Maintenance</h1>
                    <p className="text-muted-foreground">
                        Isi rincian pekerjaan pemeliharaan baris demi baris.
                    </p>
                </div>

                <MaintenanceReportForm
                    clients={clients}
                    projects={projects}
                    statusResultOptions={statusResultOptions}
                />
            </div>
        </AppLayout>
    );
}
