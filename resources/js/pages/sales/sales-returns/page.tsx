import { ListHeader } from '@/components/list-header';
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
import { Textarea } from '@/components/ui/textarea';
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

interface SalesInvoiceLine {
    id: number;
    quantity: string;
    product: { id: number; code: string; name: string };
}

interface ReturnableInvoice {
    id: number;
    number: string;
    customer: { id: number; name: string };
    lines: SalesInvoiceLine[];
}

interface SalesReturn {
    id: number;
    number: string;
    return_date: string;
    total: string;
    sales_invoice: { id: number; number: string; customer: { id: number; name: string } };
    warehouse: { id: number; code: string; name: string };
}

interface Option {
    id: number;
    code: string;
    name: string;
}

interface PageProps {
    returns: SalesReturn[];
    returnableInvoices: ReturnableInvoice[];
    warehouses: Option[];
}

interface LineForm {
    sales_invoice_line_id: number;
    quantity: string;
}

function SalesReturnsPage({ returns, returnableInvoices, warehouses }: PageProps) {
    const { t } = useTranslation('sales');
    const { locale } = usePage().props as { locale?: string };
    const [pickerOpen, setPickerOpen] = useState(false);
    const [selectedInvoice, setSelectedInvoice] = useState<ReturnableInvoice | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);

    useBreadcrumbs([
        { title: t('nav.sales'), href: '#' },
        { title: t('sales_return.breadcrumb'), href: '/sales/sales-returns' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    const { data, setData, post, processing, errors, clearErrors, reset, transform } = useForm<{
        warehouse_id: string | number;
        return_date: string;
        reason: string;
        lines: LineForm[];
    }>({
        warehouse_id: '',
        return_date: new Date().toISOString().slice(0, 10),
        reason: '',
        lines: [],
    });

    const openPicker = () => {
        setSelectedInvoice(null);
        setPickerOpen(true);
    };

    const selectInvoice = (invoice: ReturnableInvoice) => {
        setSelectedInvoice(invoice);
        reset();
        setData({
            warehouse_id: '',
            return_date: new Date().toISOString().slice(0, 10),
            reason: '',
            lines: invoice.lines.map((line) => ({
                sales_invoice_line_id: line.id,
                quantity: '0',
            })),
        });
        setPickerOpen(false);
        setDialogOpen(true);
    };

    const updateLine = (index: number, patch: Partial<LineForm>) =>
        setData('lines', data.lines.map((l, i) => (i === index ? { ...l, ...patch } : l)));

    const lineMeta = (salesInvoiceLineId: number) =>
        selectedInvoice?.lines.find((l) => l.id === salesInvoiceLineId);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedInvoice) return;
        transform((formData) => ({
            ...formData,
            lines: formData.lines.filter((line) => parseFloat(line.quantity) > 0),
        }));
        post(`/sales/sales-invoices/${selectedInvoice.id}/sales-returns`, {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                setSelectedInvoice(null);
                reset();
            },
        });
    };

    return (
        <>
            <Head title={t('sales_return.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('sales_return.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('sales_return.description')}</p>
                    </div>
                    <Button onClick={openPicker} disabled={returnableInvoices.length === 0}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('sales_return.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('sales_return.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('sales_return.table.number')}</TableHead>
                                    <TableHead>{t('sales_return.table.sales_invoice')}</TableHead>
                                    <TableHead>{t('sales_return.table.customer')}</TableHead>
                                    <TableHead>{t('sales_return.table.warehouse')}</TableHead>
                                    <TableHead>{t('sales_return.table.return_date')}</TableHead>
                                    <TableHead className="text-right">{t('sales_return.total')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {returns.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('sales_return.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    returns.map((ret) => (
                                        <TableRow key={ret.id}>
                                            <TableCell className="font-mono text-sm">
                                                <Link
                                                    href={`/sales/sales-returns/${ret.id}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {ret.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="font-mono text-sm text-muted-foreground">
                                                {ret.sales_invoice.number}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {ret.sales_invoice.customer.name}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{ret.warehouse.code}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{formatDate(ret.return_date)}</TableCell>
                                            <TableCell className="text-right tabular-nums">{ret.total}</TableCell>
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
                        <DialogTitle>{t('sales_return.pick_invoice_title')}</DialogTitle>
                        <DialogDescription>{t('sales_return.pick_invoice_description')}</DialogDescription>
                    </DialogHeader>

                    <div className="max-h-[60vh] space-y-2 overflow-y-auto">
                        {returnableInvoices.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                {t('sales_return.no_eligible_invoices')}
                            </p>
                        ) : (
                            returnableInvoices.map((inv) => (
                                <button
                                    key={inv.id}
                                    type="button"
                                    onClick={() => selectInvoice(inv)}
                                    className="flex w-full items-center justify-between rounded-lg border p-3 text-left hover:bg-accent"
                                >
                                    <div>
                                        <span className="font-mono text-sm">{inv.number}</span>
                                        <span className="ml-2 text-xs text-muted-foreground">{inv.customer.name}</span>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {inv.lines.length} {t('sales_return.lines_suffix')}
                                    </span>
                                </button>
                            ))
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="cancel" onClick={() => setPickerOpen(false)}>
                            Batal
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="flex max-h-[90vh] max-w-2xl flex-col overflow-hidden">
                    <DialogHeader>
                        <DialogTitle>{t('sales_return.add_title')}</DialogTitle>
                        <DialogDescription>{selectedInvoice?.number}</DialogDescription>
                    </DialogHeader>

                    <form id="sr-form" onSubmit={handleSubmit} className="flex-1 space-y-4 overflow-y-auto pr-2">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="warehouse_id">{t('sales_return.warehouse')}</Label>
                                <Select
                                    value={data.warehouse_id.toString()}
                                    onValueChange={(value) => {
                                        setData('warehouse_id', value);
                                        clearErrors('warehouse_id');
                                    }}
                                >
                                    <SelectTrigger id="warehouse_id">
                                        <SelectValue placeholder={t('sales_return.warehouse_placeholder')} />
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

                            <div className="grid gap-2">
                                <Label htmlFor="return_date">{t('sales_return.return_date')}</Label>
                                <Input
                                    id="return_date"
                                    type="date"
                                    value={data.return_date}
                                    onChange={(e) => setData('return_date', e.target.value)}
                                />
                                {errors.return_date && <p className="text-sm text-destructive">{errors.return_date}</p>}
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="reason">{t('sales_return.reason')}</Label>
                            <Textarea
                                id="reason"
                                placeholder={t('sales_return.reason_placeholder')}
                                value={data.reason}
                                onChange={(e) => setData('reason', e.target.value)}
                            />
                        </div>

                        <div className="space-y-3">
                            <Label className="text-base">{t('sales_return.lines')}</Label>

                            {data.lines.map((line, index) => {
                                const meta = lineMeta(line.sales_invoice_line_id);
                                return (
                                    <div key={line.sales_invoice_line_id} className="grid grid-cols-12 items-center gap-2 rounded-lg border p-3">
                                        <div className="col-span-8">
                                            <div className="text-sm font-medium">{meta?.product.name}</div>
                                            <div className="font-mono text-xs text-muted-foreground">{meta?.product.code}</div>
                                        </div>
                                        <div className="col-span-4">
                                            <Label className="mb-1 block text-xs text-muted-foreground">
                                                {t('sales_return.quantity')} ({t('sales_order.max_prefix')} {meta?.quantity})
                                            </Label>
                                            <Input
                                                type="number"
                                                step="0.0001"
                                                min={0}
                                                max={meta?.quantity}
                                                value={line.quantity}
                                                onChange={(e) => updateLine(index, { quantity: e.target.value })}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                            {errors.lines && <p className="text-sm text-destructive">{errors.lines}</p>}
                        </div>
                    </form>

                    <DialogFooter className="mt-2 border-t pt-4">
                        <Button type="button" variant="cancel" onClick={() => setDialogOpen(false)} disabled={processing}>
                            Batal
                        </Button>
                        <Button type="submit" form="sr-form" disabled={processing}>
                            {t('sales_return.add')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

SalesReturnsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default SalesReturnsPage;
