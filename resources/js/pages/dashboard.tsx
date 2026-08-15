import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Beranda',
        href: '/dashboard',
    },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dasbor" />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Selamat datang kembali!
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Dashboard ini akan diisi begitu modul-modul ERP mulai berjalan.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Belum ada data</CardTitle>
                        <CardDescription>
                            Ringkasan eksekutif akan tersedia setelah modul transaksional
                            (Kas Bank, AR, AP, Sales, Pembelian) mulai posting ke buku besar.
                        </CardDescription>
                    </CardHeader>
                    <CardContent />
                </Card>
            </div>
        </AppLayout>
    );
}
