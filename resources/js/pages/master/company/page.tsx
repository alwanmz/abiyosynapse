import { ListHeader } from '@/components/list-header';
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
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { IconBuilding, IconPencil } from '@tabler/icons-react';
import { useState } from 'react';

interface Company {
    kode: string | null;
    nama_perusahaan: string | null;
    alamat: string | null;
    telp: string | null;
    email: string | null;
    website: string | null;
    instagram: string | null;
    linkedin: string | null;
    logo_path: string | null;
    stamp_path: string | null;
}

interface Props {
    company: Company;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master', href: '#' },
    { title: 'Perusahaan', href: '/master/company' },
];

export default function CompanyPage({ company }: Props) {
    const [open, setOpen] = useState(false);
    const [logoPreview, setLogoPreview] = useState<string | null>(
        company.logo_path ? `/storage/${company.logo_path}` : null,
    );
    const [stampPreview, setStampPreview] = useState<string | null>(
        company.stamp_path ? `/storage/${company.stamp_path}` : null,
    );

    const { data, setData, post, processing, errors, clearErrors } = useForm({
        _method: 'post',
        kode: company.kode || '',
        nama_perusahaan: company.nama_perusahaan || '',
        alamat: company.alamat || '',
        telp: company.telp || '',
        email: company.email || '',
        website: company.website || '',
        instagram: company.instagram || '',
        linkedin: company.linkedin || '',
        logo: null as File | null,
        stamp: null as File | null,
    });

    const isEmpty = !company.nama_perusahaan && !company.kode && !company.logo_path;

    const openDialog = () => {
        clearErrors();
        setData({
            _method: 'post',
            kode: company.kode || '',
            nama_perusahaan: company.nama_perusahaan || '',
            alamat: company.alamat || '',
            telp: company.telp || '',
            email: company.email || '',
            website: company.website || '',
            instagram: company.instagram || '',
            linkedin: company.linkedin || '',
            logo: null,
            stamp: null,
        });
        setLogoPreview(company.logo_path ? `/storage/${company.logo_path}` : null);
        setStampPreview(company.stamp_path ? `/storage/${company.stamp_path}` : null);
        setOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/master/company', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setData('logo', file);
        if (file) setLogoPreview(URL.createObjectURL(file));
    };

    const handleStampChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        setData('stamp', file);
        if (file) setStampPreview(URL.createObjectURL(file));
    };

    const detailLogo = company.logo_path ? `/storage/${company.logo_path}` : null;
    const detailStamp = company.stamp_path ? `/storage/${company.stamp_path}` : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pengaturan Perusahaan" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Perusahaan</h1>
                        <p className="text-muted-foreground">Pengaturan profil perusahaan.</p>
                    </div>
                    <Button onClick={openDialog}>
                        <IconPencil className="mr-1 h-4 w-4" />
                        {isEmpty ? 'Lengkapi Profil' : 'Edit Profil'}
                    </Button>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader title="Profil Perusahaan" />
                    <CardContent className="p-5">
                        {isEmpty ? (
                            <div className="flex flex-col items-center gap-3 py-12 text-center">
                                <IconBuilding className="h-10 w-10 text-muted-foreground" />
                                <p className="text-muted-foreground">
                                    Profil perusahaan belum diisi.
                                </p>
                                <Button onClick={openDialog}>Lengkapi Profil</Button>
                            </div>
                        ) : (
                            <div className="grid gap-6 md:grid-cols-3">
                                <div className="md:col-span-2">
                                    <dl className="divide-y">
                                        <Row label="Kode" value={company.kode} />
                                        <Row label="Nama Perusahaan" value={company.nama_perusahaan} />
                                        <Row label="Alamat" value={company.alamat} />
                                        <Row label="Telp" value={company.telp} />
                                        <Row label="Email" value={company.email} />
                                        <Row label="Website" value={company.website} />
                                        <Row label="Instagram" value={company.instagram} />
                                        <Row label="LinkedIn" value={company.linkedin} />
                                    </dl>
                                </div>
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label>Logo</Label>
                                        <div className="flex aspect-square w-full items-center justify-center overflow-hidden rounded-md border bg-muted/30">
                                            {detailLogo ? (
                                                <img src={detailLogo} alt="Logo" className="h-full w-full object-contain" />
                                            ) : (
                                                <span className="text-xs text-muted-foreground">Tanpa logo</span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Cap / Stempel</Label>
                                        <div className="flex aspect-square w-full items-center justify-center overflow-hidden rounded-md border bg-muted/30">
                                            {detailStamp ? (
                                                <img src={detailStamp} alt="Cap perusahaan" className="h-full w-full object-contain" />
                                            ) : (
                                                <span className="text-xs text-muted-foreground">Tanpa cap</span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{isEmpty ? 'Lengkapi Profil Perusahaan' : 'Edit Profil Perusahaan'}</DialogTitle>
                        <DialogDescription>
                            Logo & nama yang disimpan akan tampil di sidebar aplikasi.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="grid gap-6 md:grid-cols-3">
                        <div className="space-y-4 md:col-span-2">
                            <div className="space-y-2">
                                <Label htmlFor="kode">Kode</Label>
                                <Input id="kode" value={data.kode} onChange={(e) => setData('kode', e.target.value)} />
                                {errors.kode && <p className="text-sm text-destructive">{errors.kode}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="nama_perusahaan">Nama Perusahaan</Label>
                                <Input id="nama_perusahaan" value={data.nama_perusahaan} onChange={(e) => setData('nama_perusahaan', e.target.value)} />
                                {errors.nama_perusahaan && <p className="text-sm text-destructive">{errors.nama_perusahaan}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="alamat">Alamat</Label>
                                <Textarea id="alamat" rows={3} value={data.alamat} onChange={(e) => setData('alamat', e.target.value)} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="telp">Telp</Label>
                                <Input id="telp" value={data.telp} onChange={(e) => setData('telp', e.target.value)} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                                {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="website">Website</Label>
                                <Input id="website" value={data.website} onChange={(e) => setData('website', e.target.value)} placeholder="sistemkesehatan.id" />
                                {errors.website && <p className="text-sm text-destructive">{errors.website}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="instagram">Instagram</Label>
                                <Input id="instagram" value={data.instagram} onChange={(e) => setData('instagram', e.target.value)} placeholder="sistemkesehatan.id" />
                                {errors.instagram && <p className="text-sm text-destructive">{errors.instagram}</p>}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="linkedin">LinkedIn</Label>
                                <Input id="linkedin" value={data.linkedin} onChange={(e) => setData('linkedin', e.target.value)} placeholder="sistemkesehatan.id" />
                                {errors.linkedin && <p className="text-sm text-destructive">{errors.linkedin}</p>}
                            </div>
                        </div>

                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label>Logo</Label>
                                <div className="flex aspect-square w-full items-center justify-center overflow-hidden rounded-md border bg-muted/30">
                                    {logoPreview ? (
                                        <img src={logoPreview} alt="Logo" className="h-full w-full object-contain" />
                                    ) : (
                                        <span className="text-xs text-muted-foreground">No logo</span>
                                    )}
                                </div>
                                <Input type="file" accept="image/*" onChange={handleLogoChange} />
                                {errors.logo && <p className="text-sm text-destructive">{errors.logo}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label>Cap / Stempel</Label>
                                <div className="flex aspect-square w-full items-center justify-center overflow-hidden rounded-md border bg-muted/30">
                                    {stampPreview ? (
                                        <img src={stampPreview} alt="Cap perusahaan" className="h-full w-full object-contain" />
                                    ) : (
                                        <span className="text-xs text-muted-foreground">No cap</span>
                                    )}
                                </div>
                                <Input type="file" accept="image/*" onChange={handleStampChange} />
                                {errors.stamp && <p className="text-sm text-destructive">{errors.stamp}</p>}
                                <p className="text-xs text-muted-foreground">
                                    Dipakai otomatis di tanda tangan Laporan Maintenance.
                                </p>
                            </div>
                        </div>

                        <DialogFooter className="md:col-span-3">
                            <Button type="button" variant="outline" onClick={() => setOpen(false)} disabled={processing}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

function Row({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="grid grid-cols-3 gap-4 py-3">
            <dt className="text-sm font-medium text-muted-foreground">{label}</dt>
            <dd className="col-span-2 text-sm break-words">{value || '-'}</dd>
        </div>
    );
}
