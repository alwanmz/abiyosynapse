import { PrintDocumentButton } from '@/components/print-document-button';
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
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconPlus } from '@tabler/icons-react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface CashTransaction {
    id: number;
    number: string;
    type: 'in' | 'out';
    transaction_date: string;
    amount: string;
    description: string;
    reference: string | null;
    bank_account: { id: number; code: string; name: string };
    counter_account: { id: number; code: string; name: string };
}

interface Option {
    id: number;
    code: string;
    name: string;
    type?: string;
}

interface PageProps {
    transactions: CashTransaction[];
    bankAccounts: Option[];
    counterAccounts: Option[];
}

function CashTransactionsPage({ transactions, bankAccounts, counterAccounts }: PageProps) {
    const { t } = useTranslation('cash-bank');
    const { locale } = usePage().props as { locale?: string };
    const [dialogOpen, setDialogOpen] = useState(false);

    useBreadcrumbs([
        { title: t('nav.cash_bank'), href: '#' },
        { title: t('cash_transaction.breadcrumb'), href: '/cash-bank/cash-transactions' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    const { data, setData, post, processing, errors, clearErrors, reset } = useForm({
        bank_account_id: '' as string | number,
        type: '' as string,
        transaction_date: new Date().toISOString().slice(0, 10),
        counter_account_id: '' as string | number,
        amount: '',
        description: '',
        reference: '',
    });

    const openCreate = () => {
        reset();
        setData('transaction_date', new Date().toISOString().slice(0, 10));
        setDialogOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/cash-bank/cash-transactions', {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                reset();
            },
        });
    };

    return (
        <>
            <Head title={t('cash_transaction.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('cash_transaction.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('cash_transaction.description')}</p>
                    </div>
                    <Button onClick={openCreate}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('cash_transaction.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('cash_transaction.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('cash_transaction.table.number')}</TableHead>
                                    <TableHead>{t('cash_transaction.table.date')}</TableHead>
                                    <TableHead>{t('cash_transaction.table.bank_account')}</TableHead>
                                    <TableHead>{t('cash_transaction.table.type')}</TableHead>
                                    <TableHead>{t('cash_transaction.table.counter_account')}</TableHead>
                                    <TableHead className="text-right">{t('cash_transaction.table.amount')}</TableHead>
                                    <TableHead>{t('cash_transaction.table.description')}</TableHead>
                                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transactions.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={8} className="h-24 text-center text-muted-foreground">
                                            {t('cash_transaction.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    transactions.map((transaction) => (
                                        <TableRow key={transaction.id}>
                                            <TableCell className="font-mono text-sm">{transaction.number}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {formatDate(transaction.transaction_date)}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {transaction.bank_account.name}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={transaction.type === 'in' ? 'default' : 'outline'}>
                                                    {t(`cash_transaction.type_${transaction.type}`)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {transaction.counter_account.name}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{transaction.amount}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {transaction.description}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end">
                                                    <PrintDocumentButton type="cash_transaction" documentId={transaction.id} compact />
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
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>{t('cash_transaction.add_title')}</DialogTitle>
                        <DialogDescription>{t('cash_transaction.description')}</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="bank_account_id">{t('cash_transaction.bank_account')}</Label>
                                <Select
                                    value={data.bank_account_id.toString()}
                                    onValueChange={(value) => {
                                        setData('bank_account_id', value);
                                        clearErrors('bank_account_id');
                                    }}
                                >
                                    <SelectTrigger id="bank_account_id">
                                        <SelectValue placeholder={t('cash_transaction.bank_account_placeholder')} />
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

                            <div className="grid gap-2">
                                <Label htmlFor="type">{t('cash_transaction.type')}</Label>
                                <Select
                                    value={data.type}
                                    onValueChange={(value) => {
                                        setData('type', value);
                                        clearErrors('type');
                                    }}
                                >
                                    <SelectTrigger id="type">
                                        <SelectValue placeholder={t('cash_transaction.type_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="in">{t('cash_transaction.type_in')}</SelectItem>
                                        <SelectItem value="out">{t('cash_transaction.type_out')}</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.type && <p className="text-sm text-destructive">{errors.type}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="transaction_date">{t('cash_transaction.transaction_date')}</Label>
                                <Input
                                    id="transaction_date"
                                    type="date"
                                    value={data.transaction_date}
                                    onChange={(e) => setData('transaction_date', e.target.value)}
                                />
                                {errors.transaction_date && (
                                    <p className="text-sm text-destructive">{errors.transaction_date}</p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="counter_account_id">{t('cash_transaction.counter_account')}</Label>
                                <Select
                                    value={data.counter_account_id.toString()}
                                    onValueChange={(value) => {
                                        setData('counter_account_id', value);
                                        clearErrors('counter_account_id');
                                    }}
                                >
                                    <SelectTrigger id="counter_account_id">
                                        <SelectValue placeholder={t('cash_transaction.counter_account_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {counterAccounts.map((account) => (
                                            <SelectItem key={account.id} value={account.id.toString()}>
                                                {account.code} — {account.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.counter_account_id && (
                                    <p className="text-sm text-destructive">{errors.counter_account_id}</p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="amount">{t('cash_transaction.amount')}</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    step="0.01"
                                    min={0.01}
                                    value={data.amount}
                                    onChange={(e) => setData('amount', e.target.value)}
                                />
                                {errors.amount && <p className="text-sm text-destructive">{errors.amount}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">{t('cash_transaction.description')}</Label>
                                <Input
                                    id="description"
                                    placeholder={t('cash_transaction.description_placeholder')}
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                />
                                {errors.description && (
                                    <p className="text-sm text-destructive">{errors.description}</p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="reference">{t('cash_transaction.reference')}</Label>
                                <Input
                                    id="reference"
                                    placeholder={t('cash_transaction.reference_placeholder')}
                                    value={data.reference}
                                    onChange={(e) => setData('reference', e.target.value)}
                                />
                                {errors.reference && <p className="text-sm text-destructive">{errors.reference}</p>}
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="cancel"
                                onClick={() => setDialogOpen(false)}
                                disabled={processing}
                            >
                                {t('common.cancel')}
                            </Button>
                            <Button type="submit" variant="save" disabled={processing}>
                                {processing ? t('common.saving') : t('common.save')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

CashTransactionsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default CashTransactionsPage;
