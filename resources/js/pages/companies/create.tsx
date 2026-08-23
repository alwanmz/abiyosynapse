import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

function CreateCompanyPage() {
    const { t } = useTranslation('companies');
    const months = t('months', { returnObjects: true }) as string[];

    useBreadcrumbs([
        {
            title: t('form.create_breadcrumb'),
            href: companies.create().url,
        },
    ]);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        legal_name: '',
        tax_id: '',
        address: '',
        currency: 'IDR',
        fiscal_year_start_month: 1,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(companies.store().url);
    };

    return (
        <>
            <Head title={t('form.create_head_title')} />

            <div className="mx-auto max-w-2xl p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">{t('form.create_title')}</h1>
                    <p className="text-sm text-muted-foreground">
                        {t('form.create_description')}
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
                                    placeholder={t('form.name_placeholder')}
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
                                    placeholder={t('form.legal_name_placeholder')}
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
                                    placeholder={t('form.tax_id_placeholder')}
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
                                    placeholder={t('form.address_placeholder')}
                                />
                                {errors.address && (
                                    <p className="text-sm text-destructive">{errors.address}</p>
                                )}
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="currency">{t('form.currency')}</Label>
                                    <Select value={data.currency} onValueChange={(value) => setData('currency', value)}>
                                        <SelectTrigger id="currency"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="IDR">IDR - Rupiah</SelectItem>
                                            <SelectItem value="USD">USD - US Dollar</SelectItem>
                                            <SelectItem value="JPY">JPY - Japanese Yen</SelectItem>
                                            <SelectItem value="CNY">CNY - Chinese Yuan</SelectItem>
                                        </SelectContent>
                                    </Select>
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

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? t('form.create_submitting') : t('form.create_submit')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CreateCompanyPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default CreateCompanyPage;
