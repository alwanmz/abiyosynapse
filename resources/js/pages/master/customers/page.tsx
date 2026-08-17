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
import { Head, useForm } from '@inertiajs/react';
import { IconPlus } from '@tabler/icons-react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pencil, Trash2 } from 'lucide-react';

interface Customer {
    id: number;
    code: string;
    name: string;
    tax_id: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    payment_term_days: number;
    is_active: boolean;
}

interface PageProps {
    customers: Customer[];
}

function CustomersPage({ customers }: PageProps) {
    const { t } = useTranslation('master');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Customer | null>(null);
    const [deleting, setDeleting] = useState<Customer | null>(null);

    useBreadcrumbs([
        { title: t('nav.master_data'), href: '#' },
        { title: t('customer.title'), href: '/master/customers' },
    ]);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: '',
        name: '',
        tax_id: '',
        email: '',
        phone: '',
        address: '',
        payment_term_days: 0,
        is_active: true,
    });

    const { delete: destroy, processing: deleting_ } = useForm({});

    const openCreate = () => {
        setEditing(null);
        reset();
        setDialogOpen(true);
    };

    const openEdit = (customer: Customer) => {
        setEditing(customer);
        setData({
            code: customer.code,
            name: customer.name,
            tax_id: customer.tax_id ?? '',
            email: customer.email ?? '',
            phone: customer.phone ?? '',
            address: customer.address ?? '',
            payment_term_days: customer.payment_term_days,
            is_active: customer.is_active,
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
            put(`/master/customers/${editing.id}`, options);
        } else {
            post('/master/customers', options);
        }
    };

    const handleDelete = () => {
        if (!deleting) return;
        destroy(`/master/customers/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onError: () => setDeleting(null),
        });
    };

    return (
        <>
            <Head title={t('customer.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('customer.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('customer.description')}</p>
                    </div>
                    <Button onClick={openCreate}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('customer.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('customer.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.code')}</TableHead>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead>{t('customer.email')}</TableHead>
                                    <TableHead>{t('customer.phone')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {customers.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('customer.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    customers.map((customer) => (
                                        <TableRow key={customer.id}>
                                            <TableCell className="font-mono text-sm">{customer.code}</TableCell>
                                            <TableCell>{customer.name}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{customer.email ?? '—'}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{customer.phone ?? '—'}</TableCell>
                                            <TableCell>
                                                <Badge variant={customer.is_active ? 'default' : 'outline'}>
                                                    {customer.is_active ? t('common.active') : t('common.inactive')}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="icon" onClick={() => openEdit(customer)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => setDeleting(customer)}
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
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>{editing ? t('customer.edit_title') : t('customer.add_title')}</DialogTitle>
                        <DialogDescription>{t('customer.description')}</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="code">{t('common.code')}</Label>
                                    <Input
                                        id="code"
                                        placeholder={t('customer.code_placeholder')}
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value)}
                                    />
                                    {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="name">{t('common.name')}</Label>
                                    <Input
                                        id="name"
                                        placeholder={t('customer.name_placeholder')}
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                    />
                                    {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="tax_id">{t('customer.tax_id')}</Label>
                                    <Input
                                        id="tax_id"
                                        value={data.tax_id}
                                        onChange={(e) => setData('tax_id', e.target.value)}
                                    />
                                    {errors.tax_id && <p className="text-sm text-destructive">{errors.tax_id}</p>}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">{t('customer.email')}</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                    />
                                    {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">{t('customer.phone')}</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                    />
                                    {errors.phone && <p className="text-sm text-destructive">{errors.phone}</p>}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="payment_term_days">{t('customer.payment_term')}</Label>
                                    <Input
                                        id="payment_term_days"
                                        type="number"
                                        min={0}
                                        max={365}
                                        value={data.payment_term_days}
                                        onChange={(e) => setData('payment_term_days', parseInt(e.target.value) || 0)}
                                    />
                                    {errors.payment_term_days && (
                                        <p className="text-sm text-destructive">{errors.payment_term_days}</p>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address">{t('customer.address')}</Label>
                                <Textarea
                                    id="address"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                />
                                {errors.address && <p className="text-sm text-destructive">{errors.address}</p>}
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
                title={t('customer.delete_title')}
                description={
                    <>
                        {t('customer.delete_confirm_prefix')}{' '}
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

CustomersPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default CustomersPage;
