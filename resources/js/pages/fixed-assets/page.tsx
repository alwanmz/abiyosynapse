import { ConfirmDialog } from '@/components/confirm-dialog';
import { ListHeader } from '@/components/list-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Head, useForm, usePage } from '@inertiajs/react';
import { IconCalculator, IconEye, IconPlus, IconPlayerPlay, IconTrash } from '@tabler/icons-react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface Account {
    id: number;
    code: string;
    name: string;
    type: string;
}

interface Depreciation {
    id: number;
    period_date: string;
    amount: string;
    accumulated_depreciation: string;
    journal_entry: { id: number; number: string } | null;
}

interface FixedAsset {
    id: number;
    number: string;
    name: string;
    category: string | null;
    acquisition_date: string;
    currency_code: string;
    placed_in_service_date: string | null;
    acquisition_cost: string;
    salvage_value: string;
    useful_life_months: number;
    depreciation_method: 'straight_line';
    accumulated_depreciation: string;
    status: 'draft' | 'active' | 'fully_depreciated' | 'disposed';
    disposal_proceeds: string | null;
    disposal_gain_loss: string | null;
    asset_account: Account;
    accumulated_depreciation_account: Account;
    depreciation_expense_account: Account;
    source_account: Account;
    depreciations: Depreciation[];
}

interface PageProps {
    fixedAssets: FixedAsset[];
    accounts: Account[];
    currencies: { currency_code: string; currency: { code: string; name: string } }[];
}

type AssetForm = {
    name: string;
    category: string;
    asset_account_id: string;
    accumulated_depreciation_account_id: string;
    depreciation_expense_account_id: string;
    source_account_id: string;
    acquisition_date: string;
    currency_code: string;
    placed_in_service_date: string;
    acquisition_cost: string;
    salvage_value: string;
    useful_life_months: string;
    depreciation_method: 'straight_line';
    notes: string;
};

const STATUS_VARIANT: Record<FixedAsset['status'], 'default' | 'outline' | 'secondary'> = {
    draft: 'outline',
    active: 'default',
    fully_depreciated: 'secondary',
    disposed: 'outline',
};

