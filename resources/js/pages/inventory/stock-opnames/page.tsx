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
import { Head, Link, useForm } from '@inertiajs/react';
import { IconPlus } from '@tabler/icons-react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface StockOpname {
    id: number;
    number: string;
    opname_date: string;
    status: 'draft' | 'completed';
    warehouse: { id: number; code: string; name: string };
}

interface WarehouseOption {
    id: number;
    code: string;
    name: string;
}

interface PageProps {
    opnames: StockOpname[];
    warehouses: WarehouseOption[];
}

function StockOpnamesPage({ opnames, warehouses }: PageProps) {
    const { t } = useTranslation('inventory');
    const [dialogOpen, setDialogOpen] = useState(false);

    useBreadcrumbs([
        { title: t('nav.inventory'), href: '#' },
        { title: t('stock_opname.breadcrumb'), href: '/inventory/stock-opnames' },
    ]);

    const { data, setData, post, processing, errors, reset } = useForm({
        warehouse_id: '' as string | number,
        opname_date: new Date().toISOString().slice(0, 10),
        notes: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/inventory/stock-opnames', {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                reset();
            },
        });
    };

    return (
        <>
            <Head title={t('stock_opname.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('stock_opname.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('stock_opname.description')}</p>
                    </div>
                    <Button onClick={() => setDialogOpen(true)}>
                        <IconPlus className="mr-2 h-4 w-4" />
                        {t('stock_opname.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('stock_opname.list_title')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('stock_opname.table.number')}</TableHead>
                                    <TableHead>{t('stock_opname.table.warehouse')}</TableHead>
                                    <TableHead>{t('stock_opname.table.date')}</TableHead>
                                    <TableHead>{t('stock_opname.table.status')}</TableHead>
                                    <TableHead className="text-right">{t('stock_opname.table.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {opnames.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                                            {t('stock_opname.empty')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    opnames.map((opname) => (
                                        <TableRow key={opname.id}>
                                            <TableCell className="font-mono text-sm">{opname.number}</TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {opname.warehouse.code} — {opname.warehouse.name}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{opname.opname_date}</TableCell>
                                            <TableCell>
                                                <Badge variant={opname.status === 'completed' ? 'default' : 'outline'}>
                                                    {t(`stock_opname.status_${opname.status}`)}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Link
                                                    href={`/inventory/stock-opnames/${opname.id}`}
                                                    className="text-sm text-primary hover:underline"
                                                >
                                                    {t('stock_opname.detail_title')}
                                                </Link>
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
                        <DialogTitle>{t('stock_opname.add_title')}</DialogTitle>
                        <DialogDescription>{t('stock_opname.description')}</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleSubmit}>
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="warehouse_id">{t('stock_opname.warehouse')}</Label>
                                <Select
                                    value={data.warehouse_id.toString()}
                                    onValueChange={(value) => setData('warehouse_id', value)}
                                >
                                    <SelectTrigger id="warehouse_id">
                                        <SelectValue placeholder={t('stock_opname.warehouse_placeholder')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {warehouses.map((warehouse) => (
                                            <SelectItem key={warehouse.id} value={warehouse.id.toString()}>
                                                {warehouse.code} — {warehouse.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.warehouse_id && <p className="text-sm text-destructive">{errors.warehouse_id}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="opname_date">{t('stock_opname.opname_date')}</Label>
                                <Input
                                    id="opname_date"
                                    type="date"
                                    value={data.opname_date}
                                    onChange={(e) => setData('opname_date', e.target.value)}
                                />
                                {errors.opname_date && <p className="text-sm text-destructive">{errors.opname_date}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">{t('stock_opname.notes')}</Label>
                                <Textarea
                                    id="notes"
                                    placeholder={t('stock_opname.notes_placeholder')}
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="cancel"
                                onClick={() => setDialogOpen(false)}
                                disabled={processing}
                            >
                                Batal
                            </Button>
                            <Button type="submit" variant="save" disabled={processing}>
                                {t('stock_opname.add')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

StockOpnamesPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default StockOpnamesPage;
