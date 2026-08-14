import { ConfirmDialog } from '@/components/confirm-dialog';
import { ListHeader } from '@/components/list-header';
import { UserAvatar } from '@/components/user-avatar';
import { Badge } from '@/components/ui/badge';
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
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { ai } from '@/lib/ai';
import { BreadcrumbItem, DailyLog, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    IconCalendar,
    IconClock,
    IconFileTypePdf,
    IconMicrophone,
    IconNotes,
    IconPhoto,
    IconPlayerStopFilled,
    IconRobot,
    IconSparkles,
    IconTicket,
    IconTrash,
    IconUser,
    IconUsers,
} from '@tabler/icons-react';
import { useState } from 'react';
import { FilePreviewDialog } from '../tickets/components/file-preview-dialog';
import { AttachmentUpload } from './components/attachment-upload';
import { EnergySlider } from './components/energy-slider';
import { Mood, MoodPicker } from './components/mood-picker';
import { TagInput } from './components/tag-input';
import { LogReactions, ReactionItem } from './components/log-reactions';
import { useVoiceRecorder } from './hooks/use-voice-recorder';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Catatan Harian', href: '/daily-logs' }];

const CATEGORIES: { value: string; label: string; color: string }[] = [
    { value: 'development', label: 'Development', color: 'bg-blue-100 text-blue-700' },
    { value: 'meeting', label: 'Meeting / Rapat', color: 'bg-purple-100 text-purple-700' },
    { value: 'support', label: 'Support / Maintenance', color: 'bg-amber-100 text-amber-700' },
    { value: 'documentation', label: 'Dokumentasi', color: 'bg-emerald-100 text-emerald-700' },
    { value: 'research', label: 'Riset / Analisa', color: 'bg-cyan-100 text-cyan-700' },
    { value: 'deployment', label: 'Deployment / Rilis', color: 'bg-rose-100 text-rose-700' },
    { value: 'other', label: 'Lainnya', color: 'bg-slate-100 text-slate-700' },
];

const TAG_SUGGESTIONS = ['urgent', 'refactor', 'bugfix', 'review', 'meeting', 'planning'];

const catMeta = (value?: string | null) => CATEGORIES.find((c) => c.value === value);

type DailyLogScope = 'self' | 'team';

interface DailyLogWithExtras extends DailyLog {
    log_number?: string | null;
    mood?: string | null;
    energy_level?: number | null;
    tags?: string[] | null;
    duration_minutes?: number | null;
    attachments?: Array<{
        id: number;
        original_name: string;
        url: string;
        mime_type: string;
        size_bytes: number;
    }>;
    user?: {
        id: number;
        name: string;
        avatar_path?: string | null;
    } | null;
    reactions?: ReactionItem[];
}

interface Props {
    logs: DailyLogWithExtras[];
    selectedDate: string;
    selectedScope?: DailyLogScope;
    canViewTeamLogs?: boolean;
    moods: Mood[];
}

