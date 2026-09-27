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
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import companiesRoutes from '@/routes/companies';
import { Head, Link } from '@inertiajs/react';
import { CreditCard, Pencil, Trash2 } from 'lucide-react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DeleteCompanyDialog } from './components/delete-company-dialog';

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

function CompaniesPage({ companies }: CompaniesPageProps) {
    const { t } = useTranslation('companies');
    const [companyToDelete, setCompanyToDelete] = useState<Company | null>(null);

    useBreadcrumbs([
        {
            title: t('breadcrumb'),
            href: companiesRoutes.index().url,
        },
    ]);

    return (
        <>
            <Head title={t('head_title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{t('page_title')}</h1>
                        <p className="text-sm text-muted-foreground">
                            {t('page_description')}
                        </p>
                    </div>
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/company/subscription">
                                <CreditCard className="size-4" />
                                {t('subscription.manage')}
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={companiesRoutes.create().url}>{t('add_company')}</Link>
                        </Button>
                    </div>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('list_title')} />

                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('table.name')}</TableHead>
                                    <TableHead>{t('table.code')}</TableHead>
                                    <TableHead>{t('table.currency')}</TableHead>
                                    <TableHead>{t('table.status')}</TableHead>
                                    <TableHead>{t('table.default')}</TableHead>
                                    <TableHead className="text-right">{t('table.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {companies.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="text-center text-muted-foreground">
                                            {t('table.empty')}
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
                                                    {company.is_active ? t('table.active') : t('table.inactive')}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {company.pivot?.is_default && (
                                                    <Badge variant="outline">{t('table.default')}</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
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
                                                </div>
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
        </>
    );
}

CompaniesPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default CompaniesPage;
