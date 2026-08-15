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
import companies from '@/routes/companies';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Perusahaan',
        href: companies.index().url,
    },
    {
        title: 'Edit',
        href: '#',
    },
];

const MONTHS = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

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

export default function EditCompanyPage({ company }: EditCompanyPageProps) {
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
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit Perusahaan" />

            <div className="mx-auto max-w-2xl p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Edit Perusahaan</h1>
                    <p className="text-sm text-muted-foreground">
                        Perbarui informasi perusahaan.
                    </p>
                </div>

                <Card>
                    <CardContent className="p-6">
                        <form onSubmit={handleSubmit} className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama Perusahaan</Label>
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
                                <Label htmlFor="legal_name">Nama Badan Hukum</Label>
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
                                <Label htmlFor="tax_id">NPWP</Label>
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
                                <Label htmlFor="address">Alamat</Label>
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
                                    <Label htmlFor="currency">Mata Uang</Label>
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
                                    <Label htmlFor="fiscal_year_start_month">Awal Tahun Fiskal</Label>
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
                                            {MONTHS.map((month, index) => (
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
                                    Perusahaan aktif
                                </Label>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
