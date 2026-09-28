import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { IconAlertTriangle, IconCheck, IconCreditCard } from '@tabler/icons-react';
import { type BillingCycle, CYCLE_LABELS, ORDER_STATUS, formatDate, formatMoney } from '@/lib/billing';
import { type ReactElement, useState } from 'react';


interface Plan {
    id: number;
    code: string;
    name: string;
    description: string | null;
    price: string;
    price_yearly: string | null;
    price_lifetime: string | null;
    currency_code: string;
    limits: Record<string, number | null> | null;
    features: Record<string, boolean> | null;
}

interface Order {
    id: number;
    number: string;
    billing_cycle: BillingCycle;
    amount: string;
    currency_code: string;
    status: string;
    paid_at: string | null;
    created_at: string;
    plan: { id: number; name: string } | null;
}

interface BillingProps {
    company: { name: string; trial_ends_at: string | null; purge_at: string | null; trial_expired: boolean };
    subscription: {
        status: string;
        billing_cycle: BillingCycle | null;
        plan_code: string | null;
        plan_name: string | null;
        current_period_end: string | null;
    };
    plans: Plan[];
    orders: Order[];
}

const PERIOD_SUFFIX: Record<BillingCycle, string> = { monthly: '/bulan', yearly: '/tahun', lifetime: ' sekali bayar' };

const LIMIT_LABELS: Record<string, string> = { users: 'pengguna', warehouses: 'gudang', products: 'produk', ai_documents: 'dokumen AI' };

const FEATURE_LABELS: Record<string, string> = {
    multicurrency: 'Multi mata uang',
    reports: 'Laporan keuangan',
    ai: 'Asisten AI & OCR dokumen',
    advanced_reports: 'Laporan lanjutan',
    api: 'Akses API',
};

function priceFor(plan: Plan, cycle: BillingCycle) {
    return cycle === 'monthly' ? plan.price : cycle === 'yearly' ? plan.price_yearly : plan.price_lifetime;
}

