import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import type {
    MaintenanceReport,
    MaintenanceReportClient,
    MaintenanceReportProject,
} from '@/types/maintenance-report';
import { Head, router } from '@inertiajs/react';
import { FileDown, Send, Undo2 } from 'lucide-react';
import { MaintenanceReportForm } from './components/maintenance-report-form';

interface Props {
    report: MaintenanceReport;
    clients: MaintenanceReportClient[];
    projects: MaintenanceReportProject[];
    statusResultOptions: string[];
}

export default function MaintenanceReportEdit({
    report,
    clients,
    projects,
    statusResultOptions,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Laporan Maintenance', href: '/maintenance-reports' },
        { title: report.report_number, href: `/maintenance-reports/${report.id}/edit` },
    ];

    const isPublished = report.status === 'published';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Laporan ${report.report_number}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex flex-col gap-1.5">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-bold tracking-tight">
                                {report.report_number}
                            </h1>
                            <Badge variant={isPublished ? 'default' : 'secondary'}>
                                {isPublished ? 'Terbit' : 'Draft'}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground">{report.title}</p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" asChild>
                            <a href={`/maintenance-reports/${report.id}/export/docx`}>
                                <FileDown className="mr-2 h-4 w-4" />
                                Ekspor Word
                            </a>
                        </Button>
                        {isPublished ? (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        `/maintenance-reports/${report.id}/unpublish`,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Undo2 className="mr-2 h-4 w-4" />
                                Kembalikan ke Draft
                            </Button>
                        ) : (
                            <Button
                                onClick={() =>
                                    router.post(
                                        `/maintenance-reports/${report.id}/publish`,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Send className="mr-2 h-4 w-4" />
                                Terbitkan
                            </Button>
                        )}
                    </div>
                </div>

                {isPublished && (
                    <p className="rounded-md border border-primary/20 bg-primary/5 px-3 py-2 text-sm text-muted-foreground">
                        Laporan ini sudah terbit dan dapat diunduh klien dari portal dalam format
                        Excel.
                    </p>
                )}

                <MaintenanceReportForm
                    clients={clients}
                    projects={projects}
                    statusResultOptions={statusResultOptions}
                    report={report}
                />
            </div>
        </AppLayout>
    );
}
