import { RichTextEditor } from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type {
    Guidebook,
    GuidebookCategory,
    GuidebookContentType,
    GuidebookFormData,
} from '@/types/guidebook';
import { router } from '@inertiajs/react';
import { GripVertical, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { CONTENT_TYPES } from '../constants/content-types';

const emptyForm: GuidebookFormData = {
    category_id: '',
    title: '',
    description: '',
    content_type: 'native',
    embed_url: '',
    content: '',
    checklist_items: [{ text: '', note: '' }],
    is_pinned: false,
    pdf: null,
    attachments: [],
};

const initialFormData = (
    guidebook: Guidebook | null | undefined,
    categories: GuidebookCategory[],
): GuidebookFormData =>
    guidebook
        ? {
              category_id: String(guidebook.category?.id ?? ''),
              title: guidebook.title,
              description: guidebook.description ?? '',
              content_type: guidebook.content_type,
              embed_url: guidebook.embed_url ?? '',
              content: guidebook.content ?? '',
              checklist_items: guidebook.checklist_items.length
                  ? guidebook.checklist_items
                  : [{ text: '', note: '' }],
              is_pinned: guidebook.is_pinned,
              pdf: null,
              attachments: [],
          }
        : { ...emptyForm, category_id: String(categories[0]?.id ?? '') };

export function GuidebookFormDialog({
    open,
    onOpenChange,
    categories,
    guidebook,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    categories: GuidebookCategory[];
    /** Diisi saat mode edit; kosong berarti mode buat baru. */
    guidebook?: Guidebook | null;
}) {
    // Isi form di-remount tiap dialog dibuka (lewat key) sehingga state awal
    // selalu segar tanpa perlu menyetel state di dalam effect.
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {open && (
                <GuidebookFormBody
                    key={guidebook?.id ?? 'new'}
                    onOpenChange={onOpenChange}
                    categories={categories}
                    guidebook={guidebook}
                />
            )}
        </Dialog>
    );
}

