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
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pencil, Trash2 } from 'lucide-react';

interface Product {
    id: number;
    code: string;
    name: string;
    type: 'manufactured' | 'purchased' | 'service';
    product_category_id: number | null;
    category: { id: number; name: string } | null;
    base_uom_id: number;
    base_unit_of_measure: { id: number; code: string; name: string };
    make_or_buy: 'make' | 'buy';
    lot_tracked: boolean;
    serial_tracked: boolean;
    standard_cost: string;
    selling_price: string;
    default_warehouse_id: number | null;
    default_warehouse: { id: number; code: string; name: string } | null;
    status: 'draft' | 'pending_approval' | 'active' | 'inactive';
}

interface Option {
    id: number;
    name?: string;
    code?: string;
}

interface PageProps {
    products: Product[];
    categories: Option[];
    unitOfMeasures: Option[];
    warehouses: Option[];
}

const STATUS_VARIANT: Record<Product['status'], 'default' | 'outline' | 'secondary'> = {
    draft: 'outline',
    pending_approval: 'secondary',
    active: 'default',
    inactive: 'outline',
};

function ProductsPage({ products, categories, unitOfMeasures, warehouses }: PageProps) {
    const { t } = useTranslation('master');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Product | null>(null);
    const [deleting, setDeleting] = useState<Product | null>(null);

    useBreadcrumbs([
        { title: t('nav.master_data'), href: '#' },
        { title: t('product.title'), href: '/master/products' },
    ]);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        code: '',
        name: '',
        type: 'purchased' as Product['type'],
        product_category_id: '' as string | number,
        base_uom_id: '' as string | number,
        make_or_buy: 'buy' as Product['make_or_buy'],
        lot_tracked: false,
        serial_tracked: false,
        standard_cost: '0',
        selling_price: '0',
        default_warehouse_id: '' as string | number,
        status: 'draft' as Product['status'],
    });

    const { delete: destroy, processing: deleting_ } = useForm({});

    const openCreate = () => {
        setEditing(null);
        reset();
        setDialogOpen(true);
    };

    const openEdit = (product: Product) => {
        setEditing(product);
        setData({
            code: product.code,
            name: product.name,
            type: product.type,
            product_category_id: product.product_category_id ?? '',
            base_uom_id: product.base_uom_id,
            make_or_buy: product.make_or_buy,
            lot_tracked: product.lot_tracked,
            serial_tracked: product.serial_tracked,
            standard_cost: product.standard_cost,
            selling_price: product.selling_price,
            default_warehouse_id: product.default_warehouse_id ?? '',
            status: product.status,
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
            put(`/master/products/${editing.id}`, options);
        } else {
            post('/master/products', options);
        }
    };

    const handleDelete = () => {
        if (!deleting) return;
        destroy(`/master/products/${deleting.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleting(null),
            onError: () => setDeleting(null),
        });
    };

    return (
        <>
            <Head title={t('product.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('product.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('product.description')}</p>
                    </div>
                    <Button onClick={openCreate}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('product.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('product.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.code')}</TableHead>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead>{t('product.type')}</TableHead>
                                    <TableHead>{t('product.base_uom')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {products.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            {t('product.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    products.map((product) => (
                                        <TableRow key={product.id}>
                                            <TableCell className="font-mono text-sm">{product.code}</TableCell>
                                            <TableCell>{product.name}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {t(`product.type_${product.type}`)}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {product.base_unit_of_measure.code}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={STATUS_VARIANT[product.status]}>
                                                    {t(`product.status_${product.status}`)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="icon" onClick={() => openEdit(product)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-destructive hover:text-destructive"
                                                        onClick={() => setDeleting(product)}
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
                <DialogContent className="flex max-h-[90vh] max-w-2xl flex-col overflow-hidden">
                    <DialogHeader>
                        <DialogTitle>{editing ? t('product.edit_title') : t('product.add_title')}</DialogTitle>
                        <DialogDescription>{t('product.description')}</DialogDescription>
                    </DialogHeader>

                    <form
                        id="product-form"
                        onSubmit={handleSubmit}
                        className="flex-1 space-y-4 overflow-y-auto pr-2"
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="code">{t('common.code')}</Label>
                                <Input
                                    id="code"
                                    placeholder={t('product.code_placeholder')}
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value)}
                                />
                                {errors.code && <p className="text-sm text-destructive">{errors.code}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('common.name')}</Label>
                                <Input
                                    id="name"
                                    placeholder={t('product.name_placeholder')}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="type">{t('product.type')}</Label>
                                <Select
                                    value={data.type}
                                    onValueChange={(value) => {
                                        setData('type', value as Product['type']);
                                        if (value === 'manufactured') setData('make_or_buy', 'make');
                                    }}
                                >
                                    <SelectTrigger id="type">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="manufactured">{t('product.type_manufactured')}</SelectItem>
                                        <SelectItem value="purchased">{t('product.type_purchased')}</SelectItem>
                                        <SelectItem value="service">{t('product.type_service')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="category">{t('product.category')}</Label>
                                <Select
                                    value={data.product_category_id.toString()}
                                    onValueChange={(value) => setData('product_category_id', value)}
                                >
                                    <SelectTrigger id="category">
                                        <SelectValue placeholder={t('product.category_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((category) => (
                                            <SelectItem key={category.id} value={category.id.toString()}>
                                                {category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="base_uom_id">{t('product.base_uom')}</Label>
                                <Select
                                    value={data.base_uom_id.toString()}
                                    onValueChange={(value) => setData('base_uom_id', value)}
                                >
                                    <SelectTrigger id="base_uom_id">
                                        <SelectValue placeholder={t('product.base_uom_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {unitOfMeasures.map((uom) => (
                                            <SelectItem key={uom.id} value={uom.id.toString()}>
                                                {uom.code} — {uom.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.base_uom_id && <p className="text-sm text-destructive">{errors.base_uom_id}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="make_or_buy">{t('product.make_or_buy')}</Label>
                                <Select
                                    value={data.make_or_buy}
                                    onValueChange={(value) => setData('make_or_buy', value as Product['make_or_buy'])}
                                >
                                    <SelectTrigger id="make_or_buy">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="make">{t('product.make')}</SelectItem>
                                        <SelectItem value="buy">{t('product.buy')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="standard_cost">{t('product.standard_cost')}</Label>
                                <Input
                                    id="standard_cost"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={data.standard_cost}
                                    onChange={(e) => setData('standard_cost', e.target.value)}
                                />
                                {errors.standard_cost && <p className="text-sm text-destructive">{errors.standard_cost}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="selling_price">{t('product.selling_price')}</Label>
                                <Input
                                    id="selling_price"
                                    type="number"
                                    step="0.01"
                                    min={0}
                                    value={data.selling_price}
                                    onChange={(e) => setData('selling_price', e.target.value)}
                                />
                                {errors.selling_price && <p className="text-sm text-destructive">{errors.selling_price}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="default_warehouse_id">{t('product.default_warehouse')}</Label>
                                <Select
                                    value={data.default_warehouse_id.toString()}
                                    onValueChange={(value) => setData('default_warehouse_id', value)}
                                >
                                    <SelectTrigger id="default_warehouse_id">
                                        <SelectValue placeholder={t('product.default_warehouse_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {warehouses.map((warehouse) => (
                                            <SelectItem key={warehouse.id} value={warehouse.id.toString()}>
                                                {warehouse.code} — {warehouse.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="status">{t('product.status')}</Label>
                                <Select
                                    value={data.status}
                                    onValueChange={(value) => setData('status', value as Product['status'])}
                                >
                                    <SelectTrigger id="status">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="draft">{t('product.status_draft')}</SelectItem>
                                        <SelectItem value="pending_approval">{t('product.status_pending_approval')}</SelectItem>
                                        <SelectItem value="active">{t('product.status_active')}</SelectItem>
                                        <SelectItem value="inactive">{t('product.status_inactive')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="flex flex-col gap-3 rounded-lg border p-3">
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={data.lot_tracked}
                                    onCheckedChange={(checked) => setData('lot_tracked', checked === true)}
                                />
                                {t('product.lot_tracked')}
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={data.serial_tracked}
                                    onCheckedChange={(checked) => setData('serial_tracked', checked === true)}
                                />
                                {t('product.serial_tracked')}
                            </label>
                        </div>
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
                        <Button type="submit" form="product-form" variant="save" disabled={processing}>
                            {processing ? t('common.saving') : t('common.save')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={t('product.delete_title')}
                description={
                    <>
                        {t('product.delete_confirm_prefix')}{' '}
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

ProductsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default ProductsPage;
