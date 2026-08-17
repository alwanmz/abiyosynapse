import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { IconPlus } from '@tabler/icons-react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface PurchaseRequestLine {
    id: number;
    quantity: string;
    converted_quantity: string;
    product: { id: number; code: string; name: string };
}

interface ApprovedRequest {
    id: number;
    number: string;
    lines: PurchaseRequestLine[];
}

interface PurchaseOrder {
    id: number;
    number: string;
    order_date: string;
    expected_date: string | null;
    status: 'draft' | 'approval' | 'approved' | 'sent' | 'partial' | 'received' | 'closed';
    total: string;
    supplier: { id: number; code: string; name: string };
    warehouse: { id: number; code: string; name: string };
}

interface Option {
    id: number;
    code: string;
    name: string;
}

interface TaxCodeOption extends Option {
    rate: string;
}

interface PageProps {
    orders: PurchaseOrder[];
    approvedRequests: ApprovedRequest[];
    suppliers: Option[];
    warehouses: Option[];
    taxCodes: TaxCodeOption[];
}

interface LineForm {
    purchase_request_line_id: number;
    quantity: string;
    unit_price: string;
    tax_code_id: string | number;
}

const STATUS_VARIANT: Record<PurchaseOrder['status'], 'default' | 'outline' | 'secondary'> = {
    draft: 'outline',
    approval: 'secondary',
    approved: 'secondary',
    sent: 'secondary',
    partial: 'secondary',
    received: 'default',
    closed: 'outline',
};

