import { ListHeader } from '@/components/list-header';
import { ConfirmDialog } from '@/components/confirm-dialog';
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
import { Head, useForm } from '@inertiajs/react';
import { IconPlus } from '@tabler/icons-react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pencil, Trash2 } from 'lucide-react';

interface TaxCode {
    id: number;
    code: string;
    name: string;
    rate: string;
    account_id: number | null;
    account: { id: number; code: string; name: string } | null;
    is_active: boolean;
}

interface AccountOption {
    id: number;
    code: string;
    name: string;
}

interface PageProps {
    taxCodes: TaxCode[];
    accounts: AccountOption[];
}

function TaxCodesPage({ taxCodes, accounts }: PageProps) {
    const { t } = useTranslation('master');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<TaxCode | null>(null);
    const [deleting, setDeleting] = useState<TaxCode | null>(null);

    useBreadcrumbs([
        { title: t('nav.master_data'), href: '#' },
        { title: t('tax_code.title'), href: '/master/tax-codes' },
    ]);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: '',
        name: '',
        rate: '',
        account_id: '' as string | number,
        is_active: true,
    });

    const { delete: destroy, processing: deleting_ } = useForm({});

    const openCreate = () => {
        setEditing(null);
        reset();
        setDialogOpen(true);
    };

    const openEdit = (taxCode: TaxCode) => {
        setEditing(taxCode);
        setData({
            code: taxCode.code,
            name: taxCode.name,
            rate: taxCode.rate,
            account_id: taxCode.account_id ?? '',
            is_active: taxCode.is_active,
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
            put(`/master/tax-codes/${editing.id}`, options);
        } else {
            post('/master/tax-codes', options);
        }
    };

    const handleDelete = () => {
        if (!deleting) return;
        destroy(`/master/tax-codes/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onError: () => setDeleting(null),
        });
    };

    return (
        <>
            <Head title={t('tax_code.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('tax_code.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('tax_code.description')}</p>
                    </div>
                    <Button onClick={openCreate}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('tax_code.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('tax_code.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.code')}</TableHead>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead>{t('tax_code.rate')}</TableHead>
                                    <TableHead>{t('tax_code.account')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {taxCodes.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('tax_code.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    taxCodes.map((taxCode) => (
                                        <TableRow key={taxCode.id}>
                                            <TableCell className="font-mono text-sm">{taxCode.code}</TableCell>
                                            <TableCell>{taxCode.name}</TableCell>
                                            <TableCell className="tabular-nums">{taxCode.rate}%</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {taxCode.account ? `${taxCode.account.code} — ${taxCode.account.name}` : '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={taxCode.is_active ? 'default' : 'outline'}>
                                                    {taxCode.is_active ? t('common.active') : t('common.inactive')}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="icon" onClick={() => openEdit(taxCode)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => setDeleting(taxCode)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
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
                        <DialogTitle>{editing ? t('tax_code.edit_title') : t('tax_code.add_title')}</DialogTitle>
                        <DialogDescription>{t('tax_code.description')}</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="code">{t('common.code')}</Label>
                                <Input
                                    id="code"
                                    placeholder={t('tax_code.code_placeholder')}
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value)}
                                />
                                {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('common.name')}</Label>
                                <Input
                                    id="name"
                                    placeholder={t('tax_code.name_placeholder')}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="rate">{t('tax_code.rate')}</Label>
                                <Input
                                    id="rate"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    max={100}
                                    value={data.rate}
                                    onChange={(e) => setData('rate', e.target.value)}
                                />
                                {errors.rate && <p className="text-sm text-destructive">{errors.rate}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="account_id">{t('tax_code.account')}</Label>
                                <Select
                                    value={data.account_id.toString()}
                                    onValueChange={(value) => setData('account_id', value)}
                                >
                                    <SelectTrigger id="account_id">
                                        <SelectValue placeholder={t('tax_code.account_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent className="max-h-72">
                                        {accounts.map((account) => (
                                            <SelectItem key={account.id} value={account.id.toString()}>
                                                {account.code} — {account.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.account_id && <p className="text-sm text-destructive">{errors.account_id}</p>}
                            </div>
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
                title={t('tax_code.delete_title')}
                description={
                    <>
                        {t('tax_code.delete_confirm_prefix')}{' '}
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

TaxCodesPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default TaxCodesPage;