export default function DailyLogsPage({
    logs,
    selectedDate,
    selectedScope = 'self',
    canViewTeamLogs = false,
    moods,
}: Props) {
    const { ai: aiCfg, auth } = usePage<SharedData>().props;
    const aiEnabled = !!aiCfg?.enabled;
    const currentScope: DailyLogScope = canViewTeamLogs && selectedScope === 'team' ? 'team' : 'self';

    // Inertia form (note: we'll forceFormData on submit because of attachments).
    const { data, setData, post, processing, reset, errors, clearErrors } = useForm<{
        category: string;
        description: string;
        log_date: string;
        mood: string | null;
        energy_level: number | null;
        duration_minutes: number | null;
        tags: string[];
        attachments: File[];
    }>({
        category: 'development',
        description: '',
        log_date: selectedDate || new Date().toISOString().split('T')[0],
        mood: null,
        energy_level: null,
        duration_minutes: null,
        tags: [],
        attachments: [],
    });

    const [deletingLogId, setDeletingLogId] = useState<number | null>(null);
    const [aiBusy, setAiBusy] = useState<null | 'transcribe' | 'polish'>(null);
    const [aiError, setAiError] = useState<string | null>(null);

    const recorder = useVoiceRecorder({
        onStop: async (blob) => {
            setAiBusy('transcribe');
            setAiError(null);
            try {
                const text = await ai.transcribe(blob, 'id-ID');
                const merged = data.description.trim()
                    ? `${data.description.trim()}\n${text.trim()}`
                    : text.trim();
                setData('description', merged);
            } catch (e: any) {
                setAiError(humanizeAiError(e?.message));
            }
            setAiBusy(null);
            // Note: we don't re-throw so the hook stays in 'idle' state
            // and the voice button can be used again immediately.
        },
        maxSeconds: 120,
    });

    const handlePolish = async () => {
        if (!data.description.trim() || aiBusy) return;
        setAiBusy('polish');
        setAiError(null);
        try {
            const polished = await ai.polish(
                data.description,
                `kategori: ${data.category}; tag: ${data.tags.join(', ')}`,
            );
            setData('description', polished);
        } catch (e: any) {
            setAiError(humanizeAiError(e?.message));
        } finally {
            setAiBusy(null);
        }
    };

    const handleVoiceClick = () => {
        setAiError(null);
        if (recorder.status === 'error') {
            recorder.dismissError();
            return;
        }
        if (recorder.status === 'recording') {
            recorder.stop();
        } else {
            recorder.start();
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        clearErrors();
        post('/daily-logs', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset('description', 'tags', 'attachments', 'duration_minutes', 'mood', 'energy_level');
            },
        });
    };

    const handleDateChange = (date: string) => {
        router.get('/daily-logs', { date, scope: currentScope }, { preserveState: true, preserveScroll: true });
    };

    const handleScopeChange = (scope: DailyLogScope) => {
        if (scope === currentScope) return;

        router.get('/daily-logs', { date: selectedDate, scope }, { preserveState: true, preserveScroll: true });
    };

    const confirmDeleteLog = () => {
        if (deletingLogId === null) return;

        const query = new URLSearchParams({ date: selectedDate, scope: currentScope });

        router.delete(`/daily-logs/${deletingLogId}?${query.toString()}`, {
            preserveScroll: true,
            onFinish: () => setDeletingLogId(null),
        });
    };

    const isRecording = recorder.status === 'recording';
    const isTranscribing = aiBusy === 'transcribe';
    const isPolishing = aiBusy === 'polish';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Catatan Harian" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Catatan Harian</h1>
                        <p className="text-muted-foreground">
                            Catat aktivitas, mood, dan energi harianmu. Bisa diketik, diunggah lampirannya,
                            atau pakai suara langsung.
                        </p>
                    </div>

                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <div className="flex items-center gap-2 rounded-md border bg-background p-1 shadow-sm">
                            <Label htmlFor="date-filter" className="sr-only">
                                Filter Tanggal
                            </Label>
                            <IconCalendar className="ml-2 h-4 w-4 text-muted-foreground" />
                            <Input
                                id="date-filter"
                                type="date"
                                className="w-40 border-0 focus-visible:ring-0"
                                value={selectedDate}
                                onChange={(e) => handleDateChange(e.target.value)}
                            />
                        </div>
                    </div>
                </div>

                {/* Add Log Form (full width on top, 2-column inner grid on wider screens) */}
                <Card className="overflow-hidden p-0">
                    <ListHeader
                        title="Tambah Catatan Aktivitas"
                        description="Lengkapi mood, energi, dan kategori"
                    />
                    <CardContent className="p-5">
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* TOP: Description (the main writing area - most important) */}
                            <div className="space-y-3">
                                <Label htmlFor="description" className="text-base font-semibold">
                                    Deskripsi Kegiatan
                                </Label>
                                <Textarea
                                    id="description"
                                    placeholder="Jelaskan apa yang dikerjakan, hasil, dan kendala (di luar tiket)..."
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    rows={7}
                                    required
                                    className="min-h-[180px] resize-y"
                                />

                                {isRecording && (
                                    <p className="flex items-center gap-1 text-xs text-blue-600">
                                        <span className="h-2 w-2 animate-pulse rounded-full bg-rose-600" />
                                        Merekam... bicara dalam Bahasa Indonesia. Klik Stop bila selesai.
                                    </p>
                                )}
                                {recorder.error && (
                                    <p className="text-xs text-destructive">{recorder.error}</p>
                                )}
                                {aiError && <p className="text-xs text-destructive">{aiError}</p>}
                                {errors.description && (
                                    <p className="text-sm text-destructive">{errors.description}</p>
                                )}

                                {/* AI Buttons - below textarea */}
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant={isRecording ? 'destructive' : 'outline'}
                                        size="sm"
                                        className="gap-1.5"
                                        onClick={handleVoiceClick}
                                        disabled={
                                            !aiEnabled ||
                                            !recorder.supported ||
                                            isTranscribing ||
                                            isPolishing
                                        }
                                        title={
                                            !aiEnabled
                                                ? 'AI belum diaktifkan di server'
                                                : !recorder.supported
                                                  ? 'Browser tidak mendukung perekaman'
                                                  : isRecording
                                                    ? 'Berhenti merekam'
                                                    : 'Rekam suara'
                                        }
                                    >
                                        {isRecording ? (
                                            <>
                                                <IconPlayerStopFilled size={14} /> Stop ({recorder.seconds}s)
                                            </>
                                        ) : isTranscribing ? (
                                            <>
                                                <IconRobot size={14} className="animate-pulse" /> Mentranskrip...
                                            </>
                                        ) : (
                                            <>
                                                <IconMicrophone size={14} /> Voice
                                            </>
                                        )}
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="gap-1.5"
                                        onClick={handlePolish}
                                        disabled={
                                            !aiEnabled ||
                                            !data.description.trim() ||
                                            isPolishing ||
                                            isTranscribing ||
                                            isRecording
                                        }
                                        title={
                                            !aiEnabled
                                                ? 'AI belum diaktifkan di server'
                                                : 'Rapikan otomatis pakai AI'
                                        }
                                    >
                                        {isPolishing ? (
                                            <>
                                                <IconSparkles size={14} className="animate-pulse" /> Merapikan...
                                            </>
                                        ) : (
                                            <>
                                                <IconSparkles size={14} /> Rapihin
                                            </>
                                        )}
                                    </Button>

                                    {!aiEnabled && (
                                        <span className="text-xs text-muted-foreground">
                                            AI belum aktif di server
                                        </span>
                                    )}
                                </div>
                            </div>

                            <Separator />

                            {/* MIDDLE: Meta fields in responsive grid */}
                            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                                {/* Energy Level */}
                                <div className="lg:col-span-2">
                                    <EnergySlider
                                        value={data.energy_level}
                                        onChange={(v) => setData('energy_level', v)}
                                    />
                                </div>

                                {/* Mood */}
                                <div>
                                    <MoodPicker
                                        moods={moods}
                                        value={data.mood}
                                        onChange={(v) => setData('mood', v)}
                                    />
                                </div>

                                {/* Category */}
                                <div className="space-y-2">
                                    <Label htmlFor="category">Kategori</Label>
                                    <Select
                                        value={data.category}
                                        onValueChange={(v) => setData('category', v)}
                                    >
                                        <SelectTrigger id="category">
                                            <SelectValue placeholder="Pilih kategori" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {CATEGORIES.map((c) => (
                                                <SelectItem key={c.value} value={c.value}>
                                                    {c.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.category && (
                                        <p className="text-sm text-destructive">{errors.category}</p>
                                    )}
                                </div>

                                {/* Duration */}
                                <div className="space-y-2">
                                    <Label htmlFor="duration_minutes">
                                        Durasi <span className="text-muted-foreground">(menit)</span>
                                    </Label>
                                    <div className="relative">
                                        <IconClock className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            id="duration_minutes"
                                            type="number"
                                            min={1}
                                            max={1440}
                                            inputMode="numeric"
                                            placeholder="Mis. 90"
                                            className="pl-8"
                                            value={data.duration_minutes ?? ''}
                                            onChange={(e) =>
                                                setData(
                                                    'duration_minutes',
                                                    e.target.value === ''
                                                        ? null
                                                        : Math.min(
                                                              1440,
                                                              Math.max(1, parseInt(e.target.value, 10) || 0),
                                                          ),
                                                )
                                            }
                                        />
                                    </div>
                                </div>

                                {/* Tags */}
                                <div className="md:col-span-2 lg:col-span-1">
                                    <TagInput
                                        value={data.tags}
                                        onChange={(v) => setData('tags', v)}
                                        suggestions={TAG_SUGGESTIONS}
                                        max={10}
                                    />
                                </div>
                            </div>

                            <Separator />

                            {/* BOTTOM: Attachment + Submit */}
                            <div className="space-y-4">
                                <AttachmentUpload
                                    files={data.attachments}
                                    onChange={(files) => setData('attachments', files)}
                                />
                                {errors.attachments && (
                                    <p className="text-sm text-destructive">{errors.attachments}</p>
                                )}

                                <Button
                                    type="submit"
                                    className="w-full md:w-auto md:px-10"
                                    size="lg"
                                    disabled={processing || isRecording || isTranscribing}
                                >
                                    {processing ? 'Menyimpan...' : 'Simpan Catatan'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Logs List (full width below the form) */}
                <Card className="overflow-hidden p-0">
                    <ListHeader
                        title={`Log Aktivitas ${currentScope === 'team' ? 'Tim' : 'Saya'} — ${new Date(selectedDate).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' })}`}
                        action={
                            <div className="flex items-center gap-2">
                                {canViewTeamLogs && (
                                    <div className="flex items-center gap-1 rounded-md bg-white/10 p-0.5">
                                        <button
                                            type="button"
                                            className={`flex h-7 items-center gap-1.5 rounded px-2.5 text-sm font-medium transition-all ${
                                                currentScope === 'self'
                                                    ? 'bg-white text-gray-900'
                                                    : 'text-white/80 hover:bg-white/20 hover:text-white'
                                            }`}
                                            onClick={() => handleScopeChange('self')}
                                        >
                                            <IconUser className="h-3.5 w-3.5" />
                                            Saya
                                        </button>
                                        <button
                                            type="button"
                                            className={`flex h-7 items-center gap-1.5 rounded px-2.5 text-sm font-medium transition-all ${
                                                currentScope === 'team'
                                                    ? 'bg-white text-gray-900'
                                                    : 'text-white/80 hover:bg-white/20 hover:text-white'
                                            }`}
                                            onClick={() => handleScopeChange('team')}
                                        >
                                            <IconUsers className="h-3.5 w-3.5" />
                                            Tim
                                        </button>
                                    </div>
                                )}
                                <Badge
                                    variant="secondary"
                                    className="bg-white/20 font-mono text-white hover:bg-white/30"
                                >
                                    {logs.length} catatan
                                </Badge>
                            </div>
                        }
                    />
                    <CardContent className="p-0">
                        <div className="divide-y">
                            {logs.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-12 text-muted-foreground">
                                    <IconCalendar className="mb-2 h-12 w-12 opacity-20" />
                                    <p>{currentScope === 'team' ? 'Belum ada catatan tim untuk tanggal ini.' : 'Belum ada catatan untuk tanggal ini.'}</p>
                                </div>
                            ) : (
                                logs.map((log) => (
                                    <LogRow
                                        key={log.id}
                                        log={log}
                                        moods={moods}
                                        showUser={currentScope === 'team'}
                                        currentUserId={auth.user?.id}
                                        onRequestDelete={() => setDeletingLogId(log.id)}
                                    />
                                ))
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <ConfirmDialog
                open={deletingLogId !== null}
                onOpenChange={(o) => !o && setDeletingLogId(null)}
                title="Hapus catatan harian?"
                description="Catatan ini beserta lampirannya akan dihapus permanen."
                confirmLabel="Ya, Hapus"
                onConfirm={confirmDeleteLog}
            />
        </AppLayout>
    );
}

const URL_REGEX = /https?:\/\/[^\s<>"{}|\\^`[\]]+/g;

function renderDescription(text: string) {
    const nodes: React.ReactNode[] = [];
    let last = 0;
    URL_REGEX.lastIndex = 0;
    let match: RegExpExecArray | null;
    while ((match = URL_REGEX.exec(text)) !== null) {
        if (match.index > last) nodes.push(text.slice(last, match.index));
        const url = match[0];
        nodes.push(
            <a
                key={match.index}
                href={url}
                target="_blank"
                rel="noopener noreferrer"
                className="break-all text-primary underline hover:text-primary/80"
                onClick={(e) => e.stopPropagation()}
            >
                {url}
            </a>,
        );
        last = match.index + url.length;
    }
    if (last < text.length) nodes.push(text.slice(last));
    return nodes;
}

/** A single log row with mood/energy/attachments preview. */
function LogRow({
    log,
    moods,
    showUser,
    currentUserId,
    onRequestDelete,
}: {
    log: DailyLogWithExtras;
    moods: Mood[];
    showUser: boolean;
    currentUserId?: number;
    onRequestDelete: () => void;
}) {
    const meta = catMeta(log.category);
    const moodMeta = log.mood ? moods.find((m) => m.value === log.mood) : null;
    const [previewFile, setPreviewFile] = useState<{
        name: string; path: string; url: string; size: number; mime_type: string;
    } | null>(null);
    const [previewOpen, setPreviewOpen] = useState(false);

    return (
        <div className="group flex items-start gap-4 p-4 transition-colors hover:bg-muted/50">
            <div className="relative mt-1 shrink-0">
                {log.user ? (
                    <UserAvatar user={log.user} className="h-9 w-9" />
                ) : (
                    <div
                        className={`rounded-full p-2 ${log.is_automated ? 'bg-blue-100 text-blue-600' : 'bg-orange-100 text-orange-600'}`}
                    >
                        {log.is_automated ? <IconRobot size={18} /> : <IconUser size={18} />}
                    </div>
                )}
                {log.is_automated && (
                    <div
                        className="absolute -bottom-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-blue-600 text-white ring-2 ring-background"
                        title="Otomatis"
                    >
                        <IconRobot size={10} />
                    </div>
                )}
            </div>

            <div className="flex-1 space-y-1.5">
                <div className="flex items-center justify-between">
                    <div className="flex flex-wrap items-center gap-2">
                        {log.log_number && (
                            <span className="rounded bg-blue-50 px-2 py-0.5 font-mono text-[10px] font-semibold text-blue-700 dark:bg-blue-950/40 dark:text-blue-200">
                                {log.log_number}
                            </span>
                        )}
                        {showUser && log.user && (
                            <span className="inline-flex items-center gap-1 rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                <IconUser size={12} />
                                {log.user.name}
                            </span>
                        )}
                        {meta && (
                            <span className={`rounded px-2 py-0.5 text-xs font-medium ${meta.color}`}>
                                {meta.label}
                            </span>
                        )}
                        {moodMeta && (
                            <span className="inline-flex items-center gap-1 rounded bg-muted px-2 py-0.5 text-xs">
                                <span>{moodMeta.emoji}</span>
                                <span className="text-muted-foreground">{moodMeta.label}</span>
                            </span>
                        )}
                        {typeof log.energy_level === 'number' && (
                            <span className="rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200">
                                ⚡ {log.energy_level}/10
                            </span>
                        )}
                        {typeof log.duration_minutes === 'number' && (
                            <span className="inline-flex items-center gap-1 rounded bg-muted px-2 py-0.5 text-[10px] text-muted-foreground">
                                <IconClock size={10} /> {log.duration_minutes}m
                            </span>
                        )}
                        <Separator orientation="vertical" className="h-3" />
                        <span className="text-xs text-muted-foreground">
                            {new Date(log.created_at).toLocaleTimeString('id-ID', {
                                hour: '2-digit',
                                minute: '2-digit',
                            })}
                        </span>
                    </div>

                    {!showUser && !log.is_automated && (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-destructive opacity-0 transition-opacity group-hover:opacity-100"
                            onClick={onRequestDelete}
                        >
                            <IconTrash size={16} />
                        </Button>
                    )}
                </div>

                <p className="whitespace-pre-wrap text-sm leading-relaxed">
                    {renderDescription(log.description)}
                </p>

                {log.tags && log.tags.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                        {log.tags.map((t) => (
                            <span
                                key={t}
                                className="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-700 dark:bg-blue-950/40 dark:text-blue-200"
                            >
                                #{t}
                            </span>
                        ))}
                    </div>
                )}

                {log.attachments && log.attachments.length > 0 && (
                    <div className="flex flex-wrap gap-2 pt-1">
                        {log.attachments.map((a) => {
                            const isImg = a.mime_type.startsWith('image/');
                            const open = () => {
                                setPreviewFile({
                                    name: a.original_name,
                                    path: a.url,
                                    url: a.url,
                                    size: a.size_bytes,
                                    mime_type: a.mime_type,
                                });
                                setPreviewOpen(true);
                            };
                            return isImg ? (
                                <button
                                    key={a.id}
                                    type="button"
                                    onClick={open}
                                    className="block h-16 w-16 overflow-hidden rounded-md border hover:border-blue-400 hover:shadow-md transition-all cursor-pointer"
                                    title={a.original_name}
                                >
                                    <img
                                        src={a.url}
                                        alt={a.original_name}
                                        className="h-full w-full object-cover"
                                        loading="lazy"
                                    />
                                </button>
                            ) : (
                                <button
                                    key={a.id}
                                    type="button"
                                    onClick={open}
                                    className="inline-flex items-center gap-1 rounded-md border bg-background px-2 py-1 text-xs hover:border-primary hover:bg-muted transition-colors cursor-pointer"
                                    title={a.original_name}
                                >
                                    <IconFileTypePdf size={14} className="text-rose-600" />
                                    <span className="max-w-[140px] truncate">{a.original_name}</span>
                                </button>
                            );
                        })}
                    </div>
                )}

                <FilePreviewDialog
                    file={previewFile}
                    open={previewOpen}
                    onOpenChange={setPreviewOpen}
                />

                {log.ticket && (
                    <button
                        type="button"
                        className="mt-1 flex cursor-pointer items-center gap-1 text-xs font-medium text-primary hover:underline"
                        onClick={() => router.visit('/tickets', { data: { search: log.ticket!.ticket_number } })}
                    >
                        <IconTicket size={14} />
                        <span>
                            {log.ticket.ticket_number}: {log.ticket.title}
                        </span>
                    </button>
                )}

                {log.minute && (
                    <Link
                        href={`/minutes/${log.minute.id}`}
                        className="mt-1 flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                    >
                        <IconNotes size={14} />
                        <span>{log.minute.title}</span>
                    </Link>
                )}

                <LogReactions
                    logId={log.id}
                    reactions={log.reactions}
                    currentUserId={currentUserId}
                />
            </div>
        </div>
    );
}

/**
 * Map raw Gemini error messages into friendlier copy. Catches the common
 * 429/quota case so users don't see scary stack traces.
 */
function humanizeAiError(raw?: string): string {
    if (!raw) return 'AI tidak bisa memproses permintaan ini. Coba lagi sebentar.';
    const m = raw.toLowerCase();
    if (m.includes('quota') || m.includes('exceeded') || m.includes('rate') || m.includes('429')) {
        return 'AI sedang sibuk (kuota gratis kena rate limit). Coba lagi 1 menit lagi 😅';
    }
    if (m.includes('api key') || m.includes('belum di-set')) {
        return 'AI belum diaktifkan oleh admin (DEEPSEEK_API_KEY belum di-set).';
    }
    return raw;
}
