import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import companies from '@/routes/companies';
import { Head, useForm } from '@inertiajs/react';
import { type ReactElement } from 'react';
import { useTranslation } from 'react-i18next';

interface Company {
    id: number;
    name: string;
    legal_name: string | null;
    tax_id: string | null;
    address: string | null;
    currency: string;
    fiscal_year_start_month: number;
    is_active: boolean;
}

interface EditCompanyPageProps {
    company: Company;
}

function EditCompanyPage({ company }: EditCompanyPageProps) {
    const { t } = useTranslation('companies');
    const months = t('months', { returnObjects: true }) as string[];

    useBreadcrumbs([
        {
            title: t('breadcrumb'),
            href: companies.index().url,
        },
        {
            title: t('form.edit_breadcrumb'),
            href: '#',
        },
    ]);

    const { data, setData, put, processing, errors } = useForm({
        name: company.name,
        legal_name: company.legal_name ?? '',
        tax_id: company.tax_id ?? '',
        address: company.address ?? '',
        currency: company.currency,
        fiscal_year_start_month: company.fiscal_year_start_month,
        is_active: company.is_active,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(companies.update(company.id).url);
    };

    return (
        <>
            <Head title={t('form.edit_head_title')} />

            <div className="mx-auto max-w-2xl p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">{t('form.edit_title')}</h1>
                    <p className="text-sm text-muted-foreground">
                        {t('form.edit_description')}
                    </p>
                </div>

                <Card>
                    <CardContent className="p-6">
                        <form onSubmit={handleSubmit} className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('form.name')}</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">{errors.name}</p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="legal_name">{t('form.legal_name')}</Label>
                                <Input
                                    id="legal_name"
                                    value={data.legal_name}
                                    onChange={(e) => setData('legal_name', e.target.value)}
                                />
                                {errors.legal_name && (
                                    <p className="text-sm text-destructive">{errors.legal_name}</p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="tax_id">{t('form.tax_id')}</Label>
                                <Input
                                    id="tax_id"
                                    value={data.tax_id}
                                    onChange={(e) => setData('tax_id', e.target.value)}
                                />
                                {errors.tax_id && (
                                    <p className="text-sm text-destructive">{errors.tax_id}</p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address">{t('form.address')}</Label>
                                <Textarea
                                    id="address"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                />
                                {errors.address && (
                                    <p className="text-sm text-destructive">{errors.address}</p>
                                )}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="currency">{t('form.currency')}</Label>
                                    <Input
                                        id="currency"
                                        value={data.currency}
                                        maxLength={3}
                                        onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                                    />
                                    {errors.currency && (
                                        <p className="text-sm text-destructive">{errors.currency}</p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="fiscal_year_start_month">{t('form.fiscal_year_start_month')}</Label>
                                    <Select
                                        value={data.fiscal_year_start_month.toString()}
                                        onValueChange={(value) =>
                                            setData('fiscal_year_start_month', parseInt(value))
                                        }
                                    >
                                        <SelectTrigger id="fiscal_year_start_month">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {months.map((month, index) => (
                                                <SelectItem key={month} value={(index + 1).toString()}>
                                                    {month}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.fiscal_year_start_month && (
                                        <p className="text-sm text-destructive">
                                            {errors.fiscal_year_start_month}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked === true)}
                                />
                                <Label htmlFor="is_active" className="font-normal">
                                    {t('form.is_active')}
                                </Label>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? t('form.edit_submitting') : t('form.edit_submit')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

EditCompanyPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default EditCompanyPage;
