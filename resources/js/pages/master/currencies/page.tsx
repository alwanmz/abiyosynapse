import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Plus, Power, RefreshCw, Save } from 'lucide-react';
import { type FormEvent, type ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string | null;
    minor_unit: number;
}

interface CompanyCurrency {
    id: number;
    currency_code: string;
    is_active: boolean;
    is_base: boolean;
    currency: Currency;
}

interface CurrencyRate {
    id: number;
    from_currency_code: string;
    to_currency_code: string;
    effective_date: string;
    rate: string;
    rate_type: 'general' | 'buying' | 'selling';
    source: string;
    status: string;
    approver?: { id: number; name: string } | null;
    from_currency?: { code: string; name: string } | null;
    to_currency?: { code: string; name: string } | null;
}

interface RevaluationRun {
    id: number;
    currency_code: string;
    revaluation_date: string;
    total_adjustment_base: string;
    status: 'completed' | 'reversed';
    journal_entry?: { id: number; number: string } | null;
    reversal_journal_entry?: { id: number; number: string } | null;
}

interface CurrenciesPageProps {
    baseCurrency: string | null;
    currencies: Currency[];
    companyCurrencies: CompanyCurrency[];
    rates: CurrencyRate[];
    revaluationRuns: RevaluationRun[];
}