function GuidebookFormBody({
    onOpenChange,
    categories,
    guidebook,
}: {
    onOpenChange: (open: boolean) => void;
    categories: GuidebookCategory[];
    guidebook?: Guidebook | null;
}) {
    const [data, setData] = useState<GuidebookFormData>(() =>
        initialFormData(guidebook, categories),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const isEdit = Boolean(guidebook);

    const set = <K extends keyof GuidebookFormData>(key: K, value: GuidebookFormData[K]) =>
        setData((current) => ({ ...current, [key]: value }));

    const updateChecklistItem = (index: number, key: 'text' | 'note', value: string) =>
        setData((current) => ({
            ...current,
            checklist_items: current.checklist_items.map((item, i) =>
                i === index ? { ...item, [key]: value } : item,
            ),
        }));

    const submit = () => {
        setProcessing(true);
        setErrors({});

        // eslint-disable-next-line @typescript-eslint/no-explicit-any -- payload multipart campuran File & skalar, sama seperti form tiket.
        const payload: Record<string, any> = {
            category_id: data.category_id,
            title: data.title,
            description: data.description,
            content_type: data.content_type,
            is_pinned: data.is_pinned ? 1 : 0,
        };

        if (data.content_type === 'embed' || data.content_type === 'video') {
            payload.embed_url = data.embed_url;
        }
        if (data.content_type === 'native') {
            payload.content = data.content;
        }
        if (data.content_type === 'checklist') {
            payload.checklist_items = data.checklist_items.filter((item) => item.text.trim() !== '');
        }
        if (data.content_type === 'pdf' && data.pdf) {
            payload.pdf = data.pdf;
        }
        if (data.attachments.length > 0) {
            payload.attachments = data.attachments;
        }

        // PUT multipart tidak diurai PHP, jadi update dikirim sebagai POST
        // dengan method spoofing — sama seperti modul tiket.
        if (isEdit) {
            payload._method = 'put';
        }

        router.post(isEdit ? `/guidebooks/${guidebook!.id}` : '/guidebooks', payload, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onError: (formErrors) => setErrors(formErrors as Record<string, string>),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Ubah Guidebook' : 'Tambah Guidebook'}</DialogTitle>
                    <DialogDescription>
                        Pilih tipe konten, lalu lengkapi isian sesuai tipe yang dipilih.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-2">
                    <div className="grid gap-2">
                        <Label htmlFor="title">Judul</Label>
                        <Input
                            id="title"
                            value={data.title}
                            onChange={(e) => set('title', e.target.value)}
                            placeholder="Misal: SOP Rilis Maintenance"
                        />
                        {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Deskripsi singkat</Label>
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={(e) => set('description', e.target.value)}
                            placeholder="Ringkasan satu kalimat untuk kartu di daftar guidebook."
                            rows={2}
                        />
                        {errors.description && (
                            <p className="text-sm text-destructive">{errors.description}</p>
                        )}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label>Kategori</Label>
                            <Select
                                value={data.category_id}
                                onValueChange={(value) => set('category_id', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((category) => (
                                        <SelectItem key={category.id} value={String(category.id)}>
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.category_id && (
                                <p className="text-sm text-destructive">{errors.category_id}</p>
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label>Tipe konten</Label>
                            <Select
                                value={data.content_type}
                                onValueChange={(value) =>
                                    set('content_type', value as GuidebookContentType)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {CONTENT_TYPES.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.content_type && (
                                <p className="text-sm text-destructive">{errors.content_type}</p>
                            )}
                        </div>
                    </div>

                    {(data.content_type === 'embed' || data.content_type === 'video') && (
                        <div className="grid gap-2">
                            <Label htmlFor="embed_url">
                                {data.content_type === 'video' ? 'URL Video' : 'URL Situs'}
                            </Label>
                            <Input
                                id="embed_url"
                                value={data.embed_url}
                                onChange={(e) => set('embed_url', e.target.value)}
                                placeholder={
                                    data.content_type === 'video'
                                        ? 'https://youtube.com/watch?v=... atau tautan Google Drive'
                                        : 'https://sites.google.com/...'
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {data.content_type === 'video'
                                    ? 'Mendukung tautan YouTube dan Google Drive.'
                                    : 'Pastikan situs mengizinkan penyematan (iframe).'}
                            </p>
                            {errors.embed_url && (
                                <p className="text-sm text-destructive">{errors.embed_url}</p>
                            )}
                        </div>
                    )}

                    {data.content_type === 'pdf' && (
                        <div className="grid gap-2">
                            <Label htmlFor="pdf">Berkas PDF</Label>
                            <Input
                                id="pdf"
                                type="file"
                                accept="application/pdf"
                                onChange={(e) => set('pdf', e.target.files?.[0] ?? null)}
                            />
                            {isEdit && guidebook?.pdf_url && !data.pdf && (
                                <p className="text-xs text-muted-foreground">
                                    Berkas saat ini akan dipertahankan bila tidak diganti.
                                </p>
                            )}
                            {errors.pdf && <p className="text-sm text-destructive">{errors.pdf}</p>}
                        </div>
                    )}

                    {data.content_type === 'native' && (
                        <div className="grid gap-2">
                            <Label>Isi artikel</Label>
                            <RichTextEditor
                                value={data.content}
                                onChange={(html) => set('content', html)}
                                placeholder="Tulis panduan di sini…"
                            />
                            {errors.content && (
                                <p className="text-sm text-destructive">{errors.content}</p>
                            )}
                        </div>
                    )}

                    {data.content_type === 'checklist' && (
                        <div className="grid gap-2">
                            <Label>Langkah checklist</Label>
                            <div className="flex flex-col gap-2">
                                {data.checklist_items.map((item, index) => (
                                    <div
                                        key={index}
                                        className="flex items-start gap-2 rounded-md border p-2"
                                    >
                                        <GripVertical className="mt-2 h-4 w-4 shrink-0 text-muted-foreground" />
                                        <div className="grid flex-1 gap-2">
                                            <Input
                                                value={item.text}
                                                onChange={(e) =>
                                                    updateChecklistItem(index, 'text', e.target.value)
                                                }
                                                placeholder={`Langkah ${index + 1}`}
                                            />
                                            <Input
                                                value={item.note ?? ''}
                                                onChange={(e) =>
                                                    updateChecklistItem(index, 'note', e.target.value)
                                                }
                                                placeholder="Catatan (opsional)"
                                                className="text-sm"
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="mt-1 h-8 w-8 shrink-0"
                                            disabled={data.checklist_items.length === 1}
                                            onClick={() =>
                                                set(
                                                    'checklist_items',
                                                    data.checklist_items.filter((_, i) => i !== index),
                                                )
                                            }
                                            aria-label="Hapus langkah"
                                        >
                                            <Trash2 className="h-4 w-4 text-destructive/70" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="w-fit"
                                onClick={() =>
                                    set('checklist_items', [
                                        ...data.checklist_items,
                                        { text: '', note: '' },
                                    ])
                                }
                            >
                                <Plus className="mr-1.5 h-4 w-4" />
                                Tambah langkah
                            </Button>
                            {errors.checklist_items && (
                                <p className="text-sm text-destructive">{errors.checklist_items}</p>
                            )}
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="attachments">Lampiran pendukung (opsional)</Label>
                        <Input
                            id="attachments"
                            type="file"
                            multiple
                            onChange={(e) => set('attachments', Array.from(e.target.files ?? []))}
                        />
                        {errors.attachments && (
                            <p className="text-sm text-destructive">{errors.attachments}</p>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="is_pinned"
                            checked={data.is_pinned}
                            onCheckedChange={(checked) => set('is_pinned', checked === true)}
                        />
                        <Label htmlFor="is_pinned" className="font-normal">
                            Sematkan di bagian atas daftar
                        </Label>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                        Batal
                    </Button>
                    <Button onClick={submit} disabled={processing}>
                        {processing ? 'Menyimpan…' : isEdit ? 'Simpan Perubahan' : 'Simpan'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </>
    );
}
