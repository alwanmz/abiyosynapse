import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import AppLayout from '@/layouts/app-layout';
import { router, Head, usePage } from '@inertiajs/react';
import { IconRefresh } from '@tabler/icons-react';
import { type FormEvent, type ReactElement, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

type ReportKey = 'trial_balance' | 'general_ledger' | 'profit_loss' | 'balance_sheet';

interface Account {
    id: number;
    code: string;
    name: string;
    type: string;
    normal_balance: string;
}

interface AccountAmountRow extends Account {
    amount: number;
}

interface TrialBalance {
    as_of: string;
    rows: Array<Account & { debit: number; credit: number }>;
    totals: { debit: number; credit: number };
}

interface GeneralLedgerLine {
    id: number;
    entry_date: string;
    number: string;
    description: string | null;
    debit: number;
    credit: number;
    balance: number;
}

interface GeneralLedger {
    account: Account | null;
    opening_balance: number;
    lines: GeneralLedgerLine[];
    closing_balance: number;
}

interface ProfitLoss {
    from: string;
    to: string;
    revenue: AccountAmountRow[];
    expenses: AccountAmountRow[];
    totals: { revenue: number; expenses: number; net_profit: number };
}

interface BalanceSheet {
    as_of: string;
    assets: AccountAmountRow[];
    liabilities: AccountAmountRow[];
    equity: AccountAmountRow[];
    current_earnings: number;
    totals: {
        assets: number;
        liabilities: number;
        equity: number;
        liabilities_and_equity: number;
        difference: number;
    };
}

interface PageProps {
    report: ReportKey;
    fromDate: string;
    toDate: string;
    selectedAccountId: number | null;
    accounts: Account[];
    trialBalance?: TrialBalance;
    generalLedger?: GeneralLedger;
    profitLoss?: ProfitLoss;
    balanceSheet?: BalanceSheet;
}

function FinancialReportsPage(props: PageProps) {
    const { t } = useTranslation('reports');
    const { locale, currentCompany } = usePage().props as { locale?: string; currentCompany?: { currency: string } | null };
    const [report, setReport] = useState<ReportKey>(props.report);
    const [fromDate, setFromDate] = useState(props.fromDate);
    const [toDate, setToDate] = useState(props.toDate);
    const [accountId, setAccountId] = useState(props.selectedAccountId?.toString() ?? '');

    useBreadcrumbs([
        { title: t('nav.gl_reports'), href: '#' },
        { title: t('title'), href: '/reports/gl' },
    ]);

    useEffect(() => {
        setReport(props.report);
        setFromDate(props.fromDate);
        setToDate(props.toDate);
        setAccountId(props.selectedAccountId?.toString() ?? '');
    }, [props.report, props.fromDate, props.toDate, props.selectedAccountId]);

    const formatCurrency = (value: number) =>
        new Intl.NumberFormat(locale ?? 'id-ID', {
            style: 'currency',
            currency: currentCompany?.currency ?? 'IDR',
            maximumFractionDigits: 0,
        }).format(value);

    const formatDate = (value: string) => {
        const [year, month, day] = value.slice(0, 10).split('-').map(Number);
        return new Intl.DateTimeFormat(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        }).format(new Date(year, month - 1, day));
    };

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            '/reports/gl',
            {
                report,
                from_date: fromDate,
                to_date: toDate,
                ...(report === 'general_ledger' && accountId ? { account_id: accountId } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const isBalanced = (debit: number, credit: number) => Math.abs(debit - credit) < 0.01;

    return (
        <>
            <Head title={t('title')} />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="font-display text-2xl font-semibold tracking-tight">{t('title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('description')}</p>
                </div>

                <Card className="overflow-hidden p-0">
                    <CardContent className="p-5">
                        <form onSubmit={applyFilters} className="grid items-end gap-4 md:grid-cols-6">
                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="report">{t('filters.report')}</Label>
                                <Select value={report} onValueChange={(value) => setReport(value as ReportKey)}>
                                    <SelectTrigger id="report"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="trial_balance">{t('report.trial_balance')}</SelectItem>
                                        <SelectItem value="general_ledger">{t('report.general_ledger')}</SelectItem>
                                        <SelectItem value="profit_loss">{t('report.profit_loss')}</SelectItem>
                                        <SelectItem value="balance_sheet">{t('report.balance_sheet')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="from_date">{t('filters.from')}</Label>
                                <Input id="from_date" type="date" value={fromDate} onChange={(event) => setFromDate(event.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="to_date">{t('filters.to')}</Label>
                                <Input id="to_date" type="date" value={toDate} onChange={(event) => setToDate(event.target.value)} />
                            </div>
                            {report === 'general_ledger' ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="account_id">{t('filters.account')}</Label>
                                    <Select value={accountId} onValueChange={setAccountId}>
                                        <SelectTrigger id="account_id"><SelectValue placeholder={t('filters.account_placeholder')} /></SelectTrigger>
                                        <SelectContent className="max-h-72">
                                            {props.accounts.map((account) => (
                                                <SelectItem key={account.id} value={account.id.toString()}>
                                                    {account.code} — {account.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            ) : <div className="hidden md:block" />}
                            <Button type="submit">
                                <IconRefresh className="size-4" />
                                {t('filters.apply')}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {report === 'trial_balance' && props.trialBalance && (
                    <Card className="overflow-hidden p-0">
                        <ReportHeading title={t('trial_balance.title')} subtitle={`${t('trial_balance.as_of')} ${formatDate(props.trialBalance.as_of)}`} />
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader><TableRow>
                                        <TableHead>{t('common.account')}</TableHead>
                                        <TableHead>{t('common.name')}</TableHead>
                                        <TableHead className="text-right">{t('common.debit')}</TableHead>
                                        <TableHead className="text-right">{t('common.credit')}</TableHead>
                                    </TableRow></TableHeader>
                                    <TableBody>
                                        {props.trialBalance.rows.map((row) => (
                                            <TableRow key={row.id}>
                                                <TableCell className="font-mono text-sm">{row.code}</TableCell>
                                                <TableCell>{row.name}</TableCell>
                                                <TableCell className="text-right tabular-nums">{formatCurrency(row.debit)}</TableCell>
                                                <TableCell className="text-right tabular-nums">{formatCurrency(row.credit)}</TableCell>
                                            </TableRow>
                                        ))}
                                        {props.trialBalance.rows.length === 0 && <EmptyRow colSpan={4} label={t('common.empty')} />}
                                        <TableRow className="border-t-2 font-semibold">
                                            <TableCell colSpan={2}>{t('common.total')}</TableCell>
                                            <TableCell className="text-right tabular-nums">{formatCurrency(props.trialBalance.totals.debit)}</TableCell>
                                            <TableCell className="text-right tabular-nums">{formatCurrency(props.trialBalance.totals.credit)}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                            <div className="border-t px-5 py-3">
                                <Badge variant={isBalanced(props.trialBalance.totals.debit, props.trialBalance.totals.credit) ? 'default' : 'destructive'}>
                                    {isBalanced(props.trialBalance.totals.debit, props.trialBalance.totals.credit) ? t('trial_balance.balanced') : t('trial_balance.not_balanced')}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {report === 'general_ledger' && props.generalLedger && (
                    <Card className="overflow-hidden p-0">
                        <ReportHeading
                            title={t('general_ledger.title')}
                            subtitle={props.generalLedger.account ? `${props.generalLedger.account.code} — ${props.generalLedger.account.name}` : undefined}
                        />
                        <CardContent className="p-0">
                            <div className="grid gap-0 border-b bg-muted/30 sm:grid-cols-2">
                                <SummaryCell label={t('general_ledger.opening')} value={formatCurrency(props.generalLedger.opening_balance)} />
                                <SummaryCell label={t('general_ledger.closing')} value={formatCurrency(props.generalLedger.closing_balance)} />
                            </div>
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader><TableRow>
                                        <TableHead>{t('common.date')}</TableHead>
                                        <TableHead>{t('common.number')}</TableHead>
                                        <TableHead>{t('common.description_label')}</TableHead>
                                        <TableHead className="text-right">{t('common.debit')}</TableHead>
                                        <TableHead className="text-right">{t('common.credit')}</TableHead>
                                        <TableHead className="text-right">{t('general_ledger.closing')}</TableHead>
                                    </TableRow></TableHeader>
                                    <TableBody>
                                        {props.generalLedger.lines.map((line) => (
                                            <TableRow key={line.id}>
                                                <TableCell className="whitespace-nowrap text-sm text-muted-foreground">{formatDate(line.entry_date)}</TableCell>
                                                <TableCell className="font-mono text-sm">{line.number}</TableCell>
                                                <TableCell>{line.description || '—'}</TableCell>
                                                <TableCell className="text-right tabular-nums">{formatCurrency(line.debit)}</TableCell>
                                                <TableCell className="text-right tabular-nums">{formatCurrency(line.credit)}</TableCell>
                                                <TableCell className="text-right tabular-nums">{formatCurrency(line.balance)}</TableCell>
                                            </TableRow>
                                        ))}
                                        {props.generalLedger.lines.length === 0 && <EmptyRow colSpan={6} label={t('general_ledger.empty')} />}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {report === 'profit_loss' && props.profitLoss && (
                    <Card className="overflow-hidden p-0">
                        <ReportHeading title={t('profit_loss.title')} subtitle={`${formatDate(props.profitLoss.from)} — ${formatDate(props.profitLoss.to)}`} />
                        <CardContent className="grid gap-6 p-5 lg:grid-cols-2">
                            <StatementSection title={t('profit_loss.revenue')} rows={props.profitLoss.revenue} totalLabel={t('profit_loss.total_revenue')} total={props.profitLoss.totals.revenue} formatCurrency={formatCurrency} empty={t('common.empty')} />
                            <StatementSection title={t('profit_loss.expenses')} rows={props.profitLoss.expenses} totalLabel={t('profit_loss.total_expenses')} total={props.profitLoss.totals.expenses} formatCurrency={formatCurrency} empty={t('common.empty')} />
                            <div className="border-t-2 pt-4 lg:col-span-2">
                                <div className="flex items-center justify-between font-semibold">
                                    <span>{t('profit_loss.net_profit')}</span>
                                    <span className="tabular-nums">{formatCurrency(props.profitLoss.totals.net_profit)}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {report === 'balance_sheet' && props.balanceSheet && (
                    <Card className="overflow-hidden p-0">
                        <ReportHeading title={t('balance_sheet.title')} subtitle={`${t('trial_balance.as_of')} ${formatDate(props.balanceSheet.as_of)}`} />
                        <CardContent className="grid gap-6 p-5 lg:grid-cols-2">
                            <StatementSection title={t('balance_sheet.assets')} rows={props.balanceSheet.assets} totalLabel={t('balance_sheet.total_assets')} total={props.balanceSheet.totals.assets} formatCurrency={formatCurrency} empty={t('common.empty')} />
                            <div className="space-y-6">
                                <StatementSection title={t('balance_sheet.liabilities')} rows={props.balanceSheet.liabilities} totalLabel={t('common.total')} total={props.balanceSheet.totals.liabilities} formatCurrency={formatCurrency} empty={t('common.empty')} />
                                <StatementSection title={t('balance_sheet.equity')} rows={props.balanceSheet.equity} totalLabel={t('common.total')} total={props.balanceSheet.totals.equity} formatCurrency={formatCurrency} empty={t('common.empty')} />
                                <div className="flex items-center justify-between border-t pt-3 text-sm">
                                    <span>{t('balance_sheet.current_earnings')}</span>
                                    <span className="tabular-nums">{formatCurrency(props.balanceSheet.current_earnings)}</span>
                                </div>
                            </div>
                            <div className="border-t-2 pt-4 lg:col-span-2">
                                <div className="flex items-center justify-between font-semibold">
                                    <span>{t('balance_sheet.total_liabilities_equity')}</span>
                                    <span className="tabular-nums">{formatCurrency(props.balanceSheet.totals.liabilities_and_equity)}</span>
                                </div>
                                <Badge className="mt-3" variant={isBalanced(props.balanceSheet.totals.assets, props.balanceSheet.totals.liabilities_and_equity) ? 'default' : 'destructive'}>
                                    {isBalanced(props.balanceSheet.totals.assets, props.balanceSheet.totals.liabilities_and_equity) ? t('balance_sheet.balanced') : `${t('balance_sheet.not_balanced')}: ${formatCurrency(props.balanceSheet.totals.difference)}`}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function ReportHeading({ title, subtitle }: { title: string; subtitle?: string }) {
    return (
        <div className="border-b bg-nx-n-50 px-5 py-4 dark:bg-card">
            <h2 className="font-display text-sm font-semibold tracking-tight">{title}</h2>
            {subtitle && <p className="mt-1 text-xs text-muted-foreground">{subtitle}</p>}
        </div>
    );
}

function EmptyRow({ colSpan, label }: { colSpan: number; label: string }) {
    return <TableRow><TableCell colSpan={colSpan} className="h-24 text-center text-muted-foreground">{label}</TableCell></TableRow>;
}

function SummaryCell({ label, value }: { label: string; value: string }) {
    return <div className="border-b p-4 last:border-b-0 sm:border-b-0 sm:border-r last:sm:border-r-0"><p className="text-xs text-muted-foreground">{label}</p><p className="mt-1 font-semibold tabular-nums">{value}</p></div>;
}

function StatementSection({
    title,
    rows,
    totalLabel,
    total,
    formatCurrency,
    empty,
}: {
    title: string;
    rows: AccountAmountRow[];
    totalLabel: string;
    total: number;
    formatCurrency: (value: number) => string;
    empty: string;
}) {
    return (
        <section className="border p-4">
            <h3 className="mb-3 text-sm font-semibold">{title}</h3>
            <div className="space-y-2">
                {rows.length === 0 ? <p className="text-sm text-muted-foreground">{empty}</p> : rows.map((row) => (
                    <div key={row.id} className="flex items-start justify-between gap-4 text-sm">
                        <span><span className="mr-2 font-mono text-xs text-muted-foreground">{row.code}</span>{row.name}</span>
                        <span className="shrink-0 tabular-nums">{formatCurrency(row.amount)}</span>
                    </div>
                ))}
            </div>
            <div className="mt-4 flex items-center justify-between border-t pt-3 text-sm font-semibold">
                <span>{totalLabel}</span>
                <span className="tabular-nums">{formatCurrency(total)}</span>
            </div>
        </section>
    );
}

FinancialReportsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default FinancialReportsPage;
