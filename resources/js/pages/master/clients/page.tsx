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
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import {
    IconBrandTelegram,
    IconKey,
    IconPencil,
    IconPlus,
    IconSearch,
    IconTrash,
} from '@tabler/icons-react';
import { useState } from 'react';

interface TelegramContact {
    id: number;
    chat_id: number;
    telegram_username: string | null;
    telegram_first_name: string | null;
    telegram_last_name: string | null;
    phone_number: string | null;
    verified_at: string | null;
}

interface Client {
    id: number;
    kode: string;
    nama: string;
    alamat: string | null;
    director_name: string | null;
    director_title: string | null;
    kontak: string | null;
    username: string | null;
    email: string | null;
    deskripsi: string | null;
    is_active: boolean;
    monthly_request_quota: number | null;
    request_quota_unlimited: boolean;
    request_quota?: { remaining: number } | null;
    has_portal_password?: boolean;
    portal_password_plain: string | null;
    telegram_verification_code: string | null;
    telegram_verification_code_generated_at: string | null;
    telegram_contacts?: TelegramContact[];
}

interface Props {
    clients: Client[];
    filters: { search: string };
    defaultQuota: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master', href: '#' },
    { title: 'Client', href: '/master/clients' },
];

export default function ClientsPage({
    clients,
    filters,
    defaultQuota,
}: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Client | null>(null);
    const [deleting, setDeleting] = useState<Client | null>(null);
    const [search, setSearch] = useState(filters.search || '');

    const { data, setData, post, put, processing, reset, errors, clearErrors } =
        useForm({
            kode: '',
            nama: '',
            alamat: '',
            director_name: '',
            director_title: '',
            kontak: '',
            username: '',
            email: '',
            deskripsi: '',
            is_active: true as boolean,
            monthly_request_quota: '' as string,
            request_quota_unlimited: false as boolean,
        });

    const startCreate = () => {
        setEditing(null);
        clearErrors();
        reset();
        setOpen(true);
    };

    const startEdit = (client: Client) => {
        setEditing(client);
        clearErrors();
        setData({
            kode: client.kode,
            nama: client.nama,
            alamat: client.alamat || '',
            director_name: client.director_name || '',
            director_title: client.director_title || '',
            kontak: client.kontak || '',
            username: client.username || '',
            email: client.email || '',
            deskripsi: client.deskripsi || '',
            is_active: client.is_active,
            monthly_request_quota:
                client.monthly_request_quota != null
                    ? String(client.monthly_request_quota)
                    : '',
            request_quota_unlimited: client.request_quota_unlimited,
        });
        setOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editing) {
            put(`/master/clients/${editing.id}`, {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
            });
        } else {
            post('/master/clients', {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
            });
        }
    };

    const handleDelete = () => {
        if (!deleting) return;
        router.delete(`/master/clients/${deleting.id}`, {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    };

    const [revokingContact, setRevokingContact] =
        useState<TelegramContact | null>(null);

    const regenerateTelegramCode = () => {
        if (!editing) return;
        router.post(
            `/master/clients/${editing.id}/telegram-code`,
            {},
            { preserveScroll: true },
        );
    };

    const resetRequestQuota = () => {
        if (!editing) return;
        router.post(
            `/master/clients/${editing.id}/reset-request-quota`,
            {},
            { preserveScroll: true },
        );
    };

    const regeneratePortalPassword = () => {
        if (!editing) return;
        router.post(
            `/master/clients/${editing.id}/portal-password`,
            {},
            { preserveScroll: true },
        );
    };

    const handleRevokeContact = () => {
        if (!revokingContact) return;
        router.delete(
            `/master/clients/telegram-contacts/${revokingContact.id}`,
            {
                preserveScroll: true,
                onFinish: () => setRevokingContact(null),
            },
        );
    };

    const contactName = (c: TelegramContact) => {
        const name =
            `${c.telegram_first_name ?? ''} ${c.telegram_last_name ?? ''}`.trim();
        return (
            name ||
            (c.telegram_username
                ? `@${c.telegram_username}`
                : `Chat #${c.chat_id}`)
        );
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/master/clients',
            { search },
            { preserveState: true, replace: true },
        );
    };

    // `editing` is a snapshot taken when the dialog opened; look up the live
    // record from `clients` so the Telegram code/contacts refresh in place
    // after a regenerate/revoke action (Inertia reloads `clients`, not `editing`).
    const liveEditing = editing
        ? (clients.find((c) => c.id === editing.id) ?? editing)
        : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Master Klien" />

            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Master Klien
                    </h1>
                    <p className="text-muted-foreground">
                        Kelola data client.{' '}
                        <span className="font-medium text-amber-600">
                            Catatan: semua aksi (tambah/ubah/hapus) tersimpan di
                            tabel log.
                        </span>
                    </p>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader
                        title="Daftar Client"
                        action={
                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={startCreate}
                            >
                                <IconPlus className="mr-1 h-4 w-4" /> Tambah
                            </Button>
                        }
                    />
                    <CardContent className="space-y-4 p-5">
                        <form
                            onSubmit={handleSearch}
                            className="flex items-center gap-2"
                        >
                            <div className="relative flex-1">
                                <IconSearch className="absolute top-2.5 left-2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    className="pl-8"
                                    placeholder="Cari kode, nama, atau deskripsi client..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            <Button type="submit" variant="outline">
                                Cari
                            </Button>
                        </form>

                        <div className="w-full overflow-x-auto rounded-md border">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead
                                    className="text-white shadow-[0_4px_16px_-8px_rgba(59,130,246,0.55)]"
                                    style={{
                                        backgroundImage:
                                            'linear-gradient(110deg, #1e3a8a 0%, #2563eb 45%, #0ea5e9 100%)',
                                    }}
                                >
                                    <tr>
                                        <th className="px-3 py-2.5 text-left text-xs font-semibold tracking-wider whitespace-nowrap uppercase">
                                            Kode
                                        </th>
                                        <th className="px-3 py-2.5 text-left text-xs font-semibold tracking-wider whitespace-nowrap uppercase">
                                            Nama Klient
                                        </th>
                                        <th className="px-3 py-2.5 text-left text-xs font-semibold tracking-wider whitespace-nowrap uppercase">
                                            Deskripsi
                                        </th>
                                        <th className="px-3 py-2.5 text-left text-xs font-semibold tracking-wider whitespace-nowrap uppercase">
                                            Kuota
                                        </th>
                                        <th className="px-3 py-2.5 text-left text-xs font-semibold tracking-wider whitespace-nowrap uppercase">
                                            Status
                                        </th>
                                        <th className="px-3 py-2.5 text-right text-xs font-semibold tracking-wider uppercase">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {clients.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-3 py-8 text-center text-muted-foreground"
                                            >
                                                Belum ada data client.
                                            </td>
                                        </tr>
                                    ) : (
                                        clients.map((c) => (
                                            <tr
                                                key={c.id}
                                                className="hover:bg-muted/30"
                                            >
                                                <td className="px-3 py-2 font-mono">
                                                    {c.kode}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {c.nama}
                                                </td>
                                                <td className="max-w-xs px-3 py-2 text-muted-foreground">
                                                    <span className="line-clamp-2">
                                                        {c.deskripsi || '-'}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 whitespace-nowrap">
                                                    <Badge variant="secondary">
                                                        {c.request_quota_unlimited
                                                            ? 'Unlimited'
                                                            : `${c.request_quota?.remaining ?? c.monthly_request_quota ?? defaultQuota}/${c.monthly_request_quota ?? defaultQuota}`}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <Badge
                                                        variant={
                                                            c.is_active
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {c.is_active
                                                            ? 'Aktif'
                                                            : 'Tidak Kerjasama'}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <div className="flex justify-end gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8"
                                                            onClick={() =>
                                                                startEdit(c)
                                                            }
                                                        >
                                                            <IconPencil
                                                                size={16}
                                                            />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8 text-destructive"
                                                            onClick={() =>
                                                                setDeleting(c)
                                                            }
                                                        >
                                                            <IconTrash
                                                                size={16}
                                                            />
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit Klien' : 'Tambah Client'}
                        </DialogTitle>
                        <DialogDescription>
                            Lengkapi data client di bawah ini.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="kode">Kode</Label>
                            <Input
                                id="kode"
                                value={data.kode}
                                onChange={(e) =>
                                    setData('kode', e.target.value)
                                }
                                required
                            />
                            {errors.kode && (
                                <p className="text-sm text-destructive">
                                    {errors.kode}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="nama">Nama Klient</Label>
                            <Input
                                id="nama"
                                value={data.nama}
                                onChange={(e) =>
                                    setData('nama', e.target.value)
                                }
                                required
                            />
                            {errors.nama && (
                                <p className="text-sm text-destructive">
                                    {errors.nama}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="alamat">Alamat</Label>
                            <Textarea
                                id="alamat"
                                rows={3}
                                value={data.alamat}
                                onChange={(e) =>
                                    setData('alamat', e.target.value)
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Dipakai sebagai alamat tujuan pada Laporan Maintenance.
                            </p>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="director_name">
                                Nama Direktur/PIC (untuk surat)
                            </Label>
                            <Input
                                id="director_name"
                                value={data.director_name}
                                onChange={(e) =>
                                    setData('director_name', e.target.value)
                                }
                                placeholder="mis. dr. H. Slamet Tjahjono, Sp.P(K), FISR"
                            />
                            {errors.director_name && (
                                <p className="text-sm text-destructive">
                                    {errors.director_name}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="director_title">
                                Jabatan Direktur/PIC
                            </Label>
                            <Input
                                id="director_title"
                                value={data.director_title}
                                onChange={(e) =>
                                    setData('director_title', e.target.value)
                                }
                                placeholder="mis. Direktur Rumah Sakit Umum Harapan Keluarga"
                            />
                            {errors.director_title && (
                                <p className="text-sm text-destructive">
                                    {errors.director_title}
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Nama, jabatan, dan alamat di atas menjadi default
                                &quot;Kepada Yth.&quot; saat membuat Laporan Maintenance
                                untuk klien ini.
                            </p>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="kontak">Kontak</Label>
                            <Input
                                id="kontak"
                                value={data.kontak}
                                onChange={(e) =>
                                    setData('kontak', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="username">
                                Username (login utama Portal Klien)
                            </Label>
                            <Input
                                id="username"
                                type="text"
                                value={data.username}
                                onChange={(e) =>
                                    setData('username', e.target.value)
                                }
                                placeholder="mis. rsud-sehat"
                            />
                            {errors.username && (
                                <p className="text-sm text-destructive">
                                    {errors.username}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="email">
                                Email (opsional, alternatif login)
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                            />
                            {errors.email && (
                                <p className="text-sm text-destructive">
                                    {errors.email}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="deskripsi">Deskripsi</Label>
                            <Textarea
                                id="deskripsi"
                                rows={3}
                                value={data.deskripsi}
                                onChange={(e) =>
                                    setData('deskripsi', e.target.value)
                                }
                            />
                            {errors.deskripsi && (
                                <p className="text-sm text-destructive">
                                    {errors.deskripsi}
                                </p>
                            )}
                        </div>
                        <div className="flex items-center gap-2">
                            <input
                                id="is_active"
                                type="checkbox"
                                className="h-4 w-4"
                                checked={data.is_active}
                                onChange={(e) =>
                                    setData('is_active', e.target.checked)
                                }
                            />
                            <Label
                                htmlFor="is_active"
                                className="cursor-pointer"
                            >
                                Masih kerjasama (aktif)
                            </Label>
                        </div>

                        <Separator />

                        <div className="space-y-3">
                            <Label className="text-sm font-semibold">
                                Kuota Permintaan Baru (Portal Klien)
                            </Label>
                            <div className="flex items-center gap-2">
                                <input
                                    id="request_quota_unlimited"
                                    type="checkbox"
                                    className="h-4 w-4"
                                    checked={data.request_quota_unlimited}
                                    onChange={(e) =>
                                        setData(
                                            'request_quota_unlimited',
                                            e.target.checked,
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="request_quota_unlimited"
                                    className="cursor-pointer"
                                >
                                    Unlimited request
                                </Label>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="monthly_request_quota">
                                    Kuota request per bulan
                                </Label>
                                <Input
                                    id="monthly_request_quota"
                                    type="number"
                                    min={0}
                                    placeholder={`Kosongkan = default ${defaultQuota}`}
                                    disabled={data.request_quota_unlimited}
                                    value={data.monthly_request_quota}
                                    onChange={(e) =>
                                        setData(
                                            'monthly_request_quota',
                                            e.target.value,
                                        )
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    Hanya tiket kategori &quot;Permintaan
                                    Baru&quot; yang memotong kuota; laporan bug
                                    tidak dibatasi. Sisa kuota dibawa ke bulan
                                    berikutnya dan baru diisi ulang setelah
                                    habis.
                                </p>
                                {errors.monthly_request_quota && (
                                    <p className="text-sm text-destructive">
                                        {errors.monthly_request_quota}
                                    </p>
                                )}
                            </div>
                            {liveEditing && (
                                <div className="flex items-center justify-between gap-2 rounded-md border bg-muted/30 p-3">
                                    <span className="text-xs text-muted-foreground">
                                        Sisa saat ini:{' '}
                                        <span className="font-medium text-foreground">
                                            {liveEditing.request_quota_unlimited
                                                ? 'Unlimited'
                                                : (liveEditing.request_quota
                                                      ?.remaining ??
                                                  'Belum dipakai')}
                                        </span>
                                    </span>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={
                                            liveEditing.request_quota_unlimited
                                        }
                                        onClick={resetRequestQuota}
                                    >
                                        Reset kuota sekarang
                                    </Button>
                                </div>
                            )}
                        </div>

                        {liveEditing && (
                            <>
                                <Separator />
                                <div className="space-y-3">
                                    <div className="flex items-center gap-2">
                                        <IconBrandTelegram
                                            size={16}
                                            className="text-sky-500"
                                        />
                                        <Label className="text-sm font-semibold">
                                            Submit Tiket via Telegram
                                        </Label>
                                    </div>

                                    <div className="rounded-md border bg-muted/30 p-3">
                                        <p className="mb-2 text-xs text-muted-foreground">
                                            Bagikan kode ini ke kontak client
                                            agar bisa terverifikasi &amp; submit
                                            tiket lewat bot Telegram.
                                        </p>
                                        <div className="flex items-center justify-between gap-2">
                                            <code className="rounded bg-background px-2 py-1 font-mono text-sm">
                                                {liveEditing.telegram_verification_code ??
                                                    'Belum ada kode'}
                                            </code>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={regenerateTelegramCode}
                                            >
                                                {liveEditing.telegram_verification_code
                                                    ? 'Buat Ulang'
                                                    : 'Buat Kode'}
                                            </Button>
                                        </div>
                                    </div>

                                    <div className="space-y-1">
                                        <Label className="text-xs text-muted-foreground">
                                            Kontak Terverifikasi
                                        </Label>
                                        {(liveEditing.telegram_contacts
                                            ?.length ?? 0) === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                Belum ada kontak yang
                                                terverifikasi.
                                            </p>
                                        ) : (
                                            <div className="divide-y rounded-md border">
                                                {liveEditing.telegram_contacts!.map(
                                                    (c) => (
                                                        <div
                                                            key={c.id}
                                                            className="flex items-center justify-between px-3 py-2"
                                                        >
                                                            <div className="text-sm">
                                                                <div>
                                                                    {contactName(
                                                                        c,
                                                                    )}
                                                                </div>
                                                                {c.phone_number && (
                                                                    <div className="text-xs text-muted-foreground">
                                                                        {
                                                                            c.phone_number
                                                                        }
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                variant="ghost"
                                                                className="text-destructive"
                                                                onClick={() =>
                                                                    setRevokingContact(
                                                                        c,
                                                                    )
                                                                }
                                                            >
                                                                Cabut
                                                            </Button>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <Separator />
                                <div className="space-y-3">
                                    <div className="flex items-center gap-2">
                                        <IconKey
                                            size={16}
                                            className="text-blue-500"
                                        />
                                        <Label className="text-sm font-semibold">
                                            Login Portal Klien
                                        </Label>
                                    </div>

                                    <div className="rounded-md border bg-muted/30 p-3">
                                        {liveEditing.username ||
                                        liveEditing.email ? (
                                            <>
                                                <p className="mb-2 text-xs text-muted-foreground">
                                                    Bagikan kredensial ini ke
                                                    klien agar bisa login di{' '}
                                                    <code className="rounded bg-background px-1 py-0.5">
                                                        /portal/login
                                                    </code>{' '}
                                                    &amp; memantau progres
                                                    tiket.
                                                </p>

                                                <div className="mb-2 space-y-1">
                                                    <div className="flex items-center justify-between gap-2">
                                                        <span className="text-xs text-muted-foreground">
                                                            Username
                                                        </span>
                                                        <code className="rounded bg-background px-2 py-1 font-mono text-sm">
                                                            {liveEditing.username ||
                                                                liveEditing.email}
                                                        </code>
                                                    </div>
                                                    <div className="flex items-center justify-between gap-2">
                                                        <span className="text-xs text-muted-foreground">
                                                            Password
                                                        </span>
                                                        <code className="rounded bg-background px-2 py-1 font-mono text-sm">
                                                            {liveEditing.portal_password_plain ??
                                                                'Belum ada password'}
                                                        </code>
                                                    </div>
                                                </div>

                                                <div className="flex justify-end">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={
                                                            regeneratePortalPassword
                                                        }
                                                    >
                                                        {liveEditing.has_portal_password
                                                            ? 'Reset Password'
                                                            : 'Buat Password'}
                                                    </Button>
                                                </div>
                                            </>
                                        ) : (
                                            <p className="text-xs text-muted-foreground">
                                                Isi{' '}
                                                <strong className="text-foreground">
                                                    Username
                                                </strong>{' '}
                                                (atau Email) di atas &amp;
                                                simpan dulu untuk mengaktifkan
                                                login Portal Klien.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </>
                        )}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                                disabled={processing}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={!!revokingContact}
                onOpenChange={(o) => !o && setRevokingContact(null)}
                title="Cabut akses kontak Telegram?"
                description={
                    <>
                        Kontak{' '}
                        <strong className="text-foreground">
                            "
                            {revokingContact
                                ? contactName(revokingContact)
                                : ''}
                            "
                        </strong>{' '}
                        tidak akan bisa submit tiket lagi sampai diverifikasi
                        ulang dengan kode baru.
                    </>
                }
                confirmLabel="Ya, Cabut"
                onConfirm={handleRevokeContact}
            />

            <ConfirmDialog
                open={!!deleting}
                onOpenChange={(o) => !o && setDeleting(null)}
                title="Hapus data client?"
                description={
                    <>
                        Client{' '}
                        <strong className="text-foreground">
                            "{deleting?.nama}"
                        </strong>{' '}
                        akan dihapus. Aksi ini akan tercatat di tabel log dan
                        tidak bisa dibatalkan.
                    </>
                }
                confirmLabel="Ya, Hapus"
                onConfirm={handleDelete}
            />
        </AppLayout>
    );
}
