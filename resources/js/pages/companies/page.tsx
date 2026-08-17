import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
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
import AppLayout from '@/layouts/app-layout';
import companiesRoutes from '@/routes/companies';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { DeleteCompanyDialog } from './components/delete-company-dialog';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Perusahaan',
        href: companiesRoutes.index().url,
    },
];

interface Company {
    id: number;
    name: string;
    legal_name: string | null;
    code: string;
    currency: string;
    is_active: boolean;
    pivot?: {
        role_id: number;
        is_default: boolean;
    };
}

interface CompaniesPageProps {
    companies: Company[];
}

export default function CompaniesPage({ companies }: CompaniesPageProps) {
    const [companyToDelete, setCompanyToDelete] = useState<Company | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Perusahaan" />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Perusahaan</h1>
                        <p className="text-sm text-muted-foreground">
                            Perusahaan yang Anda ikuti.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={companiesRoutes.create().url}>Tambah Perusahaan</Link>
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title="Daftar Perusahaan" />

                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Kode</TableHead>
                                    <TableHead>Mata Uang</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Default</TableHead>
                                    <TableHead className="text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {companies.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-center text-muted-foreground">
                                            Belum ada perusahaan.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    companies.map((company) => (
                                        <TableRow key={company.id}>
                                            <TableCell className="font-medium">
                                                {company.name}
                                            </TableCell>
                                            <TableCell className="font-mono text-sm text-muted-foreground">
                                                {company.code}
                                            </TableCell>
                                            <TableCell>{company.currency}</TableCell>
                                            <TableCell>
                                                <Badge variant={company.is_active ? 'default' : 'outline'}>
                                                    {company.is_active ? 'Aktif' : 'Nonaktif'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {company.pivot?.is_default && (
                                                    <Badge variant="outline">Default</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button variant="ghost" size="icon" asChild>
                                                    <Link href={companiesRoutes.edit(company.id).url}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => setCompanyToDelete(company)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <DeleteCompanyDialog
                    company={companyToDelete}
                    open={companyToDelete !== null}
                    onOpenChange={(open) => {
                        if (!open) setCompanyToDelete(null);
                    }}
                />
            </div>
        </AppLayout>
    );
}
