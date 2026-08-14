import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import ClientPortalLayout from '@/layouts/client-portal-layout';
import { type BreadcrumbItem } from '@/types';
import type { ClientMaintenanceReport } from '@/types/maintenance-report';
import { Head } from '@inertiajs/react';
import { IconFileSpreadsheet, IconDownload } from '@tabler/icons-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Laporan Maintenance', href: '/portal/maintenance-reports' },
];

interface Props {
    reports: ClientMaintenanceReport[];
}

const formatDate = (value: string | null) =>
    value
        ? new Date(value).toLocaleDateString('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '-';

export default function ClientMaintenanceReportsIndex({ reports }: Props) {
    return (
        <ClientPortalLayout breadcrumbs={breadcrumbs}>
            <Head title="Laporan Maintenance" />

            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Laporan Maintenance</h1>
                    <p className="text-muted-foreground">
                        Riwayat laporan pemeliharaan sistem. Unduh dalam format Excel.
                    </p>
                </div>

                {reports.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-muted-foreground">
                            <IconFileSpreadsheet className="mb-3 h-12 w-12 opacity-20" />
                            <p className="font-medium">Belum ada laporan maintenance.</p>
                            <p className="mt-1 text-sm">
                                Laporan akan tampil di sini setelah diterbitkan oleh tim kami.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Nomor</TableHead>
                                            <TableHead>Judul</TableHead>
                                            <TableHead>Periode</TableHead>
                                            <TableHead className="text-center">Item</TableHead>
                                            <TableHead>Terbit</TableHead>
                                            <TableHead className="text-right">Unduh</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {reports.map((report) => (
                                            <TableRow key={report.id}>
                                                <TableCell className="font-mono text-xs">
                                                    {report.report_number}
                                                </TableCell>
                                                <TableCell className="max-w-64">
                                                    <span className="line-clamp-2 text-sm font-medium">
                                                        {report.title}
                                                    </span>
                                                    {report.project && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {report.project.name}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap text-xs text-muted-foreground">
                                                    {formatDate(report.period_start)} —{' '}
                                                    {formatDate(report.period_end)}
                                                </TableCell>
                                                <TableCell className="text-center text-sm">
                                                    {report.items_count}
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap text-xs text-muted-foreground">
                                                    {formatDate(report.published_at)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <a
                                                            href={`/portal/maintenance-reports/${report.id}/export`}
                                                        >
                                                            <IconDownload className="mr-1.5 h-4 w-4" />
                                                            Excel
                                                        </a>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </ClientPortalLayout>
    );
}
