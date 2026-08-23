import { ConfirmDialog } from '@/components/confirm-dialog';
import { ListHeader } from '@/components/list-header';
import { PrintDocumentButton } from '@/components/print-document-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { type ReactElement, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface CashTransaction {
    id: number;
    number: string;
    type: 'in' | 'out';
    transaction_date: string;
    amount: string;
    description: string;
}

interface Line {
    id: number;
    is_cleared: boolean;
    cash_transaction: CashTransaction;
}

interface Reconciliation {
    id: number;
    number: string;
    statement_date: string;
    statement_balance: string;
    status: 'draft' | 'completed';
    completed_at: string | null;
    bank_account: { id: number; code: string; name: string };
    lines: Line[];
}

interface PageProps {
    reconciliation: Reconciliation;
}

function BankReconciliationShowPage({ reconciliation }: PageProps) {
    const { t } = useTranslation('cash-bank');
    const { locale } = usePage().props as { locale?: string };
    const isDraft = reconciliation.status === 'draft';

    const [cleared, setCleared] = useState<Record<number, boolean>>(
        Object.fromEntries(reconciliation.lines.map((line) => [line.id, line.is_cleared])),
    );
    const [completeDialogOpen, setCompleteDialogOpen] = useState(false);

    useBreadcrumbs([
        { title: t('nav.cash_bank'), href: '#' },
        { title: t('bank_reconciliation.breadcrumb'), href: '/cash-bank/bank-reconciliations' },
        { title: reconciliation.number, href: '#' },
    ]);

    const formatDate = (value: string) =>
        new Date(value).toLocaleDateString(locale ?? 'id-ID', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

    const { processing: saving } = useForm({});
    const { processing: completing } = useForm({});

    const handleSave = () => {
        router.put(
            `/cash-bank/bank-reconciliations/${reconciliation.id}/lines`,
            {
                lines: reconciliation.lines.map((line) => ({
                    id: line.id,
                    is_cleared: cleared[line.id] ?? false,
                })),
            },
            { preserveScroll: true },
        );
    };

    const handleComplete = () => {
        router.post(
            `/cash-bank/bank-reconciliations/${reconciliation.id}/complete`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setCompleteDialogOpen(false),
            },
        );
    };

    const { clearedCount, clearedTotal, grandTotal } = useMemo(() => {
        let count = 0;
        let total = 0;
        let grand = 0;
        for (const line of reconciliation.lines) {
            const amount = parseFloat(line.cash_transaction.amount);
            grand += amount;
            if (cleared[line.id]) {
                count += 1;
                total += amount;
            }
        }
        return { clearedCount: count, clearedTotal: total, grandTotal: grand };
    }, [reconciliation.lines, cleared]);

    return (
        <>
            <Head title={t('bank_reconciliation.detail_title')} />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">{reconciliation.number}</h1>
                            <Badge variant={reconciliation.status === 'completed' ? 'default' : 'outline'}>
                                {t(`bank_reconciliation.status_${reconciliation.status}`)}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {reconciliation.bank_account.code} — {reconciliation.bank_account.name} ·{' '}
                            {formatDate(reconciliation.statement_date)} · {t('bank_reconciliation.statement_balance')}:{' '}
                            <span className="tabular-nums">{reconciliation.statement_balance}</span>
                            {reconciliation.completed_at &&
                                ` · ${t('bank_reconciliation.completed_at')}: ${formatDate(reconciliation.completed_at)}`}
                        </p>
                    </div>

                    {isDraft && (
                        <div className="flex items-center gap-2">
                            <PrintDocumentButton type="bank_reconciliation" documentId={reconciliation.id} />
                            <Button variant="save" onClick={handleSave} disabled={saving}>
                                {t('bank_reconciliation.save')}
                            </Button>
                            <Button onClick={() => setCompleteDialogOpen(true)} disabled={completing}>
                                {t('bank_reconciliation.complete')}
                            </Button>
                        </div>
                    )}
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('bank_reconciliation.detail_title')} />
                    <CardContent className="space-y-4 p-5">
                        <p className="text-sm text-muted-foreground">
                            {clearedCount} {t('bank_reconciliation.progress_of')} {reconciliation.lines.length}{' '}
                            {t('bank_reconciliation.progress_cleared')} ·{' '}
                            <span className="tabular-nums">{clearedTotal.toFixed(2)}</span>{' '}
                            {t('bank_reconciliation.progress_of')}{' '}
                            <span className="tabular-nums">{grandTotal.toFixed(2)}</span>
                        </p>

                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('bank_reconciliation.lines_table.number')}</TableHead>
                                    <TableHead>{t('bank_reconciliation.lines_table.date')}</TableHead>
                                    <TableHead>{t('bank_reconciliation.lines_table.type')}</TableHead>
                                    <TableHead>{t('bank_reconciliation.lines_table.description')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('bank_reconciliation.lines_table.amount')}
                                    </TableHead>
                                    <TableHead className="text-center">
                                        {t('bank_reconciliation.lines_table.cleared')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {reconciliation.lines.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell className="font-mono text-sm">
                                            {line.cash_transaction.number}
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {formatDate(line.cash_transaction.transaction_date)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={line.cash_transaction.type === 'in' ? 'default' : 'outline'}>
                                                {t(`cash_transaction.type_${line.cash_transaction.type}`)}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {line.cash_transaction.description}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {line.cash_transaction.amount}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <Checkbox
                                                checked={cleared[line.id] ?? false}
                                                disabled={!isDraft}
                                                onCheckedChange={(checked) =>
                                                    setCleared((prev) => ({ ...prev, [line.id]: checked === true }))
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <ConfirmDialog
                open={completeDialogOpen}
                onOpenChange={setCompleteDialogOpen}
                title={t('bank_reconciliation.complete_confirm_title')}
                description={t('bank_reconciliation.complete_confirm_description')}
                confirmLabel={t('bank_reconciliation.complete')}
                variant="default"
                loading={completing}
                onConfirm={handleComplete}
            />
        </>
    );
}

BankReconciliationShowPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default BankReconciliationShowPage;
