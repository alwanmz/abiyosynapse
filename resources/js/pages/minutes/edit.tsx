import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { ai } from '@/lib/ai';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { IconFile, IconLoader2, IconMicrophone, IconPaperclip, IconPlayerStopFilled, IconPlus, IconSparkles, IconTrash, IconUpload, IconWand } from '@tabler/icons-react';
import { useEffect, useRef, useState } from 'react';
import { useVoiceRecorder } from '../daily-logs/hooks/use-voice-recorder';

const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf',
    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
const MAX_FILE_SIZE = 10 * 1024 * 1024;
const MAX_FILE_COUNT = 10;

interface Project { id: number; name: string }
interface User { id: number; name: string }
interface Decision { text: string; owner_name: string; due_date: string }
interface MinuteAttachmentData { id: number; original_name: string; path: string; mime_type: string; size_bytes: number; url: string }

interface MinuteData {
    id: number;
    title: string;
    meeting_date: string;
    location: string | null;
    project_id: number | null;
    attendees: Array<{ name: string }> | null;
    agenda: string | null;
    raw_transcript: string | null;
    summary: string | null;
    decisions: Array<{ text: string; owner_name: string | null; due_date: string | null }> | null;
    attachments: MinuteAttachmentData[] | null;
}

interface Props {
    minute: MinuteData;
    projects: Project[];
    users: User[];
}

