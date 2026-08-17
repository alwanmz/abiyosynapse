import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
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
import { Head } from '@inertiajs/react';
import { type ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

interface Inspection {
    id: number;
    type: 'incoming' | 'in_process' | 'final';
    quantity_inspected: string;
    quantity_passed: string;
    quantity_failed: string;
    result: 'pending' | 'pass' | 'fail';
    inspected_at: string | null;
    product: { id: number; code: string; name: string };
    inspector: { id: number; name: string } | null;
}

interface PageProps {
    inspections: Inspection[];
}

function QualityInspectionsPage({ inspections }: PageProps) {
    const { t } = useTranslation('quality');

    useBreadcrumbs([
        { title: t('nav.quality'), href: '#' },
        { title: t('inspection.breadcrumb'), href: '/quality/inspections' },
    ]);

    return (
        <>
            <Head title={t('inspection.title')} />

            <div className="p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight">{t('inspection.title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('inspection.description')}</p>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('inspection.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('inspection.table.type')}</TableHead>
                                    <TableHead>{t('inspection.table.product')}</TableHead>
                                    <TableHead className="text-right">{t('inspection.table.quantity_inspected')}</TableHead>
                                    <TableHead className="text-right">{t('inspection.table.quantity_passed')}</TableHead>
                                    <TableHead className="text-right">{t('inspection.table.quantity_failed')}</TableHead>
                                    <TableHead>{t('inspection.table.result')}</TableHead>
                                    <TableHead>{t('inspection.table.inspector')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {inspections.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                            {t('inspection.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    inspections.map((inspection) => (
                                        <TableRow key={inspection.id}>
                                            <TableCell>
                                                <Badge variant="outline">{t(`inspection.type_${inspection.type}`)}</Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="font-medium">{inspection.product.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {inspection.product.code}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {inspection.quantity_inspected}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums text-nx-andon-run">
                                                {inspection.quantity_passed}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums text-destructive">
                                                {inspection.quantity_failed}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={inspection.result === 'pass' ? 'default' : inspection.result === 'fail' ? 'outline' : 'secondary'}>
                                                    {t(`inspection.result_${inspection.result}`)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {inspection.inspector?.name ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

QualityInspectionsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default QualityInspectionsPage;
