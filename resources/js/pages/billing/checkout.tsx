import { AndonBadge } from '@/components/ui/andon-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { CYCLE_LABELS, type BillingCycle, formatDate, formatMoney } from '@/lib/billing';
import { Head, Link, router } from '@inertiajs/react';
import { IconFlask, IconLock } from '@tabler/icons-react';
import { useState } from 'react';

interface CheckoutProps {
    order: {
        id: number;
        number: string;
        plan_name: string;
        billing_cycle: BillingCycle;
        amount: string;
        currency_code: string;
        expires_at: string | null;
        company_name: string;
    };
}

export default function Checkout({ order }: CheckoutProps) {
    const [pending, setPending] = useState<'paid' | 'failed' | null>(null);

    const simulate = (status: 'paid' | 'failed') => {
        setPending(status);
        router.post(`/billing/checkout/${order.id}/simulate`, { status }, { onFinish: () => setPending(null) });
    };

    return (
        <div className="flex min-h-svh items-center justify-center bg-nx-navy-50 p-6 dark:bg-background">
            <Head title="Pembayaran" />

            <Card className="w-full max-w-md">
                <CardHeader className="text-center">
                    <div className="mx-auto mb-2">
                        <AndonBadge variant="caution">
                            <IconFlask className="size-3.5" />
                            Mode sandbox — tidak ada uang yang ditarik
                        </AndonBadge>
                    </div>
                    <CardTitle className="font-display text-2xl">Selesaikan pembayaran</CardTitle>
                    <CardDescription>Order {order.number}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    <dl className="space-y-2 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Perusahaan</dt>
                            <dd className="text-right font-medium">{order.company_name}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Paket</dt>
                            <dd className="font-medium">
                                {order.plan_name} · {CYCLE_LABELS[order.billing_cycle]}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Batas bayar</dt>
                            <dd>{formatDate(order.expires_at, true)}</dd>
                        </div>
                        <Separator />
                        <div className="flex items-center justify-between gap-4">
                            <dt className="font-medium">Total</dt>
                            <dd className="font-display text-2xl font-semibold">{formatMoney(order.amount, order.currency_code)}</dd>
                        </div>
                    </dl>

                    <div className="space-y-2">
                        <Button variant="save" className="w-full" disabled={pending !== null} onClick={() => simulate('paid')}>
                            {pending === 'paid' && <Spinner />}
                            Simulasikan pembayaran berhasil
                        </Button>
                        <Button variant="outline" className="w-full" disabled={pending !== null} onClick={() => simulate('failed')}>
                            {pending === 'failed' && <Spinner />}
                            Simulasikan pembayaran gagal
                        </Button>
                        <Button variant="ghost" className="w-full" asChild>
                            <Link href="/billing">Batal, kembali ke pilihan paket</Link>
                        </Button>
                    </div>

                    <p className="flex items-center justify-center gap-1.5 text-xs text-muted-foreground">
                        <IconLock className="size-3.5" />
                        Nanti halaman ini diganti payment gateway sungguhan (mis. Midtrans).
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}
