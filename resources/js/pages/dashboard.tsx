import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpRight, Banknote, FileWarning, Minus, TrendingUp, Wrench } from 'lucide-react';
import { type FormEvent, type ReactElement, type ReactNode, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface FinancialMetrics {
    revenue: number;
    cogs: number;
    gross_profit: number;
    gross_margin: number;
    net_profit: number;
    cash_balance: number;
    ar_outstanding: number;
    ap_outstanding: number;
}

interface OperationalMetrics {
    inventory_value: number;
    production_planned: number;
    production_output: number;
    production_rejected: number;
    production_completed: number;
    yield_rate: number;
    otd_rate: number;
}

interface QualityMetrics {
    inspection_count: number;
    pass_rate: number;
    failed_quantity: number;
    open_ncrs: number;
}

interface MaintenanceMetrics { high_risk_equipment: number; overdue_preventive: number; downtime_minutes: number; cost_base: number; insufficient_data: number; }

interface TrendRow {
    key: string;
    revenue: number;
    expenses: number;
    production_output: number;
    production_rejected: number;
}

interface AttentionItem {
    key: 'overdue_ar' | 'overdue_ap' | 'open_ncr' | 'late_production';
    count: number;
    href: string;
}

interface ComparisonMetric {
    current: number;
    previous: number | null;
    absolute: number | null;
    percent: number | null;
}

interface DashboardComparison {
    previous_period: { from: string; to: string };
    metrics: {
        financial: Record<string, ComparisonMetric>;
        operations: Record<string, ComparisonMetric>;
        quality: Record<string, ComparisonMetric>;
    };
}

interface DashboardPageProps {
    period: { from: string; to: string };
    financial: FinancialMetrics;
    operations: OperationalMetrics;
    quality: QualityMetrics;
    maintenance: MaintenanceMetrics;
    trend: TrendRow[];
    attention: AttentionItem[];
    comparison: DashboardComparison;
}

type Tone = 'run' | 'caution' | 'stop' | 'info' | 'neutral';

const toneStyles: Record<Tone, { card: string; icon: string; value: string; text: string }> = {
    run: {
        card: 'border-nx-andon-run/35 bg-nx-andon-run-bg',
        icon: 'text-nx-andon-run',
        value: 'text-nx-andon-run',
        text: 'text-nx-andon-run',
    },
    caution: {
        card: 'border-nx-andon-caution/40 bg-nx-andon-caution-bg',
        icon: 'text-nx-andon-caution',
        value: 'text-nx-andon-caution',
        text: 'text-nx-andon-caution',
    },
    stop: {
        card: 'border-nx-andon-stop/35 bg-nx-andon-stop-bg',
        icon: 'text-nx-andon-stop',
        value: 'text-nx-andon-stop',
        text: 'text-nx-andon-stop',
    },
    info: {
        card: 'border-nx-andon-info/35 bg-nx-andon-info-bg',
        icon: 'text-nx-andon-info',
        value: 'text-nx-andon-info',
        text: 'text-nx-andon-info',
    },
    neutral: {
        card: 'border-border bg-card',
        icon: 'text-muted-foreground',
        value: 'text-foreground',
        text: 'text-muted-foreground',
    },
};

function DashboardContent(props: DashboardPageProps) {
    const { t } = useTranslation('dashboard');
    const { locale, currentCompany } = usePage().props as { locale?: string; currentCompany?: { currency: string } | null };
    const [fromDate, setFromDate] = useState(props.period.from);
    const [toDate, setToDate] = useState(props.period.to);

    useBreadcrumbs([{ title: t('breadcrumb'), href: '/dashboard' }]);

    const formatCurrency = (value: number) => new Intl.NumberFormat(locale ?? 'id-ID', {
        style: 'currency',
        currency: currentCompany?.currency ?? 'IDR',
        maximumFractionDigits: 0,
    }).format(value);

    const formatQuantity = (value: number) => new Intl.NumberFormat(locale ?? 'id-ID', {
        maximumFractionDigits: 2,
    }).format(value);

    const formatMonth = (key: string) => {
        const [year, month] = key.split('-').map(Number);
        return new Intl.DateTimeFormat(locale ?? 'id-ID', { month: 'short' }).format(new Date(year, month - 1, 1));
    };

    const formatDate = (value: string) => new Intl.DateTimeFormat(locale ?? 'id-ID', {
        dateStyle: 'medium',
    }).format(new Date(value + 'T00:00:00'));
    const comparisonLabel = t('comparison.vs_previous') + ' ' + formatDate(props.comparison.previous_period.from) + ' - ' + formatDate(props.comparison.previous_period.to);

    const maxFinancialTrendValue = useMemo(() => Math.max(
        1,
        ...props.trend.flatMap((row) => [row.revenue, row.expenses]),
    ), [props.trend]);
    const maxProductionTrendValue = useMemo(() => Math.max(
        1,
        ...props.trend.flatMap((row) => [row.production_output, row.production_rejected]),
    ), [props.trend]);

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get('/dashboard', { from_date: fromDate, to_date: toDate }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title={t('head_title')} />

            <div className="space-y-6 p-6">
                <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">{t('welcome')}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">{t('description')}</p>
                    </div>
                    <Card className="w-full p-0 lg:w-auto">
                        <CardContent className="p-4">
                            <form onSubmit={applyFilters} className="grid items-end gap-3 sm:grid-cols-[minmax(150px,1fr)_minmax(150px,1fr)_auto]">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="dashboard_from_date">{t('filters.from')}</Label>
                                    <Input id="dashboard_from_date" type="date" value={fromDate} onChange={(event) => setFromDate(event.target.value)} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="dashboard_to_date">{t('filters.to')}</Label>
                                    <Input id="dashboard_to_date" type="date" value={toDate} onChange={(event) => setToDate(event.target.value)} />
                                </div>
                                <Button type="submit"><TrendingUp className="size-4" />{t('filters.apply')}</Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <section aria-labelledby="financial-heading">
                    <SectionHeading id="financial-heading" title={t('financial.title')} />
                    <div className="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <MetricCard icon={<TrendingUp />} label={t('financial.revenue')} value={formatCurrency(props.financial.revenue)} detail={t('financial.gross_margin') + ': ' + props.financial.gross_margin + '%'} tone={deltaTone('revenue', props.financial.revenue, props.comparison.metrics.financial.revenue)} delta={props.comparison.metrics.financial.revenue} formatDelta={formatCurrency} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} />
                        <MetricCard icon={<Banknote />} label={t('financial.gross_profit')} value={formatCurrency(props.financial.gross_profit)} detail={t('financial.cogs') + ': ' + formatCurrency(props.financial.cogs)} tone={props.financial.gross_profit < 0 ? 'stop' : deltaTone('gross_profit', props.financial.gross_profit, props.comparison.metrics.financial.gross_profit)} delta={props.comparison.metrics.financial.gross_profit} formatDelta={formatCurrency} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} />
                        <MetricCard icon={<Banknote />} label={t('financial.cash_balance')} value={formatCurrency(props.financial.cash_balance)} detail={t('financial.net_profit') + ': ' + formatCurrency(props.financial.net_profit)} tone={props.financial.cash_balance < 0 ? 'stop' : 'info'} delta={props.comparison.metrics.financial.cash_balance} formatDelta={formatCurrency} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} />
                        <MetricCard icon={<ArrowUpRight />} label={t('financial.ar_outstanding')} value={formatCurrency(props.financial.ar_outstanding)} detail={t('financial.ap_outstanding') + ': ' + formatCurrency(props.financial.ap_outstanding)} tone={props.financial.ar_outstanding > 0 ? 'caution' : 'run'} delta={props.comparison.metrics.financial.ar_outstanding} formatDelta={formatCurrency} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} semantic="risk" />
                    </div>
                </section>

                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2 text-base"><Wrench className="size-4 text-nx-andon-info" />{t('maintenance.title')}</CardTitle><CardDescription>{t('maintenance.description')}</CardDescription></CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <MetricLine label={t('maintenance.high_risk_equipment')} value={formatQuantity(props.maintenance.high_risk_equipment)} tone={props.maintenance.high_risk_equipment > 0 ? 'stop' : 'run'} semantic="risk" />
                        <MetricLine label={t('maintenance.overdue_preventive')} value={formatQuantity(props.maintenance.overdue_preventive)} tone={props.maintenance.overdue_preventive > 0 ? 'caution' : 'run'} semantic="risk" />
                        <MetricLine label={t('maintenance.downtime_minutes')} value={formatQuantity(props.maintenance.downtime_minutes)} tone={props.maintenance.downtime_minutes > 0 ? 'caution' : 'run'} semantic="risk" />
                        <MetricLine label={t('maintenance.cost_base')} value={formatCurrency(props.maintenance.cost_base)} tone={props.maintenance.cost_base > 0 ? 'caution' : 'run'} semantic="risk" />
                        <MetricLine label={t('maintenance.insufficient_data')} value={formatQuantity(props.maintenance.insufficient_data)} tone={props.maintenance.insufficient_data > 0 ? 'info' : 'run'} semantic="neutral" />
                    </CardContent>
                </Card>

                <div className="grid gap-6 xl:grid-cols-[1.35fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('operations.title')}</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                            <MetricLine label={t('operations.inventory_value')} value={formatCurrency(props.operations.inventory_value)} tone="info" delta={props.comparison.metrics.operations.inventory_value} formatDelta={formatCurrency} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} semantic="neutral" />
                            <MetricLine label={t('operations.production_output')} value={formatQuantity(props.operations.production_output)} tone="run" delta={props.comparison.metrics.operations.production_output} formatDelta={formatQuantity} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} />
                            <MetricLine label={t('operations.production_planned')} value={formatQuantity(props.operations.production_planned)} tone="info" delta={props.comparison.metrics.operations.production_planned} formatDelta={formatQuantity} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} semantic="neutral" />
                            <MetricLine label={t('operations.production_rejected')} value={formatQuantity(props.operations.production_rejected)} tone={props.operations.production_rejected > 0 ? 'stop' : 'run'} delta={props.comparison.metrics.operations.production_rejected} formatDelta={formatQuantity} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} semantic="risk" />
                            <MetricLine label={t('operations.production_completed')} value={formatQuantity(props.operations.production_completed)} tone="run" delta={props.comparison.metrics.operations.production_completed} formatDelta={formatQuantity} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} />
                            <MetricLine label={t('operations.otd_rate')} value={props.operations.otd_rate + '%'} progress={props.operations.otd_rate} tone={rateTone(props.operations.otd_rate)} delta={props.comparison.metrics.operations.otd_rate} formatDelta={(value) => value + '%'} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} />
                            <div className="sm:col-span-2">
                                <MetricLine label={t('operations.yield_rate')} value={props.operations.yield_rate + '%'} progress={props.operations.yield_rate} tone={rateTone(props.operations.yield_rate)} delta={props.comparison.metrics.operations.yield_rate} formatDelta={(value) => value + '%'} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('quality.title')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <MetricLine label={t('quality.pass_rate')} value={props.quality.pass_rate + '%'} progress={props.quality.pass_rate} tone={rateTone(props.quality.pass_rate)} delta={props.comparison.metrics.quality.pass_rate} formatDelta={(value) => value + '%'} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} />
                            <div className="grid grid-cols-3 gap-4 border-t pt-4">
                                <MetricLine label={t('quality.inspection_count')} value={formatQuantity(props.quality.inspection_count)} tone="info" delta={props.comparison.metrics.quality.inspection_count} formatDelta={formatQuantity} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} semantic="neutral" />
                                <MetricLine label={t('quality.failed_quantity')} value={formatQuantity(props.quality.failed_quantity)} tone={props.quality.failed_quantity > 0 ? 'stop' : 'run'} delta={props.comparison.metrics.quality.failed_quantity} formatDelta={formatQuantity} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} semantic="risk" />
                                <MetricLine label={t('quality.open_ncrs')} value={formatQuantity(props.quality.open_ncrs)} tone={props.quality.open_ncrs > 0 ? 'caution' : 'run'} delta={props.comparison.metrics.quality.open_ncrs} formatDelta={formatQuantity} comparisonLabel={comparisonLabel} noDataLabel={t('comparison.no_data')} newLabel={t('comparison.new')} semantic="risk" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1.35fr_1fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('trend.title')}</CardTitle>
                            <CardDescription>{t('trend.description')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {props.trend.length === 0 ? <p className="text-sm text-muted-foreground">{t('empty')}</p> : props.trend.map((row) => (
                                <div key={row.key} className="grid grid-cols-[3.5rem_1fr] items-center gap-3">
                                    <span className="text-xs font-medium text-muted-foreground">{formatMonth(row.key)}</span>
                                    <div className="space-y-1.5">
                                        <TrendBar label={t('trend.revenue')} value={row.revenue} max={maxFinancialTrendValue} color="bg-nx-navy-700" format={formatCurrency} />
                                        <TrendBar label={t('trend.expenses')} value={row.expenses} max={maxFinancialTrendValue} color="bg-nx-andon-caution" format={formatCurrency} />
                                        <TrendBar label={t('trend.output')} value={row.production_output} max={maxProductionTrendValue} color="bg-nx-cyan-500" format={formatQuantity} />
                                        <TrendBar label={t('trend.rejected')} value={row.production_rejected} max={maxProductionTrendValue} color="bg-nx-andon-stop" format={formatQuantity} />
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('attention.title')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {props.attention.length === 0 ? (
                                <p className="text-sm text-muted-foreground">{t('attention.empty')}</p>
                            ) : (
                                <div className="divide-y">
                                    {props.attention.map((item) => (
                                        <Link key={item.key} href={item.href} className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0 hover:text-primary">
                                            <span className="flex min-w-0 items-center gap-3 text-sm">
                                                <FileWarning className={'size-4 shrink-0 ' + attentionTone(item.key).icon} />
                                                <span className="truncate">{t('attention.' + item.key)}</span>
                                            </span>
                                            <span className="flex shrink-0 items-center gap-2">
                                                <Badge variant={attentionTone(item.key).badge}>{item.count}</Badge>
                                                <ArrowUpRight className="size-4" />
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function SectionHeading({ id, title }: { id?: string; title: string }) {
    return <h2 id={id} className="font-display text-sm font-semibold uppercase tracking-[0.08em] text-muted-foreground">{title}</h2>;
}

function MetricCard({
    icon,
    label,
    value,
    detail,
    tone,
    delta,
    formatDelta,
    comparisonLabel,
    noDataLabel,
    newLabel,
    semantic = 'good-up',
}: {
    icon: ReactNode;
    label: string;
    value: string;
    detail: string;
    tone: Tone;
    delta?: ComparisonMetric;
    formatDelta: (value: number) => string;
    comparisonLabel: string;
    noDataLabel: string;
    newLabel: string;
    semantic?: DeltaSemantic;
}) {
    const styles = toneStyles[tone];

    return (
        <Card className={styles.card}>
            <CardContent className="p-5">
                <div className={'flex items-center gap-2 ' + styles.icon}>{icon}<span className="text-xs font-medium uppercase tracking-wide">{label}</span></div>
                <div className={'mt-3 truncate font-display text-xl font-semibold tabular-nums ' + styles.value}>{value}</div>
                <div className="mt-1 truncate text-xs text-muted-foreground">{detail}</div>
                {delta && <DeltaBadge metric={delta} format={formatDelta} comparisonLabel={comparisonLabel} noDataLabel={noDataLabel} newLabel={newLabel} semantic={semantic} />}
            </CardContent>
        </Card>
    );
}

type DeltaSemantic = 'good-up' | 'good-down' | 'risk' | 'neutral';

function MetricLine({
    label,
    value,
    progress,
    tone = 'neutral',
    delta,
    formatDelta,
    comparisonLabel,
    noDataLabel,
    newLabel,
    semantic = 'good-up',
}: {
    label: string;
    value: string;
    progress?: number;
    tone?: Tone;
    delta?: ComparisonMetric;
    formatDelta?: (value: number) => string;
    comparisonLabel?: string;
    noDataLabel?: string;
    newLabel?: string;
    semantic?: DeltaSemantic;
}) {
    const styles = toneStyles[tone];

    return (
        <div>
            <div className="flex items-baseline justify-between gap-3">
                <span className="truncate text-sm text-muted-foreground">{label}</span>
                <span className={'shrink-0 font-medium tabular-nums ' + styles.value}>{value}</span>
            </div>
            {progress !== undefined && <Progress value={Math.min(100, Math.max(0, progress))} tone={tone === 'neutral' ? 'default' : tone} className="mt-2 h-1.5" />}
            {delta && formatDelta && comparisonLabel && noDataLabel && newLabel && <DeltaBadge metric={delta} format={formatDelta} comparisonLabel={comparisonLabel} noDataLabel={noDataLabel} newLabel={newLabel} semantic={semantic} compact />}
        </div>
    );
}

function DeltaBadge({
    metric,
    format,
    comparisonLabel,
    noDataLabel,
    newLabel,
    semantic = 'good-up',
}: {
    metric: ComparisonMetric;
    format: (value: number) => string;
    comparisonLabel: string;
    noDataLabel: string;
    newLabel: string;
    semantic?: DeltaSemantic;
    compact?: boolean;
}) {
    const hasPrevious = metric.previous !== null;
    const delta = metric.absolute ?? 0;
    const direction = delta > 0 ? 'up' : delta < 0 ? 'down' : 'flat';
    const tone = !hasPrevious ? 'info' : direction === 'flat' ? 'neutral' : deltaToneForMetric(metric, direction, semantic);
    const styles = toneStyles[tone];

    return (
        <div className={'mt-2 flex min-w-0 items-center gap-1.5 text-xs ' + styles.text}>
            {!hasPrevious ? (
                <span className="truncate">{newLabel} · {noDataLabel}</span>
            ) : (
                <>
                    {direction === 'up' && <ArrowUp className="size-3.5 shrink-0" />}
                    {direction === 'down' && <ArrowDown className="size-3.5 shrink-0" />}
                    {direction === 'flat' && <Minus className="size-3.5 shrink-0" />}
                    <span className="truncate">
                        {formatWithSign(delta, format)}{metric.percent !== null ? ' (' + formatWithSign(metric.percent, (value) => value + '%') + ')' : ''} · {comparisonLabel}
                    </span>
                </>
            )}
        </div>
    );
}

function formatWithSign(value: number, format: (value: number) => string): string {
    return (value > 0 ? '+' : value < 0 ? '-' : '') + format(Math.abs(value));
}

function deltaToneForMetric(metric: ComparisonMetric, direction: 'up' | 'down' | 'flat', semantic: DeltaSemantic): Tone {
    if (metric.current < 0) {
        return 'stop';
    }

    if (semantic === 'neutral') {
        return 'info';
    }

    const favorable = semantic === 'risk' ? direction === 'down' : direction === 'up';
    return favorable ? 'run' : 'stop';
}

function deltaTone(key: string, current: number, metric?: ComparisonMetric): Tone {
    if (current < 0) {
        return 'stop';
    }

    if (!metric || metric.previous === null || metric.absolute === null || Math.abs(metric.absolute) < 0.000001) {
        return 'info';
    }

    const riskMetric = ['cogs', 'ar_outstanding', 'ap_outstanding', 'production_rejected', 'failed_quantity', 'open_ncrs'].includes(key);
    const increasing = metric.absolute > 0;
    return riskMetric ? (increasing ? 'stop' : 'run') : (increasing ? 'run' : 'stop');
}

function rateTone(value: number): Tone {
    return value >= 95 ? 'run' : value >= 80 ? 'caution' : 'stop';
}

function attentionTone(key: AttentionItem['key']): { icon: string; badge: 'destructive' | 'secondary' | 'outline' } {
    return key === 'late_production'
        ? { icon: 'text-nx-andon-caution', badge: 'secondary' }
        : { icon: 'text-nx-andon-stop', badge: 'destructive' };
}

function TrendBar({ label, value, max, color, format }: { label: string; value: number; max: number; color: string; format: (value: number) => string }) {
    return (
        <div className="flex items-center gap-2">
            <span className="w-16 shrink-0 text-[11px] text-muted-foreground">{label}</span>
            <div className="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-muted">
                <div className={`h-full rounded-full ${color}`} style={{ width: `${Math.min(100, (value / max) * 100)}%` }} />
            </div>
            <span className="w-28 shrink-0 text-right text-[11px] tabular-nums text-muted-foreground">{format(value)}</span>
        </div>
    );
}

function Dashboard(props: DashboardPageProps) {
    return <DashboardContent key={`${props.period.from}:${props.period.to}`} {...props} />;
}

Dashboard.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default Dashboard;