function CurrenciesPage({ baseCurrency, currencies, companyCurrencies, rates, revaluationRuns }: CurrenciesPageProps) {
    const { t } = useTranslation('currencies');

    useBreadcrumbs([{ title: t('title'), href: '/master/currencies' }]);

    const activeCodes = new Set(companyCurrencies.filter((item) => item.is_active).map((item) => item.currency_code));
    const availableCurrencies = currencies.filter((currency) => !activeCodes.has(currency.code));
    const enabledCurrencies = companyCurrencies.filter((item) => item.is_active);

    const enableForm = useForm({ currency_code: '' });
    const rateForm = useForm({
        from_currency_code: enabledCurrencies.find((item) => !item.is_base)?.currency_code ?? '',
        to_currency_code: baseCurrency ?? '',
        effective_date: new Date().toISOString().slice(0, 10),
        rate: '',
        rate_type: 'general',
        notes: '',
    });
    const revaluationForm = useForm({
        revaluation_date: new Date().toISOString().slice(0, 10),
        currency_code: enabledCurrencies.find((item) => !item.is_base)?.currency_code ?? '',
    });

    const enableCurrency = (event: FormEvent) => {
        event.preventDefault();
        enableForm.post('/master/currencies/enable', { preserveScroll: true, onSuccess: () => enableForm.reset() });
    };

    const saveRate = (event: FormEvent) => {
        event.preventDefault();
        rateForm.post('/master/currency-rates', { preserveScroll: true, onSuccess: () => rateForm.reset('rate', 'notes') });
    };

    const formatDate = (value: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value));

    return (
        <>
            <Head title={t('title')} />
            <div className="space-y-6 p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">{t('title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('description')}</p>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_1.25fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{t('enabled_currencies')}</CardTitle>
                            <p className="text-sm text-muted-foreground">{t('base_currency')}: <span className="font-mono font-semibold text-foreground">{baseCurrency ?? 'IDR'}</span></p>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="divide-y border">
                                {enabledCurrencies.map((item) => (
                                    <div key={item.id} className="flex min-h-12 items-center justify-between gap-3 px-3 py-2">
                                        <div className="flex min-w-0 items-center gap-3">
                                            <span className="font-mono text-sm font-semibold">{item.currency_code}</span>
                                            <span className="truncate text-sm text-muted-foreground">{item.currency.name}</span>
                                        </div>
                                        {item.is_base ? (
                                            <Badge variant="secondary"><Check className="mr-1 size-3" />{t('base')}</Badge>
                                        ) : (
                                            <Button type="button" variant="ghost" size="icon" title={t('disable')} onClick={() => router.delete(`/master/currencies/${item.currency_code}/disable`, { preserveScroll: true })}>
                                                <Power className="size-4" />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <form onSubmit={enableCurrency} className="grid gap-3 border-t pt-4 sm:grid-cols-[1fr_auto] sm:items-end">
                                <div className="grid gap-2">
                                    <Label htmlFor="currency_code">{t('available_currency')}</Label>
                                    <Select value={enableForm.data.currency_code} onValueChange={(value) => enableForm.setData('currency_code', value)}>
                                        <SelectTrigger id="currency_code"><SelectValue placeholder={availableCurrencies.length ? t('select_placeholder') : t('empty_available')} /></SelectTrigger>
                                        <SelectContent>
                                            {availableCurrencies.map((currency) => <SelectItem key={currency.code} value={currency.code}>{currency.code} - {currency.name}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button type="submit" disabled={enableForm.processing || !enableForm.data.currency_code}><Plus className="size-4" />{t('enable')}</Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle className="text-base">{t('rate_title')}</CardTitle></CardHeader>
                        <CardContent>
                            <form onSubmit={saveRate} className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="from_currency_code">{t('from')}</Label>
                                    <Select value={rateForm.data.from_currency_code} onValueChange={(value) => rateForm.setData('from_currency_code', value)}>
                                        <SelectTrigger id="from_currency_code"><SelectValue placeholder={t('select_placeholder')} /></SelectTrigger>
                                        <SelectContent>{enabledCurrencies.map((item) => <SelectItem key={item.currency_code} value={item.currency_code}>{item.currency_code}</SelectItem>)}</SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="to_currency_code">{t('to')}</Label>
                                    <Select value={rateForm.data.to_currency_code} onValueChange={(value) => rateForm.setData('to_currency_code', value)}>
                                        <SelectTrigger id="to_currency_code"><SelectValue placeholder={t('select_placeholder')} /></SelectTrigger>
                                        <SelectContent>{enabledCurrencies.map((item) => <SelectItem key={item.currency_code} value={item.currency_code}>{item.currency_code}</SelectItem>)}</SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="effective_date">{t('date')}</Label>
                                    <Input id="effective_date" type="date" value={rateForm.data.effective_date} onChange={(event) => rateForm.setData('effective_date', event.target.value)} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="rate">{t('rate')}</Label>
                                    <Input id="rate" type="number" step="0.000000000001" min="0.000000000001" value={rateForm.data.rate} onChange={(event) => rateForm.setData('rate', event.target.value)} placeholder={t('rate_hint')} />
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="rate_type">{t('rate_type')}</Label>
                                    <Select value={rateForm.data.rate_type} onValueChange={(value) => rateForm.setData('rate_type', value as 'general' | 'buying' | 'selling')}>
                                        <SelectTrigger id="rate_type"><SelectValue /></SelectTrigger>
                                        <SelectContent><SelectItem value="general">{t('general')}</SelectItem><SelectItem value="buying">{t('buying')}</SelectItem><SelectItem value="selling">{t('selling')}</SelectItem></SelectContent>
                                    </Select>
                                </div>
                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="notes">{t('notes')}</Label>
                                    <Input id="notes" value={rateForm.data.notes} onChange={(event) => rateForm.setData('notes', event.target.value)} />
                                </div>
                                <div className="flex justify-end sm:col-span-2"><Button type="submit" variant="save" disabled={rateForm.processing}><Save className="size-4" />{t('save_rate')}</Button></div>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader><CardTitle className="text-base">{t('revaluation_title')}</CardTitle><p className="text-sm text-muted-foreground">{t('revaluation_description')}</p></CardHeader>
                    <CardContent className="space-y-4">
                        <form onSubmit={(event) => { event.preventDefault(); revaluationForm.post('/master/currency-revaluations', { preserveScroll: true }); }} className="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                            <div className="grid gap-2"><Label htmlFor="revaluation_currency_code">{t('from')}</Label><Select value={revaluationForm.data.currency_code} onValueChange={(value) => revaluationForm.setData('currency_code', value)}><SelectTrigger id="revaluation_currency_code"><SelectValue placeholder={t('select_placeholder')} /></SelectTrigger><SelectContent>{enabledCurrencies.filter((item) => !item.is_base).map((item) => <SelectItem key={item.currency_code} value={item.currency_code}>{item.currency_code}</SelectItem>)}</SelectContent></Select></div>
                            <div className="grid gap-2"><Label htmlFor="revaluation_date">{t('date')}</Label><Input id="revaluation_date" type="date" value={revaluationForm.data.revaluation_date} onChange={(event) => revaluationForm.setData('revaluation_date', event.target.value)} /></div>
                            <Button type="submit" disabled={revaluationForm.processing || !revaluationForm.data.currency_code}><RefreshCw className="size-4" />{t('run_revaluation')}</Button>
                        </form>
                        <div className="overflow-x-auto border"><Table><TableHeader><TableRow><TableHead>{t('date')}</TableHead><TableHead>{t('from')}</TableHead><TableHead>{t('revaluation_adjustment')}</TableHead><TableHead>{t('status')}</TableHead><TableHead className="text-right">{t('actions')}</TableHead></TableRow></TableHeader><TableBody>{revaluationRuns.length === 0 ? <TableRow><TableCell colSpan={5} className="h-20 text-center text-muted-foreground">{t('empty_revaluations')}</TableCell></TableRow> : revaluationRuns.map((run) => <TableRow key={run.id}><TableCell>{formatDate(run.revaluation_date)}</TableCell><TableCell className="font-mono">{run.currency_code}</TableCell><TableCell className="font-mono tabular-nums">{run.total_adjustment_base}</TableCell><TableCell><Badge variant={run.status === 'completed' ? 'default' : 'outline'}>{t(run.status)}</Badge></TableCell><TableCell className="text-right">{run.status === 'completed' && <Button type="button" variant="ghost" size="icon" title={t('reverse_revaluation')} onClick={() => router.delete(`/master/currency-revaluations/${run.id}`, { preserveScroll: true })}><RefreshCw className="size-4" /></Button>}</TableCell></TableRow>)}</TableBody></Table></div>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden p-0">
                    <CardHeader className="border-b"><CardTitle className="text-base">{t('rate_history')}</CardTitle></CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader><TableRow><TableHead>{t('from')}</TableHead><TableHead>{t('to')}</TableHead><TableHead>{t('date')}</TableHead><TableHead className="text-right">{t('rate')}</TableHead><TableHead>{t('rate_type')}</TableHead><TableHead>{t('source')}</TableHead><TableHead>{t('approved')}</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    {rates.length === 0 ? <TableRow><TableCell colSpan={7} className="h-24 text-center text-muted-foreground">{t('empty_rates')}</TableCell></TableRow> : rates.map((rate) => (
                                        <TableRow key={rate.id}>
                                            <TableCell className="font-mono">{rate.from_currency_code}</TableCell>
                                            <TableCell className="font-mono">{rate.to_currency_code}</TableCell>
                                            <TableCell className="whitespace-nowrap">{formatDate(rate.effective_date)}</TableCell>
                                            <TableCell className="text-right font-mono tabular-nums">{rate.rate}</TableCell>
                                            <TableCell>{t(rate.rate_type)}</TableCell>
                                            <TableCell><Badge variant="outline">{rate.source}</Badge></TableCell>
                                            <TableCell>{rate.status === 'approved' ? <Badge variant="default">{t('approved')}</Badge> : <Badge variant="outline">{rate.status}</Badge>}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CurrenciesPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default CurrenciesPage;
