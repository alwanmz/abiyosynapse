import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { IconBuildingFactory2, IconBuildingStore, IconBriefcase, IconStack2 } from '@tabler/icons-react';
import { type FormEvent, useState } from 'react';

interface ProfileStepProps {
    company: {
        industry: string | null;
        business_type: string | null;
        business_scale: string | null;
        business_description: string | null;
        uses_inventory: boolean;
        is_pkp: boolean;
    };
    industries: string[];
    scales: string[];
}

const BUSINESS_TYPES = [
    { value: 'jasa', label: 'Jasa', hint: 'Menjual layanan, mis. konsultan, klinik, agensi', icon: IconBriefcase },
    { value: 'dagang', label: 'Dagang', hint: 'Membeli lalu menjual kembali barang', icon: IconBuildingStore },
    { value: 'manufaktur', label: 'Manufaktur', hint: 'Mengolah bahan baku menjadi produk', icon: IconBuildingFactory2 },
    { value: 'campuran', label: 'Campuran', hint: 'Kombinasi jasa, dagang, atau produksi', icon: IconStack2 },
];

const SCALE_LABELS: Record<string, string> = {
    mikro: 'Mikro (omzet < Rp2 M/tahun)',
    kecil: 'Kecil (Rp2–15 M/tahun)',
    menengah: 'Menengah (Rp15–50 M/tahun)',
    besar: 'Besar (> Rp50 M/tahun)',
};

export function ProfileStep({ company, industries, scales }: ProfileStepProps) {
    const knownIndustry = company.industry === null || industries.includes(company.industry);
    const [industryChoice, setIndustryChoice] = useState(knownIndustry ? (company.industry ?? '') : 'Lainnya');

    const { data, setData, post, processing, errors } = useForm({
        industry: company.industry ?? '',
        business_type: company.business_type ?? '',
        business_scale: company.business_scale ?? 'kecil',
        business_description: company.business_description ?? '',
        uses_inventory: company.uses_inventory,
        is_pkp: company.is_pkp,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/onboarding/profile', { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-6 md:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="industry">Industri</Label>
                    <Select
                        value={industryChoice}
                        onValueChange={(value) => {
                            setIndustryChoice(value);
                            setData('industry', value === 'Lainnya' ? '' : value);
                        }}
                    >
                        <SelectTrigger id="industry">
                            <SelectValue placeholder="Pilih industri usaha Anda" />
                        </SelectTrigger>
                        <SelectContent className="max-h-72">
                            {industries.map((industry) => (
                                <SelectItem key={industry} value={industry}>
                                    {industry}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {industryChoice === 'Lainnya' && (
                        <Input
                            placeholder="Tulis industri Anda, mis. Jasa Laundry"
                            value={data.industry}
                            onChange={(event) => setData('industry', event.target.value)}
                        />
                    )}
                    <InputError message={errors.industry} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="business_scale">Skala usaha</Label>
                    <Select value={data.business_scale} onValueChange={(value) => setData('business_scale', value)}>
                        <SelectTrigger id="business_scale">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {scales.map((scale) => (
                                <SelectItem key={scale} value={scale}>
                                    {SCALE_LABELS[scale] ?? scale}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.business_scale} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label>Jenis usaha</Label>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {BUSINESS_TYPES.map((type) => {
                        const Icon = type.icon;
                        const selected = data.business_type === type.value;
                        return (
                            <button
                                key={type.value}
                                type="button"
                                onClick={() => {
                                    setData((current) => ({
                                        ...current,
                                        business_type: type.value,
                                        uses_inventory: type.value === 'jasa' ? false : current.uses_inventory || current.business_type === 'jasa',
                                    }));
                                }}
                                className={cn(
                                    'flex flex-col items-start gap-2 rounded-lg border p-4 text-left transition-colors',
                                    selected
                                        ? 'border-nx-cyan-500 bg-nx-cyan-50 ring-1 ring-nx-cyan-500 dark:bg-nx-cyan-500/10'
                                        : 'hover:border-nx-navy-300 hover:bg-muted/50',
                                )}
                            >
                                <Icon className={cn('size-6', selected ? 'text-nx-cyan-600' : 'text-muted-foreground')} />
                                <span className="font-medium">{type.label}</span>
                                <span className="text-xs text-muted-foreground">{type.hint}</span>
                            </button>
                        );
                    })}
                </div>
                <InputError message={errors.business_type} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="business_description">Ceritakan singkat usaha Anda (opsional)</Label>
                <Textarea
                    id="business_description"
                    rows={3}
                    placeholder="Contoh: Klinik gigi dengan 3 dokter, menjual obat dan menerima pasien BPJS & asuransi."
                    value={data.business_description}
                    onChange={(event) => setData('business_description', event.target.value)}
                />
                <p className="text-xs text-muted-foreground">
                    Semakin jelas deskripsinya, semakin sesuai bagan akun yang disusun AI.
                </p>
                <InputError message={errors.business_description} />
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <label className="flex items-start gap-2 rounded-lg border p-3 text-sm">
                    <Checkbox
                        checked={data.uses_inventory}
                        onCheckedChange={(checked) => setData('uses_inventory', checked === true)}
                    />
                    <span>
                        <span className="block font-medium">Mengelola persediaan barang</span>
                        <span className="text-xs text-muted-foreground">Ada stok barang dagang, bahan baku, atau barang jadi.</span>
                    </span>
                </label>
                <label className="flex items-start gap-2 rounded-lg border p-3 text-sm">
                    <Checkbox checked={data.is_pkp} onCheckedChange={(checked) => setData('is_pkp', checked === true)} />
                    <span>
                        <span className="block font-medium">Pengusaha Kena Pajak (PKP)</span>
                        <span className="text-xs text-muted-foreground">Memungut dan melaporkan PPN.</span>
                    </span>
                </label>
            </div>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing || !data.industry || !data.business_type}>
                    {processing && <Spinner />}
                    Lanjut
                </Button>
            </div>
        </form>
    );
}
