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

interface PayableInvoice {
    id: number;
    number: string;
    supplier_id: number;
    supplier: { id: number; code: string; name: string };
    total: string;
    outstanding_amount: number;
}

interface ApPaymentLine {
    id: number;
    amount_applied: string;
    supplier_invoice: { id: number; number: string };
}

interface ApPayment {
    id: number;
    number: string;
    payment_date: string;
    amount: string;
    reference: string | null;
    supplier: { id: number; code: string; name: string };
    bank_account: { id: number; code: string; name: string };
    lines: ApPaymentLine[];
}

interface Option {
    id: number;
    code: string;
    name: string;
}

interface PageProps {
    payments: ApPayment[];
    suppliers: Option[];
    bankAccounts: Option[];
    payableInvoices: PayableInvoice[];
}

interface LineForm {
    supplier_invoice_id: number;
    amount_applied: string;
}

function ApPaymentsPage({ payments, suppliers, bankAccounts, payableInvoices }: PageProps) {
    const { t } = useTranslation('ap');
    const { locale } = usePage().props as { locale?: string };
    const [dialogOpen, setDialogOpen] = useState(false);
    const [selectedSupplierId, setSelectedSupplierId] = useState<string>('');

    useBreadcrumbs([
        { title: t('nav.ap'), href: '#' },
        { title: t('ap_payment.breadcrumb'), href: '/ap/ap-payments' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    const { data, setData, post, processing, errors, clearErrors, reset, transform } = useForm<{
        supplier_id: string | number;
        bank_account_id: string | number;
        payment_date: string;
        reference: string;
        lines: LineForm[];
    }>({
        supplier_id: '',
        bank_account_id: '',
        payment_date: new Date().toISOString().slice(0, 10),
        reference: '',
        lines: [],
    });

    const supplierInvoices = useMemo(
        () => payableInvoices.filter((inv) => inv.supplier_id.toString() === selectedSupplierId),
        [payableInvoices, selectedSupplierId],
    );

    const openCreate = () => {
        reset();
        setSelectedSupplierId('');
        setData({
            supplier_id: '',
            bank_account_id: '',
            payment_date: new Date().toISOString().slice(0, 10),
            reference: '',
            lines: [],
        });
        setDialogOpen(true);
    };

    const selectSupplier = (supplierId: string) => {
        setSelectedSupplierId(supplierId);
        const invoices = payableInvoices.filter((inv) => inv.supplier_id.toString() === supplierId);
        setData('supplier_id', supplierId);
        setData('lines', invoices.map((inv) => ({ supplier_invoice_id: inv.id, amount_applied: '0' })));
        clearErrors('supplier_id');
    };

    const updateLine = (index: number, amount: string) =>
        setData('lines', data.lines.map((l, i) => (i === index ? { ...l, amount_applied: amount } : l)));

    const invoiceMeta = (supplierInvoiceId: number) =>
        supplierInvoices.find((inv) => inv.id === supplierInvoiceId);

    const totalApplied = data.lines.reduce((sum, line) => sum + (parseFloat(line.amount_applied) || 0), 0);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((formData) => ({
            ...formData,
            amount: totalApplied,
            lines: formData.lines.filter((line) => parseFloat(line.amount_applied) > 0),
        }));
        post('/ap/ap-payments', {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                reset();
            },
        });
    };

    return (
        <>
            <Head title={t('ap_payment.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('ap_payment.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('ap_payment.description')}</p>
                    </div>
                    <Button onClick={openCreate} disabled={payableInvoices.length === 0}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('ap_payment.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('ap_payment.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('ap_payment.table.number')}</TableHead>
                                    <TableHead>{t('ap_payment.table.supplier')}</TableHead>
                                    <TableHead>{t('ap_payment.table.bank_account')}</TableHead>
                                    <TableHead>{t('ap_payment.table.date')}</TableHead>
                                    <TableHead>{t('ap_payment.table.invoices')}</TableHead>
                                    <TableHead className="text-right">{t('ap_payment.table.amount')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payments.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('ap_payment.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    payments.map((payment) => (
                                        <TableRow key={payment.id}>
                                            <TableCell className="font-mono text-sm">{payment.number}</TableCell>
                                            <TableCell>
                                                <div className="font-medium">{payment.supplier.name}</div>
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {payment.supplier.code}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {payment.bank_account.name}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {formatDate(payment.payment_date)}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {payment.lines.map((line) => line.supplier_invoice.number).join(', ')}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{payment.amount}</TableCell>
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
                        <DialogTitle>{t('ap_payment.add_title')}</DialogTitle>
                        <DialogDescription>{t('ap_payment.add_description')}</DialogDescription>
                    </DialogHeader>

                    <form id="ap-payment-form" onSubmit={handleSubmit} className="flex-1 space-y-4 overflow-y-auto pr-2">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="supplier_id">{t('ap_payment.supplier')}</Label>
                                <Select value={selectedSupplierId} onValueChange={selectSupplier}>
                                    <SelectTrigger id="supplier_id">
                                        <SelectValue placeholder={t('ap_payment.supplier_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {suppliers
                                            .filter((s) =>
                                                payableInvoices.some((inv) => inv.supplier_id === s.id),
                                            )
                                            .map((s) => (
                                                <SelectItem key={s.id} value={s.id.toString()}>
                                                    {s.code} — {s.name}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                                {errors.supplier_id && <p className="text-sm text-destructive">{errors.supplier_id}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bank_account_id">{t('ap_payment.bank_account')}</Label>
                                <Select
                                    value={data.bank_account_id.toString()}
                                    onValueChange={(value) => {
                                        setData('bank_account_id', value);
                                        clearErrors('bank_account_id');
                                    }}
                                >
                                    <SelectTrigger id="bank_account_id">
                                        <SelectValue placeholder={t('ap_payment.bank_account_placeholder')} />
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
                                <Label htmlFor="payment_date">{t('ap_payment.payment_date')}</Label>
                                <Input
                                    id="payment_date"
                                    type="date"
                                    value={data.payment_date}
                                    onChange={(e) => setData('payment_date', e.target.value)}
                                />
                                {errors.payment_date && (
                                    <p className="text-sm text-destructive">{errors.payment_date}</p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="reference">{t('ap_payment.reference')}</Label>
                                <Input
                                    id="reference"
                                    placeholder={t('ap_payment.reference_placeholder')}
                                    value={data.reference}
                                    onChange={(e) => setData('reference', e.target.value)}
                                />
                                {errors.reference && <p className="text-sm text-destructive">{errors.reference}</p>}
                            </div>
                        </div>

                        {selectedSupplierId && (
                            <div className="space-y-3">
                                <Label className="text-base">{t('ap_payment.invoices')}</Label>

                                {supplierInvoices.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">{t('ap_payment.no_payable_invoices')}</p>
                                ) : (
                                    data.lines.map((line, index) => {
                                        const meta = invoiceMeta(line.supplier_invoice_id);
                                        return (
                                            <div
                                                key={line.supplier_invoice_id}
                                                className="grid grid-cols-12 items-center gap-2 rounded-lg border p-3"
                                            >
                                                <div className="col-span-6">
                                                    <div className="font-mono text-sm font-medium">{meta?.number}</div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {t('ap_payment.outstanding')}: {meta?.outstanding_amount}
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
                                    {t('ap_payment.total_applied')}: {totalApplied.toLocaleString(locale ?? 'id-ID')}
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
                        <Button type="submit" form="ap-payment-form" variant="save" disabled={processing || totalApplied <= 0}>
                            {processing ? t('common.saving') : t('common.save')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ApPaymentsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default ApPaymentsPage;
