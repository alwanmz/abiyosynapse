import { ListHeader } from '@/components/list-header';
import { ConfirmDialog } from '@/components/confirm-dialog';
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
import { type ReactElement, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pencil, Trash2 } from 'lucide-react';

interface Account {
    id: number;
    code: string;
    name: string;
    type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense';
    normal_balance: 'debit' | 'credit';
    parent_id: number | null;
    parent: { id: number; code: string; name: string } | null;
    is_postable: boolean;
    is_active: boolean;
}

interface PageProps {
    accounts: Account[];
}

const TYPE_VARIANT: Record<Account['type'], 'default' | 'outline' | 'secondary'> = {
    asset: 'default',
    liability: 'secondary',
    equity: 'secondary',
    revenue: 'default',
    expense: 'outline',
};

function AccountsPage({ accounts }: PageProps) {
    const { t } = useTranslation('master');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Account | null>(null);
    const [deleting, setDeleting] = useState<Account | null>(null);

    useBreadcrumbs([
        { title: t('nav.master_data'), href: '#' },
        { title: t('account.title'), href: '/master/accounts' },
    ]);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: '',
        name: '',
        type: 'asset' as Account['type'],
        normal_balance: 'debit' as Account['normal_balance'],
        parent_id: '' as string | number,
        is_postable: true,
        is_active: true,
    });

    const { delete: destroy, processing: deleting_ } = useForm({});

    const depthByCode = useMemo(() => {
        const map = new Map<string, number>();
        accounts.forEach((account) => {
            map.set(account.code, (account.code.match(/\./g) ?? []).length);
        });
        return map;
    }, [accounts]);

    const openCreate = () => {
        setEditing(null);
        reset();
        setDialogOpen(true);
    };

    const openEdit = (account: Account) => {
        setEditing(account);
        setData({
            code: account.code,
            name: account.name,
            type: account.type,
            normal_balance: account.normal_balance,
            parent_id: account.parent_id ?? '',
            is_postable: account.is_postable,
            is_active: account.is_active,
        });
        setDialogOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            preserveState: false,
            onSuccess: () => {
                setDialogOpen(false);
                reset();
            },
        };

        if (editing) {
            put(`/master/accounts/${editing.id}`, options);
        } else {
            post('/master/accounts', options);
        }
    };

    const handleDelete = () => {
        if (!deleting) return;
        destroy(`/master/accounts/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onError: () => setDeleting(null),
        });
    };

    return (
        <>
            <Head title={t('account.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('account.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('account.description')}</p>
                    </div>
                    <Button onClick={openCreate}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('account.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('account.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.code')}</TableHead>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead>{t('account.type')}</TableHead>
                                    <TableHead>{t('account.normal_balance')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {accounts.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('account.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    accounts.map((account) => {
                                        const depth = depthByCode.get(account.code) ?? 0;
                                        return (
                                            <TableRow key={account.id}>
                                                <TableCell className="font-mono text-sm tabular-nums">{account.code}</TableCell>
                                                <TableCell style={{ paddingLeft: `${depth * 1.25 + 1}rem` }}>
                                                    {!account.is_postable ? (
                                                        <span className="font-semibold">{account.name}</span>
                                                    ) : (
                                                        account.name
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={TYPE_VARIANT[account.type]}>
                                                        {t(`account.type_${account.type}`)}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-sm text-muted-foreground">
                                                    {t(`account.normal_balance_${account.normal_balance}`)}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={account.is_active ? 'default' : 'outline'}>
                                                        {account.is_active ? t('common.active') : t('common.inactive')}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button variant="ghost" size="icon" onClick={() => openEdit(account)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-destructive hover:text-destructive"
                                                            onClick={() => setDeleting(account)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>{editing ? t('account.edit_title') : t('account.add_title')}</DialogTitle>
                        <DialogDescription>{t('account.description')}</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="code">{t('common.code')}</Label>
                                    <Input
                                        id="code"
                                        placeholder={t('account.code_placeholder')}
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value)}
                                    />
                                    {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="name">{t('common.name')}</Label>
                                    <Input
                                        id="name"
                                        placeholder={t('account.name_placeholder')}
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                    />
                                    {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="type">{t('account.type')}</Label>
                                    <Select
                                        value={data.type}
                                        onValueChange={(value) => setData('type', value as Account['type'])}
                                    >
                                        <SelectTrigger id="type">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="asset">{t('account.type_asset')}</SelectItem>
                                            <SelectItem value="liability">{t('account.type_liability')}</SelectItem>
                                            <SelectItem value="equity">{t('account.type_equity')}</SelectItem>
                                            <SelectItem value="revenue">{t('account.type_revenue')}</SelectItem>
                                            <SelectItem value="expense">{t('account.type_expense')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="normal_balance">{t('account.normal_balance')}</Label>
                                    <Select
                                        value={data.normal_balance}
                                        onValueChange={(value) => setData('normal_balance', value as Account['normal_balance'])}
                                    >
                                        <SelectTrigger id="normal_balance">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="debit">{t('account.normal_balance_debit')}</SelectItem>
                                            <SelectItem value="credit">{t('account.normal_balance_credit')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="parent_id">{t('account.parent')}</Label>
                                <Select
                                    value={data.parent_id.toString()}
                                    onValueChange={(value) => setData('parent_id', value)}
                                >
                                    <SelectTrigger id="parent_id">
                                        <SelectValue placeholder={t('account.parent_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-72">
                                        {accounts
                                            .filter((a) => a.id !== editing?.id)
                                            .map((account) => (
                                                <SelectItem key={account.id} value={account.id.toString()}>
                                                    {account.code} — {account.name}
                                                </SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                                {errors.parent_id && <p className="text-sm text-destructive">{errors.parent_id}</p>}
                            </div>

                            <label className="flex items-start gap-2 rounded-lg border p-3 text-sm">
                                <Checkbox
                                    checked={data.is_postable}
                                    onCheckedChange={(checked) => setData('is_postable', checked === true)}
                                />
                                <span>
                                    <span className="block font-medium">{t('account.is_postable')}</span>
                                    <span className="text-xs text-muted-foreground">{t('account.is_postable_hint')}</span>
                                </span>
                            </label>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDialogOpen(false)}
                                disabled={processing}
                            >
                                {t('common.cancel')}
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? t('common.saving') : t('common.save')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={t('account.delete_title')}
                description={
                    <>
                        {t('account.delete_confirm_prefix')}{' '}
                        <strong className="text-foreground">"{deleting?.name}"</strong>?
                    </>
                }
                confirmLabel={t('common.delete')}
                loading={deleting_}
                onConfirm={handleDelete}
            />
        </>
    );
}

AccountsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default AccountsPage;
