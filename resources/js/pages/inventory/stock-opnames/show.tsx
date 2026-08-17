import { ConfirmDialog } from '@/components/confirm-dialog';
import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
import { Head, router, useForm } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface Line {
    id: number;
    system_quantity: string;
    counted_quantity: string | null;
    product: { id: number; code: string; name: string };
}

interface Opname {
    id: number;
    number: string;
    opname_date: string;
    status: 'draft' | 'completed';
    completed_at: string | null;
    warehouse: { id: number; code: string; name: string };
    lines: Line[];
}

interface PageProps {
    opname: Opname;
}

function StockOpnameShowPage({ opname }: PageProps) {
    const { t } = useTranslation('inventory');
    const isDraft = opname.status === 'draft';

    const [counts, setCounts] = useState<Record<number, string>>(
        Object.fromEntries(opname.lines.map((line) => [line.id, line.counted_quantity ?? ''])),
    );
    const [completeDialogOpen, setCompleteDialogOpen] = useState(false);

    useBreadcrumbs([
        { title: t('nav.inventory'), href: '#' },
        { title: t('stock_opname.breadcrumb'), href: '/inventory/stock-opnames' },
        { title: opname.number, href: '#' },
    ]);

    const { processing: savingCounts } = useForm({});
    const { processing: completing } = useForm({});

    const handleSaveCounts = () => {
        router.put(
            `/inventory/stock-opnames/${opname.id}/lines`,
            {
                lines: opname.lines.map((line) => ({
                    id: line.id,
                    counted_quantity: counts[line.id] === '' ? null : counts[line.id],
                })),
            },
            { preserveScroll: true },
        );
    };

    const handleComplete = () => {
        router.post(
            `/inventory/stock-opnames/${opname.id}/complete`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setCompleteDialogOpen(false),
            },
        );
    };

    const variance = (line: Line) => {
        const counted = counts[line.id];
        if (counted === '' || counted === undefined) return null;
        return parseFloat(counted) - parseFloat(line.system_quantity);
    };

    return (
        <>
            <Head title={t('stock_opname.detail_title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">{opname.number}</h1>
                            <Badge variant={opname.status === 'completed' ? 'default' : 'outline'}>
                                {t(`stock_opname.status_${opname.status}`)}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {opname.warehouse.code} — {opname.warehouse.name} · {opname.opname_date}
                            {opname.completed_at && ` · ${t('stock_opname.completed_at')}: ${opname.completed_at}`}
                        </p>
                    </div>

                    {isDraft && (
                        <div className="flex items-center gap-2">
                            <Button variant="outline" onClick={handleSaveCounts} disabled={savingCounts}>
                                {t('stock_opname.save_counts')}
                            </Button>
                            <Button onClick={() => setCompleteDialogOpen(true)} disabled={completing}>
                                {t('stock_opname.complete')}
                            </Button>
                        </div>
                    )}
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('stock_opname.detail_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('stock_opname.lines_table.product')}</TableHead>
                                    <TableHead className="text-right">{t('stock_opname.lines_table.system_quantity')}</TableHead>
                                    <TableHead className="text-right">{t('stock_opname.lines_table.counted_quantity')}</TableHead>
                                    <TableHead className="text-right">{t('stock_opname.lines_table.variance')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {opname.lines.map((line) => {
                                    const v = variance(line);
                                    return (
                                        <TableRow key={line.id}>
                                            <TableCell>
                                                <div className="font-medium">{line.product.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">{line.product.code}</div>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{line.system_quantity}</TableCell>
                                            <TableCell className="text-right">
                                                {isDraft ? (
                                                    <Input
                                                        type="number"
                                                        step="0.0001"
                                                        min={0}
                                                        className="ml-auto w-32 text-right tabular-nums"
                                                        value={counts[line.id] ?? ''}
                                                        onChange={(e) =>
                                                            setCounts((prev) => ({ ...prev, [line.id]: e.target.value }))
                                                        }
                                                    />
                                                ) : (
                                                    <span className="tabular-nums">{line.counted_quantity ?? '—'}</span>
                                                )}
                                            </TableCell>
                                            <TableCell
                                                className={`text-right tabular-nums font-medium ${
                                                    v !== null && v !== 0
                                                        ? v > 0
                                                            ? 'text-nx-andon-run'
                                                            : 'text-destructive'
                                                        : 'text-muted-foreground'
                                                }`}
                                            >
                                                {v !== null ? (v > 0 ? `+${v}` : v) : '—'}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <ConfirmDialog
                open={completeDialogOpen}
                onOpenChange={setCompleteDialogOpen}
                title={t('stock_opname.complete_confirm_title')}
                description={t('stock_opname.complete_confirm_description')}
                confirmLabel={t('stock_opname.complete')}
                variant="default"
                loading={completing}
                onConfirm={handleComplete}
            />
        </>
    );
}

StockOpnameShowPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default StockOpnameShowPage;
