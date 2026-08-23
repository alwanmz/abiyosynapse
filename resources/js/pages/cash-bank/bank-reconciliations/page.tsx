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

interface BankReconciliation {
    id: number;
    number: string;
    statement_date: string;
    statement_balance: string;
    status: 'draft' | 'completed';
    bank_account: { id: number; code: string; name: string };
}

interface Option {
    id: number;
    code: string;
    name: string;
}

interface PageProps {
    reconciliations: BankReconciliation[];
    bankAccounts: Option[];
}

function BankReconciliationsPage({ reconciliations, bankAccounts }: PageProps) {
    const { t } = useTranslation('cash-bank');
    const { locale } = usePage().props as { locale?: string };
    const [dialogOpen, setDialogOpen] = useState(false);

    useBreadcrumbs([
        { title: t('nav.cash_bank'), href: '#' },
        { title: t('bank_reconciliation.breadcrumb'), href: '/cash-bank/bank-reconciliations' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    const { data, setData, post, processing, errors, clearErrors, reset } = useForm({
        bank_account_id: '' as string | number,
        statement_date: new Date().toISOString().slice(0, 10),
        statement_balance: '',
    });

    const openCreate = () => {
        reset();
        setData('statement_date', new Date().toISOString().slice(0, 10));
        setDialogOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/cash-bank/bank-reconciliations', {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                reset();
            },
        });
    };

    return (
        <>
            <Head title={t('bank_reconciliation.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('bank_reconciliation.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('bank_reconciliation.description')}</p>
                    </div>
                    <Button onClick={openCreate}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('bank_reconciliation.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('bank_reconciliation.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('bank_reconciliation.table.number')}</TableHead>
                                    <TableHead>{t('bank_reconciliation.table.bank_account')}</TableHead>
                                    <TableHead>{t('bank_reconciliation.table.statement_date')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('bank_reconciliation.table.statement_balance')}
                                    </TableHead>
                                    <TableHead>{t('bank_reconciliation.table.status')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {reconciliations.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                                            {t('bank_reconciliation.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    reconciliations.map((reconciliation) => (
                                        <TableRow key={reconciliation.id}>
                                            <TableCell className="font-mono text-sm">
                                                <Link
                                                    href={`/cash-bank/bank-reconciliations/${reconciliation.id}`}
                                                    className="text-primary hover:underline"
                                                >
                                                    {reconciliation.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {reconciliation.bank_account.name}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {formatDate(reconciliation.statement_date)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {reconciliation.statement_balance}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={reconciliation.status === 'completed' ? 'default' : 'outline'}>
                                                    {t(`bank_reconciliation.status_${reconciliation.status}`)}
                                                </Badge>
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
                        <DialogTitle>{t('bank_reconciliation.add_title')}</DialogTitle>
                        <DialogDescription>{t('bank_reconciliation.description')}</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="bank_account_id">{t('bank_reconciliation.bank_account')}</Label>
                                <Select
                                    value={data.bank_account_id.toString()}
                                    onValueChange={(value) => {
                                        setData('bank_account_id', value);
                                        clearErrors('bank_account_id');
                                    }}
                                >
                                    <SelectTrigger id="bank_account_id">
                                        <SelectValue placeholder={t('bank_reconciliation.bank_account_placeholder')} />
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
                                <Label htmlFor="statement_date">{t('bank_reconciliation.statement_date')}</Label>
                                <Input
                                    id="statement_date"
                                    type="date"
                                    value={data.statement_date}
                                    onChange={(e) => setData('statement_date', e.target.value)}
                                />
                                {errors.statement_date && (
                                    <p className="text-sm text-destructive">{errors.statement_date}</p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="statement_balance">{t('bank_reconciliation.statement_balance')}</Label>
                                <Input
                                    id="statement_balance"
                                    type="number"
                                    step="0.01"
                                    value={data.statement_balance}
                                    onChange={(e) => setData('statement_balance', e.target.value)}
                                />
                                {errors.statement_balance && (
                                    <p className="text-sm text-destructive">{errors.statement_balance}</p>
                                )}
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

BankReconciliationsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default BankReconciliationsPage;
