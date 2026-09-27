import { PrintDocumentButton } from '@/components/print-document-button';
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
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconPlus } from '@tabler/icons-react';
import { type ReactElement, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface OutstandingInvoice {
    id: number;
    number: string;
    customer_id: number;
    customer: { id: number; code: string; name: string };
    total: string;
    outstanding_amount: number;
}

interface ArReceiptLine {
    id: number;
    amount_applied: string;
    sales_invoice: { id: number; number: string };
}

interface ArReceipt {
    id: number;
    number: string;
    receipt_date: string;
    amount: string;
    reference: string | null;
    customer: { id: number; code: string; name: string };
    bank_account: { id: number; code: string; name: string };
    lines: ArReceiptLine[];
}

interface Option {
    id: number;
    code: string;
    name: string;
}

interface PageProps {
    receipts: ArReceipt[];
    customers: Option[];
    bankAccounts: Option[];
    outstandingInvoices: OutstandingInvoice[];
}

interface LineForm {
    sales_invoice_id: number;
    amount_applied: string;
}

function ArReceiptsPage({ receipts, customers, bankAccounts, outstandingInvoices }: PageProps) {
    const { t } = useTranslation('ar');
    const { locale } = usePage().props as { locale?: string };
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedCustomerId, setSelectedCustomerId] = useState<string>('');

    useBreadcrumbs([
        { title: t('nav.ar'), href: '#' },
        { title: t('ar_receipt.breadcrumb'), href: '/ar/ar-receipts' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    const { data, setData, post, processing, errors, clearErrors, reset, transform } = useForm<{
        customer_id: string | number;
        bank_account_id: string | number;
        receipt_date: string;
        reference: string;
        lines: LineForm[];
    }>({
        customer_id: '',
        bank_account_id: '',
        receipt_date: new Date().toISOString().slice(0, 10),
        reference: '',
        lines: [],
    });

    const customerInvoices = useMemo(
        () => outstandingInvoices.filter((inv) => inv.customer_id.toString() === selectedCustomerId),
        [outstandingInvoices, selectedCustomerId],
    );

    const openCreate = () => {
        reset();
        setSelectedCustomerId('');
        setData({
            customer_id: '',
            bank_account_id: '',
            receipt_date: new Date().toISOString().slice(0, 10),
            reference: '',
            lines: [],
        });
        setDialogOpen(true);
    };

    const selectCustomer = (customerId: string) => {
        setSelectedCustomerId(customerId);
        const invoices = outstandingInvoices.filter((inv) => inv.customer_id.toString() === customerId);
        setData('customer_id', customerId);
        setData('lines', invoices.map((inv) => ({ sales_invoice_id: inv.id, amount_applied: '0' })));
        clearErrors('customer_id');
    };

    const updateLine = (index: number, amount: string) =>
        setData('lines', data.lines.map((l, i) => (i === index ? { ...l, amount_applied: amount } : l)));

    const invoiceMeta = (salesInvoiceId: number) =>
        customerInvoices.find((inv) => inv.id === salesInvoiceId);

    const totalApplied = data.lines.reduce((sum, line) => sum + (parseFloat(line.amount_applied) || 0), 0);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((formData) => ({
            ...formData,
            amount: totalApplied,
            lines: formData.lines.filter((line) => parseFloat(line.amount_applied) > 0),
        }));
        post('/ar/ar-receipts', {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                reset();
            },
        });
    };

    return (
        <>
            <Head title={t('ar_receipt.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('ar_receipt.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('ar_receipt.description')}</p>
                    </div>
                    <Button onClick={openCreate} disabled={outstandingInvoices.length === 0}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('ar_receipt.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('ar_receipt.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('ar_receipt.table.number')}</TableHead>
                                    <TableHead>{t('ar_receipt.table.customer')}</TableHead>
                                    <TableHead>{t('ar_receipt.table.bank_account')}</TableHead>
                                    <TableHead>{t('ar_receipt.table.date')}</TableHead>
                                    <TableHead>{t('ar_receipt.table.invoices')}</TableHead>
                                    <TableHead className="text-right">{t('ar_receipt.table.amount')}</TableHead>
                                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {receipts.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                            {t('ar_receipt.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    receipts.map((receipt) => (
                                        <TableRow key={receipt.id}>
                                            <TableCell className="font-mono text-sm">{receipt.number}</TableCell>
                                            <TableCell>
                                                <div className="font-medium">{receipt.customer.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {receipt.customer.code}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {receipt.bank_account.name}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {formatDate(receipt.receipt_date)}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {receipt.lines.map((line) => line.sales_invoice.number).join(', ')}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{receipt.amount}</TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end">
                                                    <PrintDocumentButton type="ar_receipt" documentId={receipt.id} compact />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="flex max-h-[90vh] max-w-2xl flex-col overflow-hidden">
                    <DialogHeader>
                        <DialogTitle>{t('ar_receipt.add_title')}</DialogTitle>
                        <DialogDescription>{t('ar_receipt.add_description')}</DialogDescription>
                    </DialogHeader>

                    <form id="ar-receipt-form" onSubmit={handleSubmit} className="flex-1 space-y-4 overflow-y-auto pr-2">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="customer_id">{t('ar_receipt.customer')}</Label>
                                <Select value={selectedCustomerId} onValueChange={selectCustomer}>
                                    <SelectTrigger id="customer_id">
                                        <SelectValue placeholder={t('ar_receipt.customer_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {customers
                                            .filter((c) =>
                                                outstandingInvoices.some((inv) => inv.customer_id === c.id),
                                            )
                                            .map((c) => (
                                                <SelectItem key={c.id} value={c.id.toString()}>
                                                    {c.code} — {c.name}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                                {errors.customer_id && <p className="text-sm text-destructive">{errors.customer_id}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bank_account_id">{t('ar_receipt.bank_account')}</Label>
                                <Select
                                    value={data.bank_account_id.toString()}
                                    onValueChange={(value) => {
                                        setData('bank_account_id', value);
                                        clearErrors('bank_account_id');
                                    }}
                                >
                                    <SelectTrigger id="bank_account_id">
                                        <SelectValue placeholder={t('ar_receipt.bank_account_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {bankAccounts.map((account) => (
                                            <SelectItem key={account.id} value={account.id.toString()}>
                                                {account.code} — {account.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.bank_account_id && (
                                    <p className="text-sm text-destructive">{errors.bank_account_id}</p>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="receipt_date">{t('ar_receipt.receipt_date')}</Label>
                                <Input
                                    id="receipt_date"
                                    type="date"
                                    value={data.receipt_date}
                                    onChange={(e) => setData('receipt_date', e.target.value)}
                                />
                                {errors.receipt_date && (
                                    <p className="text-sm text-destructive">{errors.receipt_date}</p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="reference">{t('ar_receipt.reference')}</Label>
                                <Input
                                    id="reference"
                                    placeholder={t('ar_receipt.reference_placeholder')}
                                    value={data.reference}
                                    onChange={(e) => setData('reference', e.target.value)}
                                />
                                {errors.reference && <p className="text-sm text-destructive">{errors.reference}</p>}
                            </div>
                        </div>

                        {selectedCustomerId && (
                            <div className="space-y-3">
                                <Label className="text-base">{t('ar_receipt.invoices')}</Label>

                                {customerInvoices.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">{t('ar_receipt.no_outstanding_invoices')}</p>
                                ) : (
                                    data.lines.map((line, index) => {
                                        const meta = invoiceMeta(line.sales_invoice_id);
                                        return (
                                            <div
                                                key={line.sales_invoice_id}
                                                className="grid grid-cols-12 items-center gap-2 rounded-lg border p-3"
                                            >
                                                <div className="col-span-6">
                                                    <div className="font-mono text-sm font-medium">{meta?.number}</div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {t('ar_receipt.outstanding')}: {meta?.outstanding_amount}
                                                    </div>
                                                </div>
                                                <div className="col-span-6">
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min={0}
                                                        max={meta?.outstanding_amount}
                                                        value={line.amount_applied}
                                                        onChange={(e) => updateLine(index, e.target.value)}
                                                    />
                                                </div>
                                            </div>
                                        );
                                    })
                                )}
                                {errors.lines && <p className="text-sm text-destructive">{errors.lines}</p>}

                                <div className="flex justify-end border-t pt-3 text-sm font-medium">
                                    {t('ar_receipt.total_applied')}: {totalApplied.toLocaleString(locale ?? 'id-ID')}
                                </div>
                            </div>
                        )}
                    </form>

                    <DialogFooter className="mt-2 border-t pt-4">
                        <Button
                            type="button"
                            variant="cancel"
                            onClick={() => setDialogOpen(false)}
                            disabled={processing}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" form="ar-receipt-form" variant="save" disabled={processing || totalApplied <= 0}>
                            {processing ? t('common.saving') : t('common.save')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ArReceiptsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default ArReceiptsPage;
