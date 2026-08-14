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
import { ai } from '@/lib/ai';
import type { SharedData } from '@/types';
import type {
    MaintenanceReport,
    MaintenanceReportClient,
    MaintenanceReportFormData,
    MaintenanceReportProject,
} from '@/types/maintenance-report';
import { router, usePage } from '@inertiajs/react';
import { Plus, Sparkles, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

const emptyItem = {
    ticket_id: null,
    category: '',
    found_at: '',
    description: '',
    resolution: '',
    status_result: '',
    resolved_at: '',
    notes: '',
};

const todayIso = () => new Date().toISOString().slice(0, 10);

export function MaintenanceReportForm({
    clients,
    projects,
    statusResultOptions,
    report,
}: {
    clients: MaintenanceReportClient[];
    projects: MaintenanceReportProject[];
    statusResultOptions: string[];
    /** Diisi saat mode edit. */
    report?: MaintenanceReport;
}) {
    const isEdit = Boolean(report);

    const [data, setData] = useState<MaintenanceReportFormData>(() =>
        report
            ? {
                  client_id: String(report.client_id),
                  project_id: report.project_id ? String(report.project_id) : '',
                  title: report.title,
                  period_start: report.period_start,
                  period_end: report.period_end,
                  letter_date: report.letter_date ?? todayIso(),
                  recipient_name: report.recipient_name ?? '',
                  recipient_title: report.recipient_title ?? '',
                  recipient_address: report.recipient_address ?? '',
                  summary: report.summary ?? '',
                  signed_by_name: report.signed_by_name ?? '',
                  signed_by_role: report.signed_by_role ?? '',
                  signature: null,
                  items: report.items.length ? report.items : [{ ...emptyItem }],
              }
            : {
                  client_id: '',
                  project_id: '',
                  title: '',
                  period_start: todayIso(),
                  period_end: todayIso(),
                  letter_date: todayIso(),
                  recipient_name: '',
                  recipient_title: '',
                  recipient_address: '',
                  summary: '',
                  signed_by_name: '',
                  signed_by_role: '',
                  signature: null,
                  items: [{ ...emptyItem }],
              },
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const { ai: aiCfg } = usePage<SharedData>().props;
    const aiEnabled = !!aiCfg?.enabled;
    const [generating, setGenerating] = useState(false);
    const [aiError, setAiError] = useState<string | null>(null);
    const [aiNotice, setAiNotice] = useState<string | null>(null);

    const set = <K extends keyof MaintenanceReportFormData>(
        key: K,
        value: MaintenanceReportFormData[K],
    ) => setData((current) => ({ ...current, [key]: value }));

    // Proyek dibatasi pada klien terpilih agar tidak salah pasang.
    const availableProjects = useMemo(
        () =>
            data.client_id
                ? projects.filter((project) => String(project.client_id ?? '') === data.client_id)
                : projects,
        [projects, data.client_id],
    );

    const selectedClient = useMemo(
        () => clients.find((client) => String(client.id) === data.client_id) ?? null,
        [clients, data.client_id],
    );

    // Saat klien dipilih (mode create), isi otomatis "Kepada Yth." dari data
    // direktur default klien tsb — staff tetap bisa mengedit/override manual.
    const handleClientChange = (value: string) => {
        set('client_id', value);
        set('project_id', '');

        if (isEdit) return;

        const client = clients.find((c) => String(c.id) === value);
        set('recipient_name', client?.director_name ?? '');
        set('recipient_title', client?.director_title ?? '');
        set('recipient_address', client?.alamat && client.alamat !== '-' ? client.alamat : '');
    };

    const updateItem = (index: number, key: keyof typeof emptyItem, value: string) =>
        setData((current) => ({
            ...current,
            items: current.items.map((item, i) => (i === index ? { ...item, [key]: value } : item)),
        }));

    const canGenerate =
        aiEnabled && Boolean(data.client_id) && Boolean(data.period_start) && Boolean(data.period_end);

    const generateWithAi = async () => {
        if (!canGenerate) return;

        setGenerating(true);
        setAiError(null);
        setAiNotice(null);

        try {
            const draft = await ai.draftMaintenanceReport(
                Number(data.client_id),
                data.project_id ? Number(data.project_id) : null,
                data.period_start,
                data.period_end,
            );

            if (draft.items.length === 0) {
                setAiNotice('Tidak ada tiket selesai pada periode ini untuk klien tersebut.');
                return;
            }

            set('summary', draft.summary);
            set(
                'items',
                draft.items.map((item) => ({
                    ticket_id: item.ticket_id,
                    category: item.category,
                    found_at: item.found_at ?? '',
                    description: item.description,
                    resolution: item.resolution,
                    status_result: item.status_result,
                    resolved_at: item.resolved_at ?? '',
                    notes: '',
                })),
            );
        } catch (error) {
            setAiError(error instanceof Error ? error.message : 'Gagal membuat draft AI.');
        } finally {
            setGenerating(false);
        }
    };

    const submit = () => {
        setProcessing(true);
        setErrors({});

        // eslint-disable-next-line @typescript-eslint/no-explicit-any -- payload multipart campuran File & skalar.
        const payload: Record<string, any> = {
            client_id: data.client_id,
            project_id: data.project_id || null,
            title: data.title,
            period_start: data.period_start,
            period_end: data.period_end,
            letter_date: data.letter_date,
            recipient_name: data.recipient_name,
            recipient_title: data.recipient_title,
            recipient_address: data.recipient_address,
            summary: data.summary,
            signed_by_name: data.signed_by_name,
            signed_by_role: data.signed_by_role,
            items: data.items.filter((item) => item.description.trim() !== ''),
        };

        if (data.signature) payload.signature = data.signature;
        if (isEdit) payload._method = 'put';

        router.post(
            isEdit ? `/maintenance-reports/${report!.id}` : '/maintenance-reports',
            payload,
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (formErrors) => setErrors(formErrors as Record<string, string>),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="flex flex-col gap-4">
            <Card>
                <CardContent className="grid gap-4 p-4">
                    <h2 className="text-sm font-semibold">Informasi Laporan</h2>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Klien</Label>
                            <Select value={data.client_id} onValueChange={handleClientChange}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih klien" />
                                </SelectTrigger>
                                <SelectContent>
                                    {clients.map((client) => (
                                        <SelectItem key={client.id} value={String(client.id)}>
                                            {client.kode ? `${client.kode} — ` : ''}
                                            {client.nama}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.client_id && (
                                <p className="text-sm text-destructive">{errors.client_id}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label>Proyek (opsional)</Label>
                            <Select
                                value={data.project_id || 'none'}
                                onValueChange={(value) =>
                                    set('project_id', value === 'none' ? '' : value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Tanpa proyek" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Tanpa proyek</SelectItem>
                                    {availableProjects.map((project) => (
                                        <SelectItem key={project.id} value={String(project.id)}>
                                            {project.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.project_id && (
                                <p className="text-sm text-destructive">{errors.project_id}</p>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="letter_date">Tanggal Surat</Label>
                        <Input
                            id="letter_date"
                            type="date"
                            value={data.letter_date}
                            onChange={(e) => set('letter_date', e.target.value)}
                            className="sm:w-56"
                        />
                        {errors.letter_date && (
                            <p className="text-sm text-destructive">{errors.letter_date}</p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="title">Judul Laporan</Label>
                        <Input
                            id="title"
                            value={data.title}
                            onChange={(e) => set('title', e.target.value)}
                            placeholder="Misal: Laporan Pemeliharaan Sistem Januari 2026"
                        />
                        {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="period_start">Periode Mulai</Label>
                            <Input
                                id="period_start"
                                type="date"
                                value={data.period_start}
                                onChange={(e) => set('period_start', e.target.value)}
                            />
                            {errors.period_start && (
                                <p className="text-sm text-destructive">{errors.period_start}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="period_end">Periode Selesai</Label>
                            <Input
                                id="period_end"
                                type="date"
                                value={data.period_end}
                                onChange={(e) => set('period_end', e.target.value)}
                            />
                            {errors.period_end && (
                                <p className="text-sm text-destructive">{errors.period_end}</p>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="summary">Ringkasan Eksekutif</Label>
                        <Textarea
                            id="summary"
                            value={data.summary}
                            onChange={(e) => set('summary', e.target.value)}
                            placeholder="Ringkasan singkat pekerjaan pemeliharaan pada periode ini."
                            rows={4}
                        />
                        {errors.summary && (
                            <p className="text-sm text-destructive">{errors.summary}</p>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="grid gap-4 p-4">
                    <div>
                        <h2 className="text-sm font-semibold">Kepada Yth.</h2>
                        <p className="text-xs text-muted-foreground">
                            Penerima surat. Terisi otomatis dari data direktur klien (Master &gt; Klien) saat
                            klien dipilih, bisa diubah manual kalau penerima kali ini berbeda.
                        </p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="recipient_name">Nama Penerima</Label>
                            <Input
                                id="recipient_name"
                                value={data.recipient_name}
                                onChange={(e) => set('recipient_name', e.target.value)}
                                placeholder="Misal: dr. H. Slamet Tjahjono, Sp.P(K), FISR"
                            />
                            {errors.recipient_name && (
                                <p className="text-sm text-destructive">{errors.recipient_name}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="recipient_title">Jabatan</Label>
                            <Input
                                id="recipient_title"
                                value={data.recipient_title}
                                onChange={(e) => set('recipient_title', e.target.value)}
                                placeholder="Misal: Direktur Rumah Sakit Umum Harapan Keluarga"
                            />
                            {errors.recipient_title && (
                                <p className="text-sm text-destructive">{errors.recipient_title}</p>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="recipient_address">Alamat</Label>
                        <Textarea
                            id="recipient_address"
                            value={data.recipient_address}
                            onChange={(e) => set('recipient_address', e.target.value)}
                            placeholder="Alamat lengkap tujuan surat"
                            rows={2}
                        />
                        {errors.recipient_address && (
                            <p className="text-sm text-destructive">{errors.recipient_address}</p>
                        )}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="grid gap-4 p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold">Rincian Pekerjaan</h2>
                        <span className="text-xs text-muted-foreground">
                            {data.items.length} baris
                        </span>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="gap-1.5"
                            onClick={generateWithAi}
                            disabled={!canGenerate || generating}
                            title={
                                !aiEnabled
                                    ? 'AI belum diaktifkan di server'
                                    : !canGenerate
                                      ? 'Pilih klien dan periode terlebih dahulu'
                                      : 'Ambil tiket selesai pada periode ini lalu isi otomatis Ringkasan & Rincian Pekerjaan'
                            }
                        >
                            {generating ? (
                                <>
                                    <Sparkles className="h-4 w-4 animate-pulse" /> Membuat draft…
                                </>
                            ) : (
                                <>
                                    <Sparkles className="h-4 w-4" /> Generate dengan AI
                                </>
                            )}
                        </Button>
                        {!aiEnabled && (
                            <span className="text-xs text-muted-foreground">AI belum aktif di server</span>
                        )}
                        {aiEnabled && !canGenerate && (
                            <span className="text-xs text-muted-foreground">
                                Pilih klien dan periode dulu
                            </span>
                        )}
                    </div>
                    {aiError && <p className="text-sm text-destructive">{aiError}</p>}
                    {aiNotice && <p className="text-sm text-muted-foreground">{aiNotice}</p>}

                    <div className="flex flex-col gap-3">
                        {data.items.map((item, index) => (
                            <div key={index} className="grid gap-2 rounded-md border p-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-medium text-muted-foreground">
                                        Item {index + 1}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="h-7 w-7"
                                        disabled={data.items.length === 1}
                                        onClick={() =>
                                            set(
                                                'items',
                                                data.items.filter((_, i) => i !== index),
                                            )
                                        }
                                        aria-label="Hapus item"
                                    >
                                        <Trash2 className="h-4 w-4 text-destructive/70" />
                                    </Button>
                                </div>

                                <div className="grid gap-2 sm:grid-cols-2">
                                    <Input
                                        value={item.category ?? ''}
                                        onChange={(e) => updateItem(index, 'category', e.target.value)}
                                        placeholder="Kategori (misal: Database)"
                                    />
                                    <Select
                                        value={item.status_result || 'none'}
                                        onValueChange={(value) =>
                                            updateItem(
                                                index,
                                                'status_result',
                                                value === 'none' ? '' : value,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Status penyelesaian" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">— Belum diisi —</SelectItem>
                                            {statusResultOptions.map((option) => (
                                                <SelectItem key={option} value={option}>
                                                    {option}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid gap-2 sm:grid-cols-2">
                                    <div className="grid gap-1">
                                        <Label className="text-xs font-normal text-muted-foreground">
                                            Tanggal Temuan
                                        </Label>
                                        <Input
                                            type="date"
                                            value={item.found_at ?? ''}
                                            onChange={(e) => updateItem(index, 'found_at', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label className="text-xs font-normal text-muted-foreground">
                                            Tanggal Penyelesaian
                                        </Label>
                                        <Input
                                            type="date"
                                            value={item.resolved_at ?? ''}
                                            onChange={(e) => updateItem(index, 'resolved_at', e.target.value)}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-1">
                                    <Label className="text-xs font-normal text-muted-foreground">
                                        Uraian Temuan
                                    </Label>
                                    <Textarea
                                        value={item.description}
                                        onChange={(e) => updateItem(index, 'description', e.target.value)}
                                        placeholder="Uraian temuan/kendala yang dilaporkan"
                                        rows={2}
                                    />
                                    {errors[`items.${index}.description`] && (
                                        <p className="text-sm text-destructive">
                                            {errors[`items.${index}.description`]}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-1">
                                    <Label className="text-xs font-normal text-muted-foreground">
                                        Uraian Penyelesaian
                                    </Label>
                                    <Textarea
                                        value={item.resolution ?? ''}
                                        onChange={(e) => updateItem(index, 'resolution', e.target.value)}
                                        placeholder="Tindak lanjut/perbaikan yang dilakukan"
                                        rows={2}
                                    />
                                    {errors[`items.${index}.resolution`] && (
                                        <p className="text-sm text-destructive">
                                            {errors[`items.${index}.resolution`]}
                                        </p>
                                    )}
                                </div>

                                <Input
                                    value={item.notes ?? ''}
                                    onChange={(e) => updateItem(index, 'notes', e.target.value)}
                                    placeholder="Keterangan (opsional)"
                                />
                            </div>
                        ))}
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="w-fit"
                        onClick={() => set('items', [...data.items, { ...emptyItem }])}
                    >
                        <Plus className="mr-1.5 h-4 w-4" />
                        Tambah baris
                    </Button>
                    {errors.items && <p className="text-sm text-destructive">{errors.items}</p>}
                </CardContent>
            </Card>

            <Card>
                <CardContent className="grid gap-4 p-4">
                    <h2 className="text-sm font-semibold">Penanggung Jawab & Tanda Tangan</h2>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="signed_by_name">Nama Penanggung Jawab</Label>
                            <Input
                                id="signed_by_name"
                                value={data.signed_by_name}
                                onChange={(e) => set('signed_by_name', e.target.value)}
                                placeholder="Nama lengkap"
                            />
                            {errors.signed_by_name && (
                                <p className="text-sm text-destructive">{errors.signed_by_name}</p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="signed_by_role">Jabatan</Label>
                            <Input
                                id="signed_by_role"
                                value={data.signed_by_role}
                                onChange={(e) => set('signed_by_role', e.target.value)}
                                placeholder="Misal: Project Manager"
                            />
                            {errors.signed_by_role && (
                                <p className="text-sm text-destructive">{errors.signed_by_role}</p>
                            )}
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="signature">Gambar Tanda Tangan (opsional)</Label>
                        <Input
                            id="signature"
                            type="file"
                            accept="image/png,image/jpeg"
                            onChange={(e) => set('signature', e.target.files?.[0] ?? null)}
                        />
                        {report?.signature_url && !data.signature && (
                            <img
                                src={report.signature_url}
                                alt="Tanda tangan tersimpan"
                                className="h-16 w-auto rounded border bg-white object-contain p-1"
                            />
                        )}
                        {errors.signature && (
                            <p className="text-sm text-destructive">{errors.signature}</p>
                        )}
                    </div>
                </CardContent>
            </Card>

            <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={() => router.visit('/maintenance-reports')}>
                    Batal
                </Button>
                <Button onClick={submit} disabled={processing}>
                    {processing ? 'Menyimpan…' : isEdit ? 'Simpan Perubahan' : 'Simpan Laporan'}
                </Button>
            </div>
        </div>
    );
}
