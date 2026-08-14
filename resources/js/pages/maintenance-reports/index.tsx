import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import type {
    MaintenanceReportClient,
    MaintenanceReportListItem,
} from '@/types/maintenance-report';
import { Head, Link, router } from '@inertiajs/react';
import { FileDown, FileSpreadsheet, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Laporan Maintenance', href: '/maintenance-reports' },
];

interface PaginatedReports {
    data: MaintenanceReportListItem[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface Props {
    reports: PaginatedReports;
    clients: MaintenanceReportClient[];
    filters: { client_id?: string | null; status?: string | null };
    can: { manage: boolean };
}

const formatDate = (value: string) =>
    new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });

export default function MaintenanceReportsIndex({ reports, clients, filters, can }: Props) {
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const applyFilter = (overrides: Record<string, string | undefined>) => {
        const merged = {
            client_id: filters.client_id ?? undefined,
            status: filters.status ?? undefined,
            ...overrides,
        };
        const next: Record<string, string> = {};
        Object.entries(merged).forEach(([key, value]) => {
            if (value) next[key] = value;
        });

        router.get('/maintenance-reports', next, { preserveState: true, replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Laporan Maintenance" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Laporan Maintenance</h1>
                        <p className="text-muted-foreground">
                            Laporan pemeliharaan sistem berkala per klien.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Select
                            value={filters.client_id ?? 'all'}
                            onValueChange={(value) =>
                                applyFilter({ client_id: value === 'all' ? undefined : value })
                            }
                        >
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="Semua klien" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua klien</SelectItem>
                                {clients.map((client) => (
                                    <SelectItem key={client.id} value={String(client.id)}>
                                        {client.nama}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.status ?? 'all'}
                            onValueChange={(value) =>
                                applyFilter({ status: value === 'all' ? undefined : value })
                            }
                        >
                            <SelectTrigger className="w-36">
                                <SelectValue placeholder="Semua status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua status</SelectItem>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="published">Terbit</SelectItem>
                            </SelectContent>
                        </Select>
                        {can.manage && (
                            <Button asChild>
                                <Link href="/maintenance-reports/create">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Buat Laporan
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {reports.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-muted-foreground">
                            <FileSpreadsheet className="mb-3 h-12 w-12 opacity-20" />
                            <p className="font-medium">Belum ada laporan maintenance.</p>
                            <p className="mt-1 text-sm">
                                Buat laporan pertama untuk periode pemeliharaan berjalan.
                            </p>
                            {can.manage && (
                                <Button asChild className="mt-4">
                                    <Link href="/maintenance-reports/create">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Buat Laporan
                                    </Link>
                                </Button>
                            )}
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
                                            <TableHead>Klien</TableHead>
                                            <TableHead>Periode</TableHead>
                                            <TableHead className="text-center">Item</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {reports.data.map((report) => (
                                            <TableRow key={report.id}>
                                                <TableCell className="font-mono text-xs">
                                                    {report.report_number}
                                                </TableCell>
                                                <TableCell className="max-w-56">
                                                    <span className="line-clamp-2 text-sm font-medium">
                                                        {report.title}
                                                    </span>
                                                    {report.project && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {report.project.name}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-sm">
                                                    {report.client?.nama ?? '-'}
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap text-xs text-muted-foreground">
                                                    {formatDate(report.period_start)} —{' '}
                                                    {formatDate(report.period_end)}
                                                </TableCell>
                                                <TableCell className="text-center text-sm">
                                                    {report.items_count}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={
                                                            report.status === 'published'
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {report.status === 'published'
                                                            ? 'Terbit'
                                                            : 'Draft'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8"
                                                            title="Ekspor Word"
                                                            asChild
                                                        >
                                                            <a
                                                                href={`/maintenance-reports/${report.id}/export/docx`}
                                                            >
                                                                <FileDown className="h-4 w-4" />
                                                            </a>
                                                        </Button>
                                                        {can.manage && (
                                                            <>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-8 w-8"
                                                                    title="Ubah laporan"
                                                                    asChild
                                                                >
                                                                    <Link
                                                                        href={`/maintenance-reports/${report.id}/edit`}
                                                                    >
                                                                        <Pencil className="h-4 w-4" />
                                                                    </Link>
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-8 w-8"
                                                                    title="Hapus laporan"
                                                                    onClick={() =>
                                                                        setDeletingId(report.id)
                                                                    }
                                                                >
                                                                    <Trash2 className="h-4 w-4 text-destructive/70" />
                                                                </Button>
                                                            </>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {(reports.prev_page_url || reports.next_page_url) && (
                    <div className="flex justify-center gap-2">
                        {reports.prev_page_url && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={reports.prev_page_url}>← Sebelumnya</Link>
                            </Button>
                        )}
                        {reports.next_page_url && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={reports.next_page_url}>Berikutnya →</Link>
                            </Button>
                        )}
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={deletingId !== null}
                onOpenChange={(o) => !o && setDeletingId(null)}
                title="Hapus laporan maintenance?"
                description="Laporan beserta seluruh item pekerjaannya akan dihapus permanen."
                confirmLabel="Ya, Hapus"
                onConfirm={() => {
                    if (deletingId === null) return;
                    router.delete(`/maintenance-reports/${deletingId}`, {
                        preserveScroll: true,
                        onFinish: () => setDeletingId(null),
                    });
                }}
            />
        </AppLayout>
    );
}
