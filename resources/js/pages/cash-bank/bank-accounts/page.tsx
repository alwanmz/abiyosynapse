import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Head, useForm } from '@inertiajs/react';
import { IconPlus } from '@tabler/icons-react';
import { Pencil } from 'lucide-react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface BankAccount {
    id: number;
    code: string;
    name: string;
    type: 'cash' | 'bank';
    bank_name: string | null;
    account_number: string | null;
    opening_balance: string;
    currency_code: string;
    is_active: boolean;
    account: { id: number; code: string };
}

interface PageProps {
    bankAccounts: BankAccount[];
    currencies: { code: string; name: string }[];
}

function BankAccountsPage({ bankAccounts, currencies }: PageProps) {
    const { t } = useTranslation('cash-bank');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<BankAccount | null>(null);

    useBreadcrumbs([
        { title: t('nav.cash_bank'), href: '#' },
        { title: t('bank_account.breadcrumb'), href: '/cash-bank/bank-accounts' },
    ]);

    const { data, setData, post, put, processing, errors, clearErrors, reset } = useForm({
        code: '',
        name: '',
        type: '' as string,
        bank_name: '',
        account_number: '',
        opening_balance: '',
        currency_code: 'IDR',
        is_active: true,
    });

    const openCreate = () => {
        setEditing(null);
        reset();
        setDialogOpen(true);
    };

    const openEdit = (bankAccount: BankAccount) => {
        setEditing(bankAccount);
        setData({
            code: bankAccount.code,
            name: bankAccount.name,
            type: bankAccount.type,
            bank_name: bankAccount.bank_name ?? '',
            account_number: bankAccount.account_number ?? '',
            opening_balance: bankAccount.opening_balance,
            currency_code: bankAccount.currency_code,
            is_active: bankAccount.is_active,
        });
        setDialogOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                reset();
            },
        };

        if (editing) {
            put(`/cash-bank/bank-accounts/${editing.id}`, options);
        } else {
            post('/cash-bank/bank-accounts', options);
        }
    };

    return (
        <>
            <Head title={t('bank_account.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('bank_account.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('bank_account.description')}</p>
                    </div>
                    <Button onClick={openCreate}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('bank_account.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('bank_account.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('bank_account.table.code')}</TableHead>
                                    <TableHead>{t('bank_account.table.name')}</TableHead>
                                    <TableHead>{t('bank_account.table.type')}</TableHead>
                                    <TableHead>{t('bank_account.table.bank_name')}</TableHead>
                                    <TableHead>{t('bank_account.table.account_number')}</TableHead>
                                    <TableHead className="text-right">{t('bank_account.table.opening_balance')}</TableHead>
                                    <TableHead>{t('bank_account.table.currency')}</TableHead>
                                    <TableHead>{t('bank_account.table.gl_account')}</TableHead>
                                    <TableHead>{t('bank_account.table.status')}</TableHead>
                                    <TableHead className="text-right">{t('bank_account.table.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {bankAccounts.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={10} className="h-24 text-center text-muted-foreground">
                                            {t('bank_account.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    bankAccounts.map((bankAccount) => (
                                        <TableRow key={bankAccount.id}>
                                            <TableCell className="font-mono text-sm">{bankAccount.code}</TableCell>
                                            <TableCell>{bankAccount.name}</TableCell>
                                            <TableCell>
                                                <Badge variant={bankAccount.type === 'cash' ? 'secondary' : 'outline'}>
                                                    {t(`bank_account.type_${bankAccount.type}`)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {bankAccount.bank_name ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {bankAccount.account_number ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {bankAccount.opening_balance}
                                            </TableCell>
                                            <TableCell className="font-mono text-sm">{bankAccount.currency_code}</TableCell>
                                            <TableCell className="font-mono text-sm text-muted-foreground">
                                                {bankAccount.account.code}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={bankAccount.is_active ? 'default' : 'outline'}>
                                                    {bankAccount.is_active ? t('common.active') : t('common.inactive')}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button variant="ghost" size="icon" onClick={() => openEdit(bankAccount)}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
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
                        <DialogTitle>{editing ? t('bank_account.edit_title') : t('bank_account.add_title')}</DialogTitle>
                        <DialogDescription>{t('bank_account.description')}</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="code">{t('common.code')}</Label>
                                <Input
                                    id="code"
                                    placeholder={t('bank_account.code_placeholder')}
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value)}
                                    disabled={!!editing}
                                />
                                {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('common.name')}</Label>
                                <Input
                                    id="name"
                                    placeholder={t('bank_account.name_placeholder')}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="type">{t('bank_account.type')}</Label>
                                <Select
                                    value={data.type}
                                    onValueChange={(value) => {
                                        setData('type', value);
                                        clearErrors('type');
                                    }}
                                    disabled={!!editing}
                                >
                                    <SelectTrigger id="type">
                                        <SelectValue placeholder={t('bank_account.type_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="cash">{t('bank_account.type_cash')}</SelectItem>
                                        <SelectItem value="bank">{t('bank_account.type_bank')}</SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.type && <p className="text-sm text-destructive">{errors.type}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bank_name">{t('bank_account.bank_name')}</Label>
                                <Input
                                    id="bank_name"
                                    placeholder={t('bank_account.bank_name_placeholder')}
                                    value={data.bank_name}
                                    onChange={(e) => setData('bank_name', e.target.value)}
                                />
                                {errors.bank_name && <p className="text-sm text-destructive">{errors.bank_name}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="account_number">{t('bank_account.account_number')}</Label>
                                <Input
                                    id="account_number"
                                    placeholder={t('bank_account.account_number_placeholder')}
                                    value={data.account_number}
                                    onChange={(e) => setData('account_number', e.target.value)}
                                />
                                {errors.account_number && (
                                    <p className="text-sm text-destructive">{errors.account_number}</p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="opening_balance">{t('bank_account.opening_balance')}</Label>
                                <Input
                                    id="opening_balance"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={data.opening_balance}
                                    onChange={(e) => setData('opening_balance', e.target.value)}
                                    disabled={!!editing}
                                />
                                {errors.opening_balance && (
                                    <p className="text-sm text-destructive">{errors.opening_balance}</p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="currency_code">{t('bank_account.currency')}</Label>
                                <Select value={data.currency_code} onValueChange={(value) => setData('currency_code', value)} disabled={!!editing}>
                                    <SelectTrigger id="currency_code"><SelectValue /></SelectTrigger>
                                    <SelectContent>{currencies.map((currency) => <SelectItem key={currency.code} value={currency.code}>{currency.code} - {currency.name}</SelectItem>)}</SelectContent>
                                </Select>
                                {errors.currency_code && <p className="text-sm text-destructive">{errors.currency_code}</p>}
                            </div>

                            {editing && (
                                <label className="flex items-center gap-2 rounded-lg border p-3 text-sm">
                                    <Checkbox
                                        checked={data.is_active}
                                        onCheckedChange={(checked) => setData('is_active', checked === true)}
                                    />
                                    <span className="font-medium">{t('common.active')}</span>
                                </label>
                            )}
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

BankAccountsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default BankAccountsPage;
