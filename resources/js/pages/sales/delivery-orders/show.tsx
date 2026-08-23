import { ConfirmDialog } from '@/components/confirm-dialog';
import { ListHeader } from '@/components/list-header';
import { PrintDocumentButton } from '@/components/print-document-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { Head, router, useForm } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface DeliveryOrderLine {
    id: number;
    sales_order_line_id: number;
    quantity: string;
    unit_cost: string | null;
    product: { id: number; code: string; name: string };
}

interface DeliveryOrder {
    id: number;
    number: string;
    delivery_date: string;
    status: 'draft' | 'shipped';
    sales_order: { id: number; number: string; customer: { id: number; name: string } };
    warehouse: { id: number; code: string; name: string };
    lines: DeliveryOrderLine[];
}

interface PageProps {
    delivery: DeliveryOrder;
}

const STATUS_VARIANT: Record<DeliveryOrder['status'], 'default' | 'outline' | 'secondary'> = {
    draft: 'secondary',
    shipped: 'default',
};

function DeliveryOrderShowPage({ delivery }: PageProps) {
    const { t } = useTranslation('sales');
    const [shipDialogOpen, setShipDialogOpen] = useState(false);

    useBreadcrumbs([
        { title: t('nav.sales'), href: '#' },
        { title: t('delivery_order.breadcrumb'), href: '/sales/delivery-orders' },
        { title: delivery.number, href: '#' },
    ]);

    const { processing: shipping } = useForm({});

    const handleShip = () => {
        router.post(
            `/sales/delivery-orders/${delivery.id}/ship`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setShipDialogOpen(false),
            },
        );
    };

    const isDraft = delivery.status === 'draft';

    return (
        <>
            <Head title={t('delivery_order.detail_title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">{delivery.number}</h1>
                            <Badge variant={STATUS_VARIANT[delivery.status]}>
                                {t(`delivery_order.status_${delivery.status}`)}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {delivery.sales_order.number} · {delivery.sales_order.customer.name} · {delivery.warehouse.code}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <PrintDocumentButton type="delivery_order" documentId={delivery.id} />
                        {isDraft && (
                        <Button onClick={() => setShipDialogOpen(true)} disabled={shipping}>
                            {t('delivery_order.ship')}
                        </Button>
                        )}
                    </div>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('delivery_order.lines')} />
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('delivery_order.table.product')}</TableHead>
                                    <TableHead className="text-right">{t('delivery_order.table.quantity')}</TableHead>
                                    <TableHead className="text-right">{t('delivery_order.table.unit_cost')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {delivery.lines.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell>
                                            <div className="font-medium">{line.product.name}</div>
                                            <div className="font-mono text-xs text-muted-foreground">{line.product.code}</div>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">{line.quantity}</TableCell>
                                        <TableCell className="text-right tabular-nums">{line.unit_cost ?? '—'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <ConfirmDialog
                open={shipDialogOpen}
                onOpenChange={setShipDialogOpen}
                title={t('delivery_order.ship_confirm_title')}
                description={t('delivery_order.ship_confirm_description')}
                confirmLabel={t('delivery_order.ship')}
                variant="default"
                loading={shipping}
                onConfirm={handleShip}
            />
        </>
    );
}

DeliveryOrderShowPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default DeliveryOrderShowPage;
