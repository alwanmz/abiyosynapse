import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { CYCLE_LABELS, ORDER_STATUS, type BillingCycle, formatDate, formatMoney } from '@/lib/billing';
import { Head, Link } from '@inertiajs/react';
import { IconCircleCheck, IconCircleX, IconClockHour4 } from '@tabler/icons-react';

interface OrderProps {
    order: {
        id: number;
        number: string;
        plan_name: string;
        billing_cycle: BillingCycle;
        amount: string;
        currency_code: string;
        status: 'paid' | 'pending' | 'failed' | 'expired';
        paid_at: string | null;
        created_at: string;
        company_name: string;
    };
}

export default function OrderStatus({ order }: OrderProps) {
    const paid = order.status === 'paid';
    const Icon = paid ? IconCircleCheck : order.status === 'pending' ? IconClockHour4 : IconCircleX;

    return (
        <div className="flex min-h-svh items-center justify-center bg-nx-navy-50 p-6 dark:bg-background">
            <Head title={`Order ${order.number}`} />

            <Card className="w-full max-w-md">
                <CardHeader className="text-center">
                    <div className={`mx-auto mb-3 flex size-14 items-center justify-center rounded-full ${paid ? 'bg-nx-andon-run-bg' : 'bg-nx-hanko-50'}`}>
                        <Icon className={`size-7 ${paid ? 'text-nx-andon-run' : 'text-nx-hanko-500'}`} />
                    </div>
                    <CardTitle className="font-display text-2xl">
                        {paid ? 'Pembayaran berhasil' : order.status === 'pending' ? 'Menunggu pembayaran' : 'Pembayaran tidak berhasil'}
                    </CardTitle>
                    <CardDescription>
                        {paid
                            ? `Paket ${order.plan_name} untuk ${order.company_name} sudah aktif.`
                            : 'Anda bisa memilih paket dan mencoba membayar lagi.'}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    <dl className="space-y-2 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">Nomor order</dt>
                            <dd className="font-mono text-xs">{order.number}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">Paket</dt>
                            <dd>
                                {order.plan_name} · {CYCLE_LABELS[order.billing_cycle]}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">Jumlah</dt>
                            <dd className="font-medium">{formatMoney(order.amount, order.currency_code)}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">Status</dt>
                            <dd>
                                <Badge variant={ORDER_STATUS[order.status].variant}>{ORDER_STATUS[order.status].label}</Badge>
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">{paid ? 'Dibayar' : 'Dibuat'}</dt>
                            <dd>{formatDate(order.paid_at ?? order.created_at, true)}</dd>
                        </div>
                    </dl>

                    {paid ? (
                        <Button className="w-full" asChild>
                            <Link href="/dashboard">Masuk ke dashboard</Link>
                        </Button>
                    ) : (
                        <div className="space-y-2">
                            {order.status === 'pending' && (
                                <Button className="w-full" asChild>
                                    <Link href={`/billing/checkout/${order.id}`}>Lanjutkan pembayaran</Link>
                                </Button>
                            )}
                            <Button variant="outline" className="w-full" asChild>
                                <Link href="/billing">Pilih paket lagi</Link>
                            </Button>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
