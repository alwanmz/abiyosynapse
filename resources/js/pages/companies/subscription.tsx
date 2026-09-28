import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Head, Link, useForm } from '@inertiajs/react';
import { CreditCard, ShieldAlert } from 'lucide-react';
import { type ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

type TenantStatus = 'active' | 'suspended' | 'archived';

interface CompanySummary {
    id: number;
    name: string;
    code: string;
    status: TenantStatus;
    is_active: boolean;
    trial_ends_at: string | null;
}

interface Plan {
    code: string;
    name: string;
    description: string | null;
    price: string;
    currency_code: string;
    limits: Record<string, number | null> | null;
    features: Record<string, boolean> | null;
}

interface Subscription {
    status: string;
    trial_ends_at: string | null;
    current_period_end: string | null;
    plan: Plan | null;
}

interface Usage {
    used: number;
    limit: number | null;
}

interface CompanySubscriptionProps {
    company: CompanySummary;
    subscription: Subscription;
    usage: Record<string, Usage>;
}

function CompanySubscriptionPage({ company, subscription, usage }: CompanySubscriptionProps) {
    const { t } = useTranslation('companies');
    const suspendForm = useForm({ reason: '' });
    const activateForm = useForm({});

    useBreadcrumbs([
        { title: t('subscription.breadcrumb'), href: '/company/subscription' },
    ]);

    const date = (value: string | null) => value
        ? new Date(value).toLocaleDateString(undefined, { dateStyle: 'medium' })
        : t('subscription.not_available');

    const statusVariant = company.status === 'suspended' ? 'destructive' : company.status === 'active' ? 'default' : 'outline';
    const statusLabel = t(`subscription.status.${company.status}`, { defaultValue: company.status });

    return (
        <>
            <Head title={t('subscription.head_title')} />
            <div className="space-y-6 p-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <div className="mb-2 flex items-center gap-2">
                            <CreditCard className="size-5 text-nx-cyan-500" />
                            <h1 className="font-display text-2xl font-semibold">{t('subscription.title')}</h1>
                        </div>
                        <p className="text-sm text-muted-foreground">{company.name} · {company.code}</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge variant={statusVariant}>{statusLabel}</Badge>
                        <Button asChild>
                            <Link href="/billing">Upgrade / Bayar langganan</Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.8fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>{subscription.plan?.name ?? t('subscription.no_plan')}</CardTitle>
                            <CardDescription>{subscription.plan?.description ?? t('subscription.no_plan_description')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-xs text-muted-foreground">{t('subscription.status_label')}</p>
                                    <p className="font-medium">{t(`subscription.subscription_status.${subscription.status}`, { defaultValue: subscription.status })}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">{t('subscription.trial_ends_at')}</p>
                                    <p className="font-medium">{date(subscription.trial_ends_at)}</p>
                                </div>
                            </div>

                            {company.status === 'suspended' ? (
                                <form onSubmit={(event) => {
                                    event.preventDefault();
                                    activateForm.post('/company/subscription/activate');
                                }}>
                                    <Button type="submit" disabled={activateForm.processing}>
                                        {activateForm.processing ? t('subscription.activating') : t('subscription.activate')}
                                    </Button>
                                </form>
                            ) : (
                                <form className="space-y-3 border-t pt-4" onSubmit={(event) => {
                                    event.preventDefault();
                                    suspendForm.post('/company/subscription/suspend');
                                }}>
                                    <div className="flex items-center gap-2 text-sm font-medium">
                                        <ShieldAlert className="size-4 text-nx-hanko-500" />
                                        {t('subscription.suspend_title')}
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="suspension-reason">{t('subscription.suspend_reason')}</Label>
                                        <Textarea
                                            id="suspension-reason"
                                            value={suspendForm.data.reason}
                                            onChange={(event) => suspendForm.setData('reason', event.target.value)}
                                            placeholder={t('subscription.suspend_reason_placeholder')}
                                        />
                                    </div>
                                    <Button type="submit" variant="destructive" disabled={suspendForm.processing}>
                                        {suspendForm.processing ? t('subscription.suspending') : t('subscription.suspend')}
                                    </Button>
                                </form>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('subscription.usage_title')}</CardTitle>
                            <CardDescription>{t('subscription.usage_description')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {Object.entries(usage).map(([key, item]) => {
                                const percentage = item.limit ? Math.min(100, Math.round((item.used / item.limit) * 100)) : 0;
                                return (
                                    <div key={key} className="space-y-2">
                                        <div className="flex items-center justify-between gap-3 text-sm">
                                            <span>{t(`subscription.quota.${key}`, { defaultValue: key })}</span>
                                            <span className="font-mono text-xs tabular-nums text-muted-foreground">
                                                {item.used} / {item.limit ?? t('subscription.unlimited')}
                                            </span>
                                        </div>
                                        {item.limit !== null && (
                                            <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                <div className="h-full bg-nx-cyan-500 transition-[width]" style={{ width: `${percentage}%` }} />
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

CompanySubscriptionPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default CompanySubscriptionPage;