function PurchaseOrdersPage({ orders, approvedRequests, suppliers, warehouses, taxCodes }: PageProps) {
    const { t } = useTranslation('purchasing');
    const { locale } = usePage().props as { locale?: string };
    const [pickerOpen, setPickerOpen] = useState(false);
    const [selectedRequest, setSelectedRequest] = useState<ApprovedRequest | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);

    useBreadcrumbs([
        { title: t('nav.purchasing'), href: '#' },
        { title: t('purchase_order.breadcrumb'), href: '/purchasing/purchase-orders' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    const eligibleRequests = approvedRequests.filter((pr) =>
        pr.lines.some((line) => parseFloat(line.quantity) > parseFloat(line.converted_quantity)),
    );

    const { data, setData, post, processing, errors, clearErrors, reset } = useForm<{
        supplier_id: string | number;
        warehouse_id: string | number;
        lines: LineForm[];
    }>({
        supplier_id: '',
        warehouse_id: '',
        lines: [],
    });

    const openPicker = () => {
        setSelectedRequest(null);
        setPickerOpen(true);
    };

    const selectRequest = (pr: ApprovedRequest) => {
        const remainingLines = pr.lines.filter(
            (line) => parseFloat(line.quantity) > parseFloat(line.converted_quantity),
        );
        setSelectedRequest(pr);
        reset();
        setData({
            supplier_id: '',
            warehouse_id: '',
            lines: remainingLines.map((line) => ({
                purchase_request_line_id: line.id,
                quantity: (parseFloat(line.quantity) - parseFloat(line.converted_quantity)).toString(),
                unit_price: '',
                tax_code_id: '',
            })),
        });
        setPickerOpen(false);
        setDialogOpen(true);
    };

    const updateLine = (index: number, patch: Partial<LineForm>) =>
        setData('lines', data.lines.map((l, i) => (i === index ? { ...l, ...patch } : l)));

    const lineMeta = (purchaseRequestLineId: number) =>
        selectedRequest?.lines.find((l) => l.id === purchaseRequestLineId);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedRequest) return;
        post(`/purchasing/purchase-requests/${selectedRequest.id}/purchase-orders`, {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                setSelectedRequest(null);
                reset();
            },
        });
    };

    return (
        <>
            <Head title={t('purchase_order.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('purchase_order.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('purchase_order.description')}</p>
                    </div>
                    <Button onClick={openPicker} disabled={eligibleRequests.length === 0}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('purchase_order.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('purchase_order.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('purchase_order.table.number')}</TableHead>
                                    <TableHead>{t('purchase_order.table.supplier')}</TableHead>
                                    <TableHead>{t('purchase_order.table.warehouse')}</TableHead>
                                    <TableHead className="text-right">{t('purchase_order.table.total')}</TableHead>
                                    <TableHead>{t('purchase_order.table.status')}</TableHead>
                                    <TableHead>{t('purchase_order.table.order_date')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {orders.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('purchase_order.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    orders.map((po) => (
                                        <TableRow key={po.id}>
                                            <TableCell className="font-mono text-sm">
                                                <Link
                                                    href={`/purchasing/purchase-orders/${po.id}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {po.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <div className="font-medium">{po.supplier.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {po.supplier.code}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {po.warehouse.code}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{po.total}</TableCell>
                                            <TableCell>
                                                <Badge variant={STATUS_VARIANT[po.status]}>
                                                    {t(`purchase_order.status_${po.status}`)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{formatDate(po.order_date)}</TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={pickerOpen} onOpenChange={setPickerOpen}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>{t('purchase_order.pick_request_title')}</DialogTitle>
                        <DialogDescription>{t('purchase_order.pick_request_description')}</DialogDescription>
                    </DialogHeader>

                    <div className="max-h-[60vh] space-y-2 overflow-y-auto">
                        {eligibleRequests.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                {t('purchase_order.no_eligible_requests')}
                            </p>
                        ) : (
                            eligibleRequests.map((pr) => (
                                <button
                                    key={pr.id}
                                    type="button"
                                    onClick={() => selectRequest(pr)}
                                    className="flex w-full items-center justify-between rounded-lg border p-3 text-left hover:bg-accent"
                                >
                                    <span className="font-mono text-sm">{pr.number}</span>
                                    <span className="text-xs text-muted-foreground">
                                        {pr.lines.length} {t('purchase_order.lines_suffix')}
                                    </span>
                                </button>
                            ))
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setPickerOpen(false)}>
                            Batal
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="flex max-h-[90vh] max-w-3xl flex-col overflow-hidden">
                    <DialogHeader>
                        <DialogTitle>{t('purchase_order.add_title')}</DialogTitle>
                        <DialogDescription>{selectedRequest?.number}</DialogDescription>
                    </DialogHeader>

                    <form id="po-form" onSubmit={handleSubmit} className="flex-1 space-y-4 overflow-y-auto pr-2">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="supplier_id">{t('purchase_order.supplier')}</Label>
                                <Select
                                    value={data.supplier_id.toString()}
                                    onValueChange={(value) => {
                                        setData('supplier_id', value);
                                        clearErrors('supplier_id');
                                    }}
                                >
                                    <SelectTrigger id="supplier_id">
                                        <SelectValue placeholder={t('purchase_order.supplier_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {suppliers.map((s) => (
                                            <SelectItem key={s.id} value={s.id.toString()}>
                                                {s.code} — {s.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.supplier_id && <p className="text-sm text-destructive">{errors.supplier_id}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="warehouse_id">{t('purchase_order.warehouse')}</Label>
                                <Select
                                    value={data.warehouse_id.toString()}
                                    onValueChange={(value) => {
                                        setData('warehouse_id', value);
                                        clearErrors('warehouse_id');
                                    }}
                                >
                                    <SelectTrigger id="warehouse_id">
                                        <SelectValue placeholder={t('purchase_order.warehouse_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {warehouses.map((w) => (
                                            <SelectItem key={w.id} value={w.id.toString()}>
                                                {w.code} — {w.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.warehouse_id && <p className="text-sm text-destructive">{errors.warehouse_id}</p>}
                            </div>
                        </div>

                        <div className="space-y-3">
                            <Label className="text-base">{t('purchase_order.lines')}</Label>

                            {data.lines.map((line, index) => {
                                const meta = lineMeta(line.purchase_request_line_id);
                                const max = meta ? parseFloat(meta.quantity) - parseFloat(meta.converted_quantity) : undefined;
                                return (
                                    <div key={line.purchase_request_line_id} className="grid grid-cols-12 items-start gap-2 rounded-lg border p-3">
                                        <div className="col-span-4">
                                            <div className="text-sm font-medium">{meta?.product.name}</div>
                                            <div className="font-mono text-xs text-muted-foreground">{meta?.product.code}</div>
                                        </div>
                                        <div className="col-span-2">
                                            <Input
                                                type="number"
                                                step="0.0001"
                                                min={0.0001}
                                                max={max}
                                                placeholder={t('purchase_order.quantity')}
                                                value={line.quantity}
                                                onChange={(e) => updateLine(index, { quantity: e.target.value })}
                                            />
                                        </div>
                                        <div className="col-span-3">
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min={0}
                                                placeholder={t('purchase_order.unit_price')}
                                                value={line.unit_price}
                                                onChange={(e) => updateLine(index, { unit_price: e.target.value })}
                                            />
                                        </div>
                                        <div className="col-span-3">
                                            <Select
                                                value={line.tax_code_id.toString()}
                                                onValueChange={(value) => updateLine(index, { tax_code_id: value })}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder={t('purchase_order.tax_code_placeholder')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {taxCodes.map((tc) => (
                                                        <SelectItem key={tc.id} value={tc.id.toString()}>
                                                            {tc.code} ({tc.rate}%)
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                );
                            })}
                            {errors.lines && <p className="text-sm text-destructive">{errors.lines}</p>}
                        </div>
                    </form>

                    <DialogFooter className="mt-2 border-t pt-4">
                        <Button type="button" variant="outline" onClick={() => setDialogOpen(false)} disabled={processing}>
                            Batal
                        </Button>
                        <Button type="submit" form="po-form" disabled={processing}>
                            {t('purchase_order.add')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PurchaseOrdersPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default PurchaseOrdersPage;