function FixedAssetsPage({ fixedAssets, accounts, currencies }: PageProps) {
    const { t } = useTranslation('fixed-assets');
    const { locale, currentCompany } = usePage().props as { locale?: string; currentCompany?: { currency: string } | null };
    const [createOpen, setCreateOpen] = useState(false);
    const [detailAsset, setDetailAsset] = useState<FixedAsset | null>(null);
    const [activateAsset, setActivateAsset] = useState<FixedAsset | null>(null);
    const [depreciateAsset, setDepreciateAsset] = useState<FixedAsset | null>(null);
    const [disposeAsset, setDisposeAsset] = useState<FixedAsset | null>(null);

    useBreadcrumbs([
        { title: t('nav.fixed_assets'), href: '#' },
        { title: t('asset.breadcrumb'), href: '/fixed-assets' },
    ]);

    const formatDate = (value: string | null) =>
        value
            ? new Date(value).toLocaleDateString(locale ?? 'id-ID', {
                  year: 'numeric',
                  month: 'short',
                  day: 'numeric',
              })
            : '—';

    const formatCurrency = (value: string | number, currency = currentCompany?.currency ?? 'IDR') =>
        new Intl.NumberFormat(locale ?? 'id-ID', {
            style: 'currency',
            currency,
            maximumFractionDigits: 2,
        }).format(Number(value));

    const emptyForm = (): AssetForm => ({
        name: '',
        category: '',
        asset_account_id: '',
        accumulated_depreciation_account_id: '',
        depreciation_expense_account_id: '',
        source_account_id: '',
        acquisition_date: new Date().toISOString().slice(0, 10),
        currency_code: currentCompany?.currency ?? 'IDR',
        placed_in_service_date: '',
        acquisition_cost: '',
        salvage_value: '0',
        useful_life_months: '36',
        depreciation_method: 'straight_line',
        notes: '',
    });

    const { data, setData, post: createAsset, processing, errors, reset, clearErrors } = useForm<AssetForm>(emptyForm());
    const activateForm = useForm({});
    const depreciationForm = useForm({ period_date: new Date().toISOString().slice(0, 7) });
    const disposalForm = useForm({ disposal_date: new Date().toISOString().slice(0, 10), proceeds: '0', proceeds_account_id: '' });

    const openCreate = () => {
        reset();
        setData(emptyForm());
        setCreateOpen(true);
    };

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        createAsset('/fixed-assets', {
            preserveScroll: true,
            onSuccess: () => {
                setCreateOpen(false);
                reset();
            },
        });
    };

    const submitDepreciation = (event: React.FormEvent) => {
        event.preventDefault();
        if (!depreciateAsset) return;
        depreciationForm.post(`/fixed-assets/${depreciateAsset.id}/depreciate`, {
            preserveScroll: true,
            onSuccess: () => {
                setDepreciateAsset(null);
                depreciationForm.reset();
            },
        });
    };

    const submitDisposal = (event: React.FormEvent) => {
        event.preventDefault();
        if (!disposeAsset) return;
        disposalForm.post(`/fixed-assets/${disposeAsset.id}/dispose`, {
            preserveScroll: true,
            onSuccess: () => {
                setDisposeAsset(null);
                disposalForm.reset();
            },
        });
    };

    return (
        <>
            <Head title={t('asset.title')} />

            <div className="p-6">
                <div className="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">{t('asset.title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('asset.description')}</p>
                    </div>
                    <Button onClick={openCreate} className="shrink-0">
                        <IconPlus className="size-4" />
                        {t('asset.add')}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title={t('asset.list_title')} />
                    <CardContent className="p-5">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{t('asset.table.number')}</TableHead>
                                        <TableHead>{t('asset.table.name')}</TableHead>
                                        <TableHead>{t('asset.table.category')}</TableHead>
                                        <TableHead className="text-right">{t('asset.table.cost')}</TableHead>
                                        <TableHead className="text-right">{t('asset.table.book_value')}</TableHead>
                                        <TableHead>{t('asset.table.status')}</TableHead>
                                        <TableHead className="text-right">{t('asset.table.actions')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {fixedAssets.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                                {t('asset.empty')}
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        fixedAssets.map((asset) => (
                                            <TableRow key={asset.id}>
                                                <TableCell className="font-mono text-sm">{asset.number}</TableCell>
                                                <TableCell>
                                                    <div className="font-medium">{asset.name}</div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {formatDate(asset.acquisition_date)}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-sm text-muted-foreground">{asset.category || '—'}</TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatCurrency(asset.acquisition_cost, asset.currency_code)}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatCurrency(Number(asset.acquisition_cost) - Number(asset.accumulated_depreciation), asset.currency_code)}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={STATUS_VARIANT[asset.status]}>
                                                        {t(`asset.status_${asset.status}`)}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button variant="ghost" size="icon" onClick={() => setDetailAsset(asset)} title={t('asset.details')}>
                                                            <IconEye className="size-4" />
                                                        </Button>
                                                        {asset.status === 'draft' && (
                                                            <Button variant="default" size="sm" onClick={() => setActivateAsset(asset)}>
                                                                <IconPlayerPlay className="size-4" />
                                                                {t('asset.activate')}
                                                            </Button>
                                                        )}
                                                        {asset.status === 'active' && (
                                                            <Button variant="secondary" size="sm" onClick={() => setDepreciateAsset(asset)}>
                                                                <IconCalculator className="size-4" />
                                                                {t('asset.depreciate')}
                                                            </Button>
                                                        )}
                                                        {(asset.status === 'active' || asset.status === 'fully_depreciated') && (
                                                            <Button variant="ghost" size="icon" onClick={() => setDisposeAsset(asset)} title={t('asset.dispose')}>
                                                                <IconTrash className="size-4 text-destructive" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="flex max-h-[90vh] max-w-3xl flex-col overflow-hidden">
                    <DialogHeader>
                        <DialogTitle>{t('asset.add_title')}</DialogTitle>
                        <DialogDescription>{t('asset.description')}</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitCreate} className="flex-1 space-y-4 overflow-y-auto pr-2">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="asset_name">{t('common.name')}</Label>
                                <Input id="asset_name" value={data.name} onChange={(event) => setData('name', event.target.value)} />
                                {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="asset_category">{t('asset.category')}</Label>
                                <Input id="asset_category" placeholder={t('asset.category_placeholder')} value={data.category} onChange={(event) => setData('category', event.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="acquisition_date">{t('asset.acquisition_date')}</Label>
                                <Input id="acquisition_date" type="date" value={data.acquisition_date} onChange={(event) => setData('acquisition_date', event.target.value)} />
                                {errors.acquisition_date && <p className="text-sm text-destructive">{errors.acquisition_date}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="placed_in_service_date">{t('asset.placed_in_service_date')}</Label>
                                <Input id="placed_in_service_date" type="date" value={data.placed_in_service_date} onChange={(event) => setData('placed_in_service_date', event.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="acquisition_cost">{t('asset.acquisition_cost')}</Label>
                                <Input id="acquisition_cost" type="number" min="0.01" step="0.01" value={data.acquisition_cost} onChange={(event) => setData('acquisition_cost', event.target.value)} />
                                {errors.acquisition_cost && <p className="text-sm text-destructive">{errors.acquisition_cost}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>{t('currency.title')}</Label>
                                <Select value={data.currency_code} onValueChange={(value) => { setData('currency_code', value); clearErrors('currency_code'); }}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>{currencies.map((currency) => <SelectItem key={currency.currency_code} value={currency.currency_code}>{currency.currency_code} — {currency.currency.name}</SelectItem>)}</SelectContent>
                                </Select>
                                {errors.currency_code && <p className="text-sm text-destructive">{errors.currency_code}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="salvage_value">{t('asset.salvage_value')}</Label>
                                <Input id="salvage_value" type="number" min="0" step="0.01" value={data.salvage_value} onChange={(event) => setData('salvage_value', event.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="useful_life_months">{t('asset.useful_life_months')}</Label>
                                <Input id="useful_life_months" type="number" min="1" step="1" value={data.useful_life_months} onChange={(event) => setData('useful_life_months', event.target.value)} />
                                {errors.useful_life_months && <p className="text-sm text-destructive">{errors.useful_life_months}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>{t('asset.method')}</Label>
                                <Select value={data.depreciation_method} onValueChange={(value) => { setData('depreciation_method', value as 'straight_line'); clearErrors('depreciation_method'); }}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent><SelectItem value="straight_line">{t('asset.method_straight_line')}</SelectItem></SelectContent>
                                </Select>
                            </div>
                            <AccountSelect label={t('asset.asset_account')} value={data.asset_account_id} accounts={accounts} error={errors.asset_account_id} onChange={(value) => { setData('asset_account_id', value); clearErrors('asset_account_id'); }} />
                            <AccountSelect label={t('asset.accumulated_account')} value={data.accumulated_depreciation_account_id} accounts={accounts} error={errors.accumulated_depreciation_account_id} onChange={(value) => { setData('accumulated_depreciation_account_id', value); clearErrors('accumulated_depreciation_account_id'); }} />
                            <AccountSelect label={t('asset.expense_account')} value={data.depreciation_expense_account_id} accounts={accounts} error={errors.depreciation_expense_account_id} onChange={(value) => { setData('depreciation_expense_account_id', value); clearErrors('depreciation_expense_account_id'); }} />
                            <AccountSelect label={t('asset.source_account')} value={data.source_account_id} accounts={accounts} error={errors.source_account_id} onChange={(value) => { setData('source_account_id', value); clearErrors('source_account_id'); }} />
                            <div className="grid gap-2 md:col-span-2">
                                <Label htmlFor="asset_notes">{t('asset.notes')}</Label>
                                <Input id="asset_notes" placeholder={t('asset.notes_placeholder')} value={data.notes} onChange={(event) => setData('notes', event.target.value)} />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="cancel" onClick={() => setCreateOpen(false)} disabled={processing}>{t('common.cancel')}</Button>
                            <Button type="submit" variant="save" disabled={processing}>{processing ? t('common.saving') : t('common.save')}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={!!detailAsset} onOpenChange={(open) => !open && setDetailAsset(null)}>
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{detailAsset?.number} · {t('asset.details')}</DialogTitle>
                        <DialogDescription>{detailAsset?.name}</DialogDescription>
                    </DialogHeader>
                    {detailAsset && (
                        <div className="space-y-5">
                            <div className="grid gap-4 rounded-lg border p-4 sm:grid-cols-3">
                                <Metric label={t('asset.table.cost')} value={formatCurrency(detailAsset.acquisition_cost, detailAsset.currency_code)} />
                                <Metric label={t('asset.table.book_value')} value={formatCurrency(Number(detailAsset.acquisition_cost) - Number(detailAsset.accumulated_depreciation), detailAsset.currency_code)} />
                                <Metric label={t('asset.status_active')} value={formatDate(detailAsset.placed_in_service_date)} />
                            </div>
                            <div>
                                <h3 className="mb-3 font-medium">{t('asset.schedule_title')}</h3>
                                {detailAsset.depreciations.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">{t('asset.schedule_empty')}</p>
                                ) : (
                                    <Table>
                                        <TableHeader><TableRow><TableHead>{t('asset.schedule_table.period')}</TableHead><TableHead className="text-right">{t('asset.schedule_table.amount')}</TableHead><TableHead className="text-right">{t('asset.schedule_table.accumulated')}</TableHead><TableHead>{t('asset.schedule_table.journal')}</TableHead></TableRow></TableHeader>
                                        <TableBody>{detailAsset.depreciations.map((row) => <TableRow key={row.id}><TableCell>{formatDate(row.period_date)}</TableCell><TableCell className="text-right tabular-nums">{formatCurrency(row.amount, detailAsset.currency_code)}</TableCell><TableCell className="text-right tabular-nums">{formatCurrency(row.accumulated_depreciation, detailAsset.currency_code)}</TableCell><TableCell className="font-mono text-xs">{row.journal_entry?.number || '—'}</TableCell></TableRow>)}</TableBody>
                                    </Table>
                                )}
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <ConfirmDialog open={!!activateAsset} onOpenChange={(open) => !open && setActivateAsset(null)} title={t('asset.activate_confirm_title')} description={t('asset.activate_confirm_description')} confirmLabel={t('asset.activate')} variant="default" loading={activateForm.processing} onConfirm={() => activateAsset && activateForm.post(`/fixed-assets/${activateAsset.id}/activate`, { preserveScroll: true, onSuccess: () => setActivateAsset(null) })} />

            <Dialog open={!!depreciateAsset} onOpenChange={(open) => !open && setDepreciateAsset(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>{t('asset.depreciate')}</DialogTitle><DialogDescription>{depreciateAsset?.number} · {depreciateAsset?.name}</DialogDescription></DialogHeader>
                    <form onSubmit={submitDepreciation} className="space-y-4">
                        <div className="grid gap-2"><Label htmlFor="period_date">{t('asset.period')}</Label><Input id="period_date" type="month" value={depreciationForm.data.period_date} onChange={(event) => depreciationForm.setData('period_date', event.target.value)} />{depreciationForm.errors.period_date && <p className="text-sm text-destructive">{depreciationForm.errors.period_date}</p>}</div>
                        <DialogFooter><Button type="button" variant="cancel" onClick={() => setDepreciateAsset(null)}>{t('common.cancel')}</Button><Button type="submit" disabled={depreciationForm.processing}>{depreciationForm.processing ? t('common.saving') : t('common.post')}</Button></DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={!!disposeAsset} onOpenChange={(open) => !open && setDisposeAsset(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>{t('asset.dispose')}</DialogTitle><DialogDescription>{disposeAsset?.number} · {t('asset.dispose_confirm_description')}</DialogDescription></DialogHeader>
                    <form onSubmit={submitDisposal} className="space-y-4">
                        <div className="grid gap-2"><Label htmlFor="disposal_date">{t('asset.disposal_date')}</Label><Input id="disposal_date" type="date" value={disposalForm.data.disposal_date} onChange={(event) => disposalForm.setData('disposal_date', event.target.value)} /></div>
                        <div className="grid gap-2"><Label htmlFor="proceeds">{t('asset.proceeds')}</Label><Input id="proceeds" type="number" min="0" step="0.01" value={disposalForm.data.proceeds} onChange={(event) => disposalForm.setData('proceeds', event.target.value)} /></div>
                        <AccountSelect label={t('asset.proceeds_account')} value={disposalForm.data.proceeds_account_id} accounts={accounts} error={disposalForm.errors.proceeds_account_id} onChange={(value) => { disposalForm.setData('proceeds_account_id', value); disposalForm.clearErrors('proceeds_account_id'); }} />
                        <DialogFooter><Button type="button" variant="cancel" onClick={() => setDisposeAsset(null)}>{t('common.cancel')}</Button><Button type="submit" variant="destructive" disabled={disposalForm.processing}>{disposalForm.processing ? t('common.saving') : t('asset.dispose')}</Button></DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function AccountSelect({ label, value, accounts, error, onChange }: { label: string; value: string; accounts: Account[]; error?: string; onChange: (value: string) => void }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger><SelectValue placeholder="—" /></SelectTrigger>
                <SelectContent className="max-h-72">{accounts.map((account) => <SelectItem key={account.id} value={account.id.toString()}>{account.code} — {account.name}</SelectItem>)}</SelectContent>
            </Select>
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return <div><div className="text-xs text-muted-foreground">{label}</div><div className="mt-1 font-medium tabular-nums">{value}</div></div>;
}

FixedAssetsPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default FixedAssetsPage;
