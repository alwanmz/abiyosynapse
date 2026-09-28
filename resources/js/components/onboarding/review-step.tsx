import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link, router, usePage } from '@inertiajs/react';
import { IconAlertTriangle, IconArrowLeft, IconPlus, IconSearch, IconSparkles, IconTrash } from '@tabler/icons-react';
import { useMemo, useState } from 'react';
import { type AccountType, type DraftAccount, type OnboardingDraft, type ReportLineOption, TYPE_LABELS, collectErrors } from './types';

const NONE = '__none';

const SOURCE_LABELS: Record<OnboardingDraft['source'], string> = {
    ai: 'Disusun AI',
    upload: 'Dari file upload',
    standard: 'COA standar',
};

interface ReviewStepProps {
    draft: OnboardingDraft;
    reportLineOptions: ReportLineOption[];
}

export function ReviewStep({ draft, reportLineOptions }: ReviewStepProps) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [accounts, setAccounts] = useState<DraftAccount[]>(draft.accounts);
    const [search, setSearch] = useState('');
    const [processing, setProcessing] = useState(false);
    const accountErrors = collectErrors(errors, 'accounts');

    const depthByCode = useMemo(() => {
        const byCode = new Map(accounts.map((account) => [account.code, account]));
        const depth = new Map<string, number>();
        accounts.forEach((account) => {
            let level = 0;
            let cursor = account;
            while (cursor.parent_code && byCode.has(cursor.parent_code) && level < 10) {
                cursor = byCode.get(cursor.parent_code)!;
                level++;
            }
            depth.set(account.code, level);
        });
        return depth;
    }, [accounts]);

    const visible = accounts
        .map((account, index) => ({ account, index }))
        .filter(({ account }) => {
            const query = search.trim().toLowerCase();
            return !query || account.code.toLowerCase().includes(query) || account.name.toLowerCase().includes(query);
        });

    const update = (index: number, patch: Partial<DraftAccount>) => {
        setAccounts((current) =>
            current.map((account, i) => {
                if (i !== index) return account;
                const next = { ...account, ...patch };
                if (patch.type && patch.type !== account.type) {
                    next.normal_balance = patch.type === 'asset' || patch.type === 'expense' ? 'debit' : 'credit';
                    next.report_line = null;
                    next.parent_code = null;
                }
                if (patch.is_postable === false) next.report_line = null;
                return next;
            }),
        );
    };

    const remove = (index: number) => {
        const code = accounts[index].code;
        setAccounts((current) =>
            current.filter((_, i) => i !== index).map((account) => (account.parent_code === code ? { ...account, parent_code: null } : account)),
        );
    };

    const add = () => {
        setAccounts((current) => [
            ...current,
            { code: '', name: '', type: 'expense', normal_balance: 'debit', is_postable: true, parent_code: null, report_line: null },
        ]);
        setSearch('');
    };

    const submit = () => {
        setProcessing(true);
        router.put('/onboarding/draft', { accounts: accounts as unknown as Record<string, string>[] }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-2">
                    <Badge variant={draft.source === 'ai' ? 'default' : 'secondary'}>
                        {draft.source === 'ai' && <IconSparkles className="size-3.5" />}
                        {SOURCE_LABELS[draft.source]}
                    </Badge>
                    <span className="text-sm text-muted-foreground">{accounts.length} akun</span>
                </div>
                <div className="relative sm:w-72">
                    <IconSearch className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input className="pl-9" placeholder="Cari kode atau nama akun" value={search} onChange={(e) => setSearch(e.target.value)} />
                </div>
            </div>

            {draft.notice && (
                <div className="flex items-start gap-2 rounded-lg border border-nx-andon-caution/40 bg-nx-andon-caution-bg p-3 text-sm">
                    <IconAlertTriangle className="mt-0.5 size-4 shrink-0 text-nx-andon-caution" />
                    {draft.notice}
                </div>
            )}

            {accountErrors.length > 0 && (
                <div className="rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
                    <p className="mb-2 font-medium">Perbaiki dulu:</p>
                    <ul className="list-disc space-y-1 pl-5">
                        {accountErrors.map((message) => (
                            <li key={message}>{message}</li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="max-h-[60vh] overflow-auto rounded-lg border">
                <Table>
                    <TableHeader className="sticky top-0 z-10 bg-background">
                        <TableRow>
                            <TableHead className="w-32">Kode</TableHead>
                            <TableHead className="min-w-56">Nama akun</TableHead>
                            <TableHead className="w-36">Tipe</TableHead>
                            <TableHead className="w-28">Saldo</TableHead>
                            <TableHead className="w-20 text-center">Header</TableHead>
                            <TableHead className="w-44">Induk</TableHead>
                            <TableHead className="w-52">Pos laporan</TableHead>
                            <TableHead className="w-10" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {visible.map(({ account, index }) => {
                            const parents = accounts.filter((a) => !a.is_postable && a.type === account.type && a.code !== account.code && a.code);
                            const lines = reportLineOptions.filter((line) =>
                                ['revenue', 'expense'].includes(account.type) ? line.report === 'profit_loss' : line.report === 'balance_sheet',
                            );
                            return (
                                <TableRow key={index}>
                                    <TableCell>
                                        <Input className="h-8 font-mono text-xs" value={account.code} onChange={(e) => update(index, { code: e.target.value.trim() })} />
                                    </TableCell>
                                    <TableCell>
                                        <Input
                                            className={account.is_postable ? 'h-8' : 'h-8 font-semibold'}
                                            style={{ marginLeft: `${(depthByCode.get(account.code) ?? 0) * 0.75}rem` }}
                                            value={account.name}
                                            onChange={(e) => update(index, { name: e.target.value })}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <Select value={account.type} onValueChange={(value) => update(index, { type: value as AccountType })}>
                                            <SelectTrigger className="h-8">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(TYPE_LABELS).map(([value, label]) => (
                                                    <SelectItem key={value} value={value}>
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </TableCell>
                                    <TableCell>
                                        <Select
                                            value={account.normal_balance}
                                            onValueChange={(value) => update(index, { normal_balance: value as DraftAccount['normal_balance'] })}
                                        >
                                            <SelectTrigger className="h-8">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="debit">Debit</SelectItem>
                                                <SelectItem value="credit">Kredit</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <Checkbox checked={!account.is_postable} onCheckedChange={(checked) => update(index, { is_postable: checked !== true })} />
                                    </TableCell>
                                    <TableCell>
                                        <Select value={account.parent_code ?? NONE} onValueChange={(value) => update(index, { parent_code: value === NONE ? null : value })}>
                                            <SelectTrigger className="h-8">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent className="max-h-72">
                                                <SelectItem value={NONE}>— Level teratas —</SelectItem>
                                                {parents.map((parent) => (
                                                    <SelectItem key={parent.code} value={parent.code}>
                                                        {parent.code} {parent.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </TableCell>
                                    <TableCell>
                                        {account.is_postable ? (
                                            <Select value={account.report_line ?? NONE} onValueChange={(value) => update(index, { report_line: value === NONE ? null : value })}>
                                                <SelectTrigger className="h-8">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent className="max-h-72">
                                                    <SelectItem value={NONE}>Otomatis (lainnya)</SelectItem>
                                                    {lines.map((line) => (
                                                        <SelectItem key={line.value} value={line.value}>
                                                            {line.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">Akun header</span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Button variant="ghost" size="icon" className="size-8 text-destructive hover:text-destructive" onClick={() => remove(index)}>
                                            <IconTrash className="size-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex gap-2">
                    <Button variant="ghost" asChild>
                        <Link href="/onboarding?step=method">
                            <IconArrowLeft />
                            Ganti metode
                        </Link>
                    </Button>
                    <Button variant="outline" onClick={add}>
                        <IconPlus />
                        Tambah akun
                    </Button>
                </div>
                <Button onClick={submit} disabled={processing || accounts.length === 0}>
                    {processing && <Spinner />}
                    Lanjut ke mapping akun inti
                </Button>
            </div>
        </div>
    );
}
