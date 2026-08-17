import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { Head, useForm } from '@inertiajs/react';
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
}

interface GoodsReceiptLine {
    id: number;
    purchase_order_line_id: number;
    quantity_received: string;
    unit_cost: string;
    quantity_accepted: string | null;
    quantity_rejected: string | null;
    product: { id: number; code: string; name: string };
    inspections: Inspection[];
}

interface GoodsReceipt {
    id: number;
    number: string;
    received_date: string;
    status: 'pending_inspection' | 'put_away';
    purchase_order: { id: number; number: string; supplier: { id: number; name: string } };
    warehouse: { id: number; code: string; name: string };
    lines: GoodsReceiptLine[];
}

interface PageProps {
    receipt: GoodsReceipt;
}

interface PutAwayLineForm {
    goods_receipt_line_id: number;
    quantity_accepted: string;
    notes: string;
}

const STATUS_VARIANT: Record<GoodsReceipt['status'], 'default' | 'outline' | 'secondary'> = {
    pending_inspection: 'secondary',
    put_away: 'default',
};

function GoodsReceiptShowPage({ receipt }: PageProps) {
    const { t } = useTranslation('purchasing');

    useBreadcrumbs([
        { title: t('nav.purchasing'), href: '#' },
        { title: t('goods_receipt.breadcrumb'), href: '/purchasing/goods-receipts' },
        { title: receipt.number, href: '#' },
    ]);

    const putAwayForm = useForm<{ lines: PutAwayLineForm[] }>({
        lines: receipt.lines.map((line) => ({
            goods_receipt_line_id: line.id,
            quantity_accepted: line.quantity_received,
            notes: '',
        })),
    });

    const updateLine = (index: number, patch: Partial<PutAwayLineForm>) =>
        putAwayForm.setData(
            'lines',
            putAwayForm.data.lines.map((l, i) => (i === index ? { ...l, ...patch } : l)),
        );

    const lineById = (id: number) => receipt.lines.find((l) => l.id === id);

    const handlePutAway = (e: React.FormEvent) => {
        e.preventDefault();
        putAwayForm.post(`/purchasing/goods-receipts/${receipt.id}/put-away`, {
            preserveScroll: true,
        });
    };

    const isPendingInspection = receipt.status === 'pending_inspection';

    return (
        <>
            <Head title={t('goods_receipt.detail_title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">{receipt.number}</h1>
                            <Badge variant={STATUS_VARIANT[receipt.status]}>
                                {t(`goods_receipt.status_${receipt.status}`)}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {receipt.purchase_order.number} · {receipt.purchase_order.supplier.name} · {receipt.warehouse.code}
                        </p>
                    </div>
                </div>

                <Card className="mb-6 overflow-hidden p-0">
                    <ListHeader title={t('goods_receipt.lines')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('goods_receipt.table.product')}</TableHead>
                                    <TableHead className="text-right">{t('goods_receipt.table.quantity_received')}</TableHead>
                                    <TableHead className="text-right">{t('goods_receipt.table.unit_cost')}</TableHead>
                                    <TableHead className="text-right">{t('goods_receipt.table.quantity_accepted')}</TableHead>
                                    <TableHead className="text-right">{t('goods_receipt.table.quantity_rejected')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {receipt.lines.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell>
                                            <div className="font-medium">{line.product.name}</div>
                                            <div className="font-mono text-xs text-muted-foreground">{line.product.code}</div>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">{line.quantity_received}</TableCell>
                                        <TableCell className="text-right tabular-nums">{line.unit_cost}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {line.quantity_accepted ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {line.quantity_rejected ?? '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {isPendingInspection && (
                    <Card className="overflow-hidden p-0">
                        <ListHeader title={t('goods_receipt.quality_incoming_title')} />
                        <CardContent className="p-5">
                            <p className="mb-4 text-sm text-muted-foreground">
                                {t('goods_receipt.quality_incoming_description')}
                            </p>
                            <form onSubmit={handlePutAway} className="space-y-3">
                                {putAwayForm.data.lines.map((line, index) => {
                                    const meta = lineById(line.goods_receipt_line_id);
                                    return (
                                        <div
                                            key={line.goods_receipt_line_id}
                                            className="grid grid-cols-12 items-end gap-2 rounded-lg border p-3"
                                        >
                                            <div className="col-span-4">
                                                <div className="text-sm font-medium">{meta?.product.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">{meta?.product.code}</div>
                                            </div>
                                            <div className="col-span-3">
                                                <Label className="mb-1 block text-xs text-muted-foreground">
                                                    {t('goods_receipt.quantity_accepted')} ({t('goods_receipt.max_prefix')}{' '}
                                                    {meta?.quantity_received})
                                                </Label>
                                                <Input
                                                    type="number"
                                                    step="0.0001"
                                                    min={0}
                                                    max={meta?.quantity_received}
                                                    value={line.quantity_accepted}
                                                    onChange={(e) => updateLine(index, { quantity_accepted: e.target.value })}
                                                />
                                            </div>
                                            <div className="col-span-5">
                                                <Label className="mb-1 block text-xs text-muted-foreground">
                                                    {t('goods_receipt.inspection_notes')}
                                                </Label>
                                                <Input
                                                    placeholder={t('goods_receipt.inspection_notes_placeholder')}
                                                    value={line.notes}
                                                    onChange={(e) => updateLine(index, { notes: e.target.value })}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                                {putAwayForm.errors.lines && (
                                    <p className="text-sm text-destructive">{putAwayForm.errors.lines}</p>
                                )}
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={putAwayForm.processing}>
                                        {t('goods_receipt.put_away')}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

GoodsReceiptShowPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default GoodsReceiptShowPage;
