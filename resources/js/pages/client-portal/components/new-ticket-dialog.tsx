import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { csrfHeaders } from '@/lib/ai';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { type PortalQuota } from '../tickets/types';

/** Same three categories the Telegram intake bot offers. */
const CATEGORIES = [
    { value: 'bug', label: 'Bug / Kendala' },
    { value: 'feature', label: 'Permintaan Baru' },
    { value: 'task', label: 'Lainnya' },
] as const;

interface DuplicateHit {
    found: boolean;
    ticket_number: string | null;
    title: string | null;
    suggestion: string | null;
}

interface Props {
    quota: PortalQuota;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/**
 * Client-facing ticket intake. "Permintaan Baru" consumes the monthly quota;
 * bug reports and "Lainnya" are unlimited. Before submitting a request we ask
 * the AI copilot whether the same thing was already solved, so the client does
 * not burn quota on a duplicate.
 */
export default function NewTicketDialog({ quota, open, onOpenChange }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<{
            type: string;
            title: string;
            description: string;
            attachments: File[];
        }>({
            type: 'bug',
            title: '',
            description: '',
            attachments: [],
        });

    const [duplicate, setDuplicate] = useState<DuplicateHit | null>(null);
    const [checking, setChecking] = useState(false);

    const usesQuota = data.type === 'feature';
    const quotaExhausted =
        usesQuota && !quota.unlimited && (quota.remaining ?? 0) <= 0;

    const close = (next: boolean) => {
        if (!next) {
            reset();
            clearErrors();
            setDuplicate(null);
        }
        onOpenChange(next);
    };

    const submit = () => {
        post('/portal/tickets', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => close(false),
        });
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (processing || quotaExhausted) return;

        // Only worth asking once, and only for requests with enough context.
        if (!duplicate && usesQuota && data.description.trim().length >= 20) {
            setChecking(true);
            try {
                const res = await fetch('/portal/tickets/check-duplicate', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        ...csrfHeaders(),
                    },
                    body: JSON.stringify({ description: data.description }),
                });
                if (res.ok) {
                    const hit: DuplicateHit = await res.json();
                    if (hit.found) {
                        setDuplicate(hit);
                        return;
                    }
                }
            } catch {
                // AI is best-effort — never block the client from reporting.
            } finally {
                setChecking(false);
            }
        }

        submit();
    };

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Buat Tiket Baru</DialogTitle>
                    <DialogDescription>
                        Jelaskan kendala atau permintaan Anda. Tim kami akan
                        menindaklanjuti setelah tiket masuk.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="type">Kategori</Label>
                        <Select
                            value={data.type}
                            onValueChange={(value) => {
                                setData('type', value);
                                setDuplicate(null);
                            }}
                        >
                            <SelectTrigger id="type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {CATEGORIES.map((c) => (
                                    <SelectItem key={c.value} value={c.value}>
                                        {c.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {usesQuota && (
                            <div className="flex items-center gap-2">
                                <Badge variant="secondary">
                                    {quota.unlimited
                                        ? 'Request: Unlimited'
                                        : `Sisa request: ${quota.remaining}/${quota.limit}`}
                                </Badge>
                                <span className="text-xs text-muted-foreground">
                                    Laporan bug tidak memotong kuota.
                                </span>
                            </div>
                        )}
                        {errors.type && (
                            <p className="text-sm text-destructive">
                                {errors.type}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="title">Judul (opsional)</Label>
                        <Input
                            id="title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder="Kosongkan untuk diambil dari deskripsi"
                        />
                        {errors.title && (
                            <p className="text-sm text-destructive">
                                {errors.title}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="description">Deskripsi</Label>
                        <Textarea
                            id="description"
                            rows={5}
                            required
                            value={data.description}
                            onChange={(e) => {
                                setData('description', e.target.value);
                                setDuplicate(null);
                            }}
                            placeholder="Ceritakan detail kendala/permintaan, sertakan menu atau langkah yang terkait."
                        />
                        {errors.description && (
                            <p className="text-sm text-destructive">
                                {errors.description}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="attachments">
                            Lampiran (opsional, maks. 5 file)
                        </Label>
                        <Input
                            id="attachments"
                            type="file"
                            multiple
                            accept=".jpg,.jpeg,.png,.pdf"
                            onChange={(e) =>
                                setData(
                                    'attachments',
                                    Array.from(e.target.files ?? []),
                                )
                            }
                        />
                        {errors.attachments && (
                            <p className="text-sm text-destructive">
                                {errors.attachments}
                            </p>
                        )}
                    </div>

                    {duplicate?.found && (
                        <div className="rounded-md border bg-muted/30 p-3">
                            <p className="text-sm font-medium">
                                Sepertinya mirip dengan{' '}
                                {duplicate.ticket_number}
                                {duplicate.title ? ` — ${duplicate.title}` : ''}
                            </p>
                            {duplicate.suggestion && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {duplicate.suggestion}
                                </p>
                            )}
                            <p className="mt-2 text-xs text-muted-foreground">
                                Tetap kirim jika kendala Anda berbeda.
                            </p>
                        </div>
                    )}

                    {quotaExhausted && (
                        <p className="text-sm text-destructive">
                            Kuota permintaan baru Anda sudah habis. Silakan
                            tunggu kuota bulan berikutnya atau hubungi tim kami.
                        </p>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => close(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || checking || quotaExhausted}
                        >
                            {duplicate?.found ? 'Tetap Kirim' : 'Kirim Tiket'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