function BillingPage({ company, subscription, plans, orders }: BillingProps) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [cycle, setBillingCycle] = useState<BillingCycle>(subscription.billing_cycle ?? 'yearly');
    const [pending, setPending] = useState<string | null>(null);

    useBreadcrumbs([{ title: 'Langganan & Pembayaran', href: '/billing' }]);

    const choose = (plan: Plan) => {
        setPending(plan.code);
        router.post('/billing/orders', { plan_code: plan.code, billing_cycle: cycle }, { onFinish: () => setPending(null) });
    };

    const isPaid = subscription.billing_cycle !== null;

    return (
        <>
            <Head title="Langganan & Pembayaran" />
            <div className="space-y-6 p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <div className="mb-2 flex items-center gap-2">
                            <IconCreditCard className="size-5 text-nx-cyan-500" />
                            <h1 className="font-display text-2xl font-semibold">Langganan & Pembayaran</h1>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {company.name} ·{' '}
                            {isPaid
                                ? `Paket ${subscription.plan_name} (${CYCLE_LABELS[subscription.billing_cycle!]})${subscription.current_period_end ? `, aktif s/d ${formatDate(subscription.current_period_end)}` : ''}`
                                : `Trial berakhir ${formatDate(company.trial_ends_at, true)}`}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/company/subscription">Detail pemakaian</Link>
                    </Button>
                </div>

                {company.trial_expired && company.purge_at && (
                    <div className="flex items-start gap-3 rounded-lg border border-nx-andon-stop/40 bg-nx-andon-stop-bg p-4 text-sm">
                        <IconAlertTriangle className="mt-0.5 size-5 shrink-0 text-nx-andon-stop" />
                        <div>
                            <p className="font-medium">Masa trial sudah berakhir dan akses perusahaan terkunci.</p>
                            <p className="text-muted-foreground">
                                Pilih paket sebelum <strong className="text-foreground">{formatDate(company.purge_at)}</strong>. Setelah itu seluruh
                                data perusahaan dihapus permanen dan akun tidak bisa login lagi.
                            </p>
                        </div>
                    </div>
                )}

                <div className="flex flex-col items-center gap-2">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={cycle}
                        onValueChange={(value) => value && setBillingCycle(value as BillingCycle)}
                    >
                        {(Object.keys(CYCLE_LABELS) as BillingCycle[]).map((value) => (
                            <ToggleGroupItem key={value} value={value} className="px-5">
                                {CYCLE_LABELS[value]}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    <p className="text-xs text-muted-foreground">Tahunan hemat 2 bulan · Selamanya: bayar sekali, lisensi seumur hidup</p>
                </div>

                {errors.plan_code && <p className="text-center text-sm text-destructive">{errors.plan_code}</p>}

                <div className="mx-auto grid max-w-4xl gap-6 md:grid-cols-2">
                    {plans.map((plan) => {
                        const price = priceFor(plan, cycle);
                        const current = isPaid && subscription.plan_code === plan.code && subscription.billing_cycle === cycle;
                        return (
                            <Card key={plan.code} className={plan.code === 'growth' ? 'border-nx-cyan-500 ring-1 ring-nx-cyan-500' : undefined}>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-xl">{plan.name}</CardTitle>
                                        {plan.code === 'growth' && <Badge className="bg-nx-cyan-500 text-white">Terpopuler</Badge>}
                                    </div>
                                    <CardDescription>{plan.description}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <p>
                                        <span className="font-display text-3xl font-semibold">{price ? formatMoney(price, plan.currency_code) : '-'}</span>
                                        <span className="text-sm text-muted-foreground">{PERIOD_SUFFIX[cycle]}</span>
                                    </p>
                                    <ul className="space-y-2 text-sm">
                                        {Object.entries(plan.limits ?? {}).map(([key, value]) => (
                                            <li key={key} className="flex items-center gap-2">
                                                <IconCheck className="size-4 text-nx-andon-run" />
                                                {value === null ? 'Tanpa batas' : value.toLocaleString('id-ID')} {LIMIT_LABELS[key] ?? key}
                                            </li>
                                        ))}
                                        {Object.entries(plan.features ?? {})
                                            .filter(([, enabled]) => enabled)
                                            .map(([key]) => (
                                                <li key={key} className="flex items-center gap-2">
                                                    <IconCheck className="size-4 text-nx-andon-run" />
                                                    {FEATURE_LABELS[key] ?? key}
                                                </li>
                                            ))}
                                    </ul>
                                </CardContent>
                                <CardFooter>
                                    <Button className="w-full" disabled={!price || pending !== null || current} onClick={() => choose(plan)}>
                                        {pending === plan.code && <Spinner />}
                                        {current ? 'Paket Anda saat ini' : isPaid ? 'Perpanjang / ganti ke paket ini' : 'Pilih paket ini'}
                                    </Button>
                                </CardFooter>
                            </Card>
                        );
                    })}
                </div>

                <Card className="overflow-hidden p-0">
                    <CardHeader className="border-b p-5">
                        <CardTitle>Riwayat pembayaran</CardTitle>
                    </CardHeader>
                    <CardContent className="p-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nomor</TableHead>
                                    <TableHead>Paket</TableHead>
                                    <TableHead>Periode</TableHead>
                                    <TableHead className="text-right">Jumlah</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Tanggal</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {orders.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-20 text-center text-muted-foreground">
                                            Belum ada pembayaran.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    orders.map((order) => (
                                        <TableRow key={order.id}>
                                            <TableCell className="font-mono text-xs">
                                                <Link href={`/billing/orders/${order.id}`} className="hover:underline">
                                                    {order.number}
                                                </Link>
                                            </TableCell>
                                            <TableCell>{order.plan?.name}</TableCell>
                                            <TableCell>{CYCLE_LABELS[order.billing_cycle]}</TableCell>
                                            <TableCell className="text-right tabular-nums">{formatMoney(order.amount, order.currency_code)}</TableCell>
                                            <TableCell>
                                                <Badge variant={ORDER_STATUS[order.status]?.variant ?? 'outline'}>
                                                    {ORDER_STATUS[order.status]?.label ?? order.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">{formatDate(order.paid_at ?? order.created_at, true)}</TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

BillingPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default BillingPage;
