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
import companies from '@/routes/companies';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Buat Perusahaan',
        href: companies.create().url,
    },
];

const MONTHS = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

export default function CreateCompanyPage() {
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
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Buat Perusahaan" />

            <div className="mx-auto max-w-2xl p-6">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold">Buat Perusahaan Baru</h1>
                    <p className="text-sm text-muted-foreground">
                        Anda akan menjadi Super Admin untuk perusahaan ini.
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
                                    placeholder="Masukkan nama perusahaan"
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
                                    placeholder="Contoh: PT Contoh Sejahtera"
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
                                    placeholder="Opsional"
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
                                    placeholder="Opsional"
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

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Membuat...' : 'Buat Perusahaan'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