export default function MinutesEdit({ minute, projects, users }: Props) {
    const { ai: aiCfg } = usePage<SharedData>().props;
    const aiEnabled = !!aiCfg?.enabled;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Notulensi', href: '/minutes' },
        { title: minute.title, href: `/minutes/${minute.id}` },
        { title: 'Edit', href: `/minutes/${minute.id}/edit` },
    ];

    const formatDateForInput = (dateStr: string | null | undefined): string => {
        if (!dateStr) return '';
        return dateStr.split('T')[0].split(' ')[0];
    };

    const [form, setForm] = useState({
        title: minute.title,
        meeting_date: formatDateForInput(minute.meeting_date),
        location: minute.location ?? '',
        project_id: minute.project_id ? String(minute.project_id) : '',
        agenda: minute.agenda ?? '',
        raw_transcript: minute.raw_transcript ?? '',
        summary: minute.summary ?? '',
    });
    const [attendees, setAttendees] = useState<string[]>(minute.attendees?.map((a) => a.name) ?? []);
    const [attendeeInput, setAttendeeInput] = useState('');
    const [decisions, setDecisions] = useState<Decision[]>(
        minute.decisions?.map((d) => ({
            text: d.text,
            owner_name: d.owner_name ?? '',
            due_date: formatDateForInput(d.due_date),
        })) ?? []
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [aiBusy, setAiBusy] = useState<null | 'transcribe' | 'summarize' | 'extract'>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [newAttachments, setNewAttachments] = useState<File[]>([]);
    const [fileErrors, setFileErrors] = useState<string[]>([]);
    const [isDragging, setIsDragging] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const DRAFT_KEY = `minute_edit_draft_${minute.id}`;
    const [hasDraft, setHasDraft] = useState(false);

    useEffect(() => {
        try {
            const saved = localStorage.getItem(DRAFT_KEY);
            if (saved) {
                const parsed = JSON.parse(saved);
                if (parsed?.form && (
                    parsed.form.raw_transcript !== (minute.raw_transcript ?? '') ||
                    parsed.form.summary !== (minute.summary ?? '') ||
                    parsed.form.agenda !== (minute.agenda ?? '') ||
                    parsed.form.title !== minute.title
                )) {
                    setHasDraft(true);
                }
            }
        } catch {}
    }, [minute]);

    useEffect(() => {
        try {
            const draft = { form, attendees, decisions };
            localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
        } catch {}
    }, [form, attendees, decisions, minute.id]);

    const restoreDraft = () => {
        try {
            const saved = localStorage.getItem(DRAFT_KEY);
            if (saved) {
                const parsed = JSON.parse(saved);
                if (parsed.form) setForm(parsed.form);
                if (parsed.attendees) setAttendees(parsed.attendees);
                if (parsed.decisions) setDecisions(parsed.decisions);
                setHasDraft(false);
            }
        } catch {}
    };

    const discardDraft = () => {
        try { localStorage.removeItem(DRAFT_KEY); } catch {}
        setHasDraft(false);
    };

    const recorder = useVoiceRecorder({
        onStop: async (blob) => {
            setAiBusy('transcribe');
            try {
                const text = await ai.transcribe(blob, 'id-ID');
                setForm((prev) => ({
                    ...prev,
                    raw_transcript: prev.raw_transcript.trim()
                        ? `${prev.raw_transcript.trim()}\n${text.trim()}`
                        : text.trim(),
                }));
            } catch (e: any) {
                setErrors((prev) => ({ ...prev, ai: e?.message ?? 'Gagal transkrip' }));
            }
            setAiBusy(null);
        },
        maxSeconds: 300,
    });

    const handleSummarize = async () => {
        const source = form.raw_transcript.trim() || form.agenda.trim();
        if (!source || aiBusy) return;
        setAiBusy('summarize');
        try {
            const summary = await ai.summarize(source, 5);
            setForm((prev) => ({ ...prev, summary }));
        } catch (e: any) {
            setErrors((prev) => ({ ...prev, ai: e?.message ?? 'Gagal meringkas' }));
        }
        setAiBusy(null);
    };

    const handleExtractDecisions = async () => {
        const source = form.summary.trim() || form.raw_transcript.trim() || form.agenda.trim();
        if (!source || aiBusy) return;
        setAiBusy('extract');
        try {
            const extracted = await ai.extractDecisions(source);
            if (extracted.length > 0) {
                setDecisions((prev) => [
                    ...prev,
                    ...extracted.map((d) => ({
                        text: d.text,
                        owner_name: d.owner_name ?? '',
                        due_date: d.due_date ?? '',
                    })),
                ]);
            }
        } catch (e: any) {
            setErrors((prev) => ({ ...prev, ai: e?.message ?? 'Gagal ekstrak keputusan' }));
        }
        setAiBusy(null);
    };

    const addFiles = (files: File[]) => {
        const errs: string[] = [];
        const valid: File[] = [];
        for (const file of files) {
            if (!ALLOWED_MIME.includes(file.type)) { errs.push(`${file.name}: tipe tidak didukung.`); continue; }
            if (file.size > MAX_FILE_SIZE) { errs.push(`${file.name}: melebihi 10 MB.`); continue; }
            if (newAttachments.length + valid.length >= MAX_FILE_COUNT) { errs.push('Maksimal 10 file.'); break; }
            valid.push(file);
        }
        setFileErrors(errs);
        if (valid.length) setNewAttachments((prev) => [...prev, ...valid]);
    };

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        addFiles(Array.from(e.target.files ?? []));
        e.target.value = '';
    };

    const deleteExistingAttachment = (id: number) => {
        router.delete(`/minute-attachments/${id}`, { preserveScroll: true });
    };

    const addAttendee = () => {
        const name = attendeeInput.trim();
        if (name && !attendees.includes(name)) setAttendees((prev) => [...prev, name]);
        setAttendeeInput('');
    };

    const addDecision = () => setDecisions((prev) => [...prev, { text: '', owner_name: '', due_date: '' }]);
    const updateDecision = (idx: number, field: keyof Decision, value: string) =>
        setDecisions((prev) => prev.map((d, i) => (i === idx ? { ...d, [field]: value } : d)));
    const removeDecision = (idx: number) => setDecisions((prev) => prev.filter((_, i) => i !== idx));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setErrors({});
        setIsSubmitting(true);

        const payload: Record<string, any> = {
            _method: 'put',
            ...form,
            project_id: form.project_id || null,
            attendees: attendees.map((name) => ({ name })),
            decisions: decisions.filter((d) => d.text.trim()).map((d) => ({
                text: d.text.trim(),
                owner_name: d.owner_name.trim() || null,
                due_date: d.due_date || null,
            })),
        };
        if (newAttachments.length) payload.attachments = newAttachments;

        router.post(`/minutes/${minute.id}`, payload, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                try { localStorage.removeItem(DRAFT_KEY); } catch {}
            },
            onError: (errs) => { setErrors(errs); setIsSubmitting(false); },
            onFinish: () => setIsSubmitting(false),
        });
    };

    const isRecording = recorder.status === 'recording';
    const isProcessing = recorder.status === 'processing';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit: ${minute.title}`} />

            <div className="p-6">
                <form onSubmit={handleSubmit} className="mx-auto max-w-3xl space-y-6">
                    {hasDraft && (
                        <div className="flex items-center justify-between gap-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
                            <p className="text-sm font-medium">Terdapat draf editan tersimpan yang belum terkirim dari sesi sebelumnya.</p>
                            <div className="flex shrink-0 gap-2">
                                <Button type="button" size="sm" onClick={restoreDraft}>Pulihkan Draf</Button>
                                <Button type="button" variant="ghost" size="sm" onClick={discardDraft}>Abaikan</Button>
                            </div>
                        </div>
                    )}

                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Edit Notulensi</h1>
                    </div>

                    <Card>
                        <CardContent className="space-y-4 p-5">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="title">Judul Rapat <span className="text-destructive">*</span></Label>
                                    <Input
                                        id="title"
                                        value={form.title}
                                        onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))}
                                        required
                                    />
                                    {errors.title && <p className="text-sm text-destructive">{errors.title}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="meeting_date">Tanggal Rapat <span className="text-destructive">*</span></Label>
                                    <Input
                                        id="meeting_date"
                                        type="date"
                                        value={form.meeting_date}
                                        onChange={(e) => setForm((p) => ({ ...p, meeting_date: e.target.value }))}
                                        required
                                    />
                                    {errors.meeting_date && <p className="text-sm text-destructive">{errors.meeting_date}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="location">Lokasi</Label>
                                    <Input
                                        id="location"
                                        value={form.location}
                                        onChange={(e) => setForm((p) => ({ ...p, location: e.target.value }))}
                                    />
                                    {errors.location && <p className="text-sm text-destructive">{errors.location}</p>}
                                </div>

                                <div className="space-y-2 sm:col-span-2">
                                    <Label htmlFor="project">Proyek (opsional)</Label>
                                    <Select
                                        value={form.project_id}
                                        onValueChange={(v) => setForm((p) => ({ ...p, project_id: v === 'none' ? '' : v }))}
                                    >
                                        <SelectTrigger id="project">
                                            <SelectValue placeholder="Pilih proyek (opsional)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">— Tanpa proyek —</SelectItem>
                                            {projects.map((p) => (
                                                <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.project_id && <p className="text-sm text-destructive">{errors.project_id}</p>}
                                </div>
                            </div>

                            <Separator />

                            <div className="space-y-2">
                                <Label>Peserta</Label>
                                <div className="flex gap-2">
                                    <Input
                                        value={attendeeInput}
                                        onChange={(e) => setAttendeeInput(e.target.value)}
                                        onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addAttendee(); } }}
                                        placeholder="Ketik nama lalu Enter"
                                        list="users-list"
                                    />
                                    <datalist id="users-list">
                                        {users.map((u) => <option key={u.id} value={u.name} />)}
                                    </datalist>
                                    <Button type="button" variant="outline" size="icon" onClick={addAttendee}>
                                        <IconPlus className="h-4 w-4" />
                                    </Button>
                                </div>
                                {attendees.length > 0 && (
                                    <div className="flex flex-wrap gap-1.5 pt-1">
                                        {attendees.map((name) => (
                                            <span key={name} className="flex items-center gap-1 rounded-full bg-muted px-3 py-1 text-xs font-medium">
                                                {name}
                                                <button type="button" onClick={() => setAttendees((p) => p.filter((n) => n !== name))}>
                                                    <IconTrash className="h-3 w-3 text-muted-foreground hover:text-destructive" />
                                                </button>
                                            </span>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="agenda">Agenda</Label>
                                <Textarea id="agenda" value={form.agenda} onChange={(e) => setForm((p) => ({ ...p, agenda: e.target.value }))} rows={3} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="space-y-4 p-5">
                            <div className="flex items-center justify-between">
                                <p className="font-medium">Transkripsi &amp; Ringkasan</p>
                                <div className="flex gap-2">
                                    {recorder.supported && (
                                        <Button
                                            type="button"
                                            variant={isRecording ? 'destructive' : 'outline'}
                                            size="sm"
                                            onClick={() => isRecording ? recorder.stop() : void recorder.start()}
                                            disabled={!aiEnabled || isProcessing || !!aiBusy}
                                        >
                                            {isProcessing || aiBusy === 'transcribe'
                                                ? <><IconLoader2 className="mr-1.5 h-4 w-4 animate-spin" />Transkripsi...</>
                                                : isRecording
                                                    ? <><IconPlayerStopFilled className="mr-1.5 h-4 w-4" />Stop ({recorder.seconds}s)</>
                                                    : <><IconMicrophone className="mr-1.5 h-4 w-4" />Rekam</>
                                            }
                                        </Button>
                                    )}
                                </div>
                            </div>

                            {recorder.error && <p className="text-xs text-destructive">{recorder.error}</p>}
                            {errors.ai && <p className="text-xs text-destructive">{errors.ai}</p>}

                            <Textarea id="raw_transcript" value={form.raw_transcript} onChange={(e) => setForm((p) => ({ ...p, raw_transcript: e.target.value }))} rows={6} placeholder="Transkripsi atau catatan rapat" />

                            <div className="flex flex-wrap gap-2">
                                <Button type="button" variant="outline" size="sm" onClick={handleSummarize} disabled={!aiEnabled || !!aiBusy || (!form.raw_transcript.trim() && !form.agenda.trim())}>
                                    {aiBusy === 'summarize' ? <><IconLoader2 className="mr-1.5 h-4 w-4 animate-spin" />Meringkas...</> : <><IconSparkles className="mr-1.5 h-4 w-4" />Ringkas AI</>}
                                </Button>
                                <Button type="button" variant="outline" size="sm" onClick={handleExtractDecisions} disabled={!aiEnabled || !!aiBusy || (!form.raw_transcript.trim() && !form.summary.trim() && !form.agenda.trim())}>
                                    {aiBusy === 'extract' ? <><IconLoader2 className="mr-1.5 h-4 w-4 animate-spin" />Ekstrak...</> : <><IconWand className="mr-1.5 h-4 w-4" />Ekstrak Keputusan AI</>}
                                </Button>
                            </div>

                            <Textarea id="summary" value={form.summary} onChange={(e) => setForm((p) => ({ ...p, summary: e.target.value }))} rows={4} placeholder="Ringkasan rapat" />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="space-y-3 p-5">
                            <div className="flex items-center justify-between">
                                <p className="font-medium">Keputusan &amp; Action Items</p>
                                <Button type="button" variant="outline" size="sm" onClick={addDecision}>
                                    <IconPlus className="mr-1.5 h-4 w-4" />Tambah
                                </Button>
                            </div>

                            {decisions.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Belum ada keputusan.</p>
                            ) : decisions.map((d, idx) => (
                                <div key={idx} className="grid gap-2 rounded-lg border p-3">
                                    <div className="flex items-start gap-2">
                                        <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">{idx + 1}</span>
                                        <Textarea value={d.text} onChange={(e) => updateDecision(idx, 'text', e.target.value)} rows={2} className="flex-1" />
                                        <Button type="button" variant="ghost" size="icon" className="h-8 w-8 shrink-0" onClick={() => removeDecision(idx)}>
                                            <IconTrash className="h-4 w-4 text-destructive/60" />
                                        </Button>
                                    </div>
                                    <div className="ml-7 grid gap-2 sm:grid-cols-2">
                                        <Input value={d.owner_name} onChange={(e) => updateDecision(idx, 'owner_name', e.target.value)} placeholder="PIC" list="dec-users" />
                                        <datalist id="dec-users">{users.map((u) => <option key={u.id} value={u.name} />)}</datalist>
                                        <Input type="date" value={d.due_date} onChange={(e) => updateDecision(idx, 'due_date', e.target.value)} />
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="space-y-3 p-5">
                            <div className="flex items-center justify-between">
                                <p className="flex items-center gap-2 font-medium">
                                    <IconPaperclip className="h-4 w-4" />
                                    Lampiran
                                </p>
                                <Button type="button" variant="outline" size="sm" onClick={() => fileInputRef.current?.click()}>
                                    <IconUpload className="mr-1.5 h-4 w-4" />
                                    Tambah File
                                </Button>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    multiple
                                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx"
                                    className="hidden"
                                    onChange={handleFileSelect}
                                />
                            </div>

                            {minute.attachments && minute.attachments.length > 0 && (
                                <div className="space-y-1">
                                    <p className="text-xs font-medium text-muted-foreground">Lampiran tersimpan:</p>
                                    <div className="flex flex-wrap gap-2">
                                        {minute.attachments.map((att) => (
                                            <div key={att.id} className="flex items-center gap-1.5 rounded-full border bg-muted px-3 py-1 text-xs">
                                                <IconFile className="h-3 w-3 shrink-0" />
                                                <span className="max-w-[160px] truncate">{att.original_name}</span>
                                                <button
                                                    type="button"
                                                    onClick={() => deleteExistingAttachment(att.id)}
                                                    title="Hapus lampiran"
                                                >
                                                    <IconTrash className="h-3 w-3 text-muted-foreground hover:text-destructive" />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div
                                className={`rounded-lg border-2 border-dashed p-4 text-center transition-colors ${isDragging ? 'border-primary bg-primary/5' : 'border-muted-foreground/30'}`}
                                onDragEnter={(e) => { e.preventDefault(); setIsDragging(true); }}
                                onDragLeave={(e) => { e.preventDefault(); setIsDragging(false); }}
                                onDragOver={(e) => e.preventDefault()}
                                onDrop={(e) => { e.preventDefault(); setIsDragging(false); addFiles(Array.from(e.dataTransfer.files)); }}
                            >
                                <p className="text-sm text-muted-foreground">Seret & lepas file baru di sini</p>
                                <p className="mt-1 text-xs text-muted-foreground">JPG, PNG, GIF, WEBP, PDF, DOC, DOCX — maks 10 MB/file</p>
                            </div>

                            {fileErrors.length > 0 && (
                                <ul className="space-y-1">
                                    {fileErrors.map((err, i) => <li key={i} className="text-xs text-destructive">{err}</li>)}
                                </ul>
                            )}

                            {newAttachments.length > 0 && (
                                <div className="flex flex-wrap gap-2">
                                    {newAttachments.map((file, i) => (
                                        <div key={i} className="flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/5 px-3 py-1 text-xs">
                                            <IconFile className="h-3 w-3 shrink-0" />
                                            <span className="max-w-[160px] truncate">{file.name}</span>
                                            <button type="button" onClick={() => setNewAttachments((prev) => prev.filter((_, j) => j !== i))}>
                                                <IconTrash className="h-3 w-3 text-muted-foreground hover:text-destructive" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <div className="flex gap-3">
                        <Button type="submit" disabled={isSubmitting}>{isSubmitting ? 'Menyimpan...' : 'Simpan Perubahan'}</Button>
                        <Button type="button" variant="outline" onClick={() => router.visit(`/minutes/${minute.id}`)}>Batal</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
