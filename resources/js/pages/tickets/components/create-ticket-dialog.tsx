import { MultiUserSelect } from '@/components/multi-user-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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
import { ai as aiClient } from '@/lib/ai';
import { Client, Project, Timeline, UserSummary } from '@/types/ticket';
import { usePage } from '@inertiajs/react';
import {
    IconAlertCircle,
    IconFile,
    IconLoader2,
    IconMicrophone,
    IconPlayerStopFilled,
    IconPlus,
    IconSparkles,
    IconTag,
    IconUpload,
    IconX,
} from '@tabler/icons-react';
import { type ReactNode, useEffect, useState } from 'react';
import { useVoiceRecorder } from '../../daily-logs/hooks/use-voice-recorder';
import {
    formatAttachmentSize,
    TICKET_ATTACHMENT_ACCEPT,
    TICKET_ATTACHMENT_HELP_TEXT,
} from '../constants/attachment-limits';
import { useFileUpload } from '../hooks/use-file-upload';
import { useFilteredTimelines } from '../hooks/use-filtered-timelines';
import { useProjectTeamMembers } from '../hooks/use-project-team-members';
import { useTagsInput } from '../hooks/use-tags-input';
import { useTicketForm } from '../hooks/use-ticket-form';
import { withRequiredAssignees } from '../utils/assignee-options';

interface CreateTicketDialogProps {
    projects: Project[];
    clients: Client[];
    timelines: Timeline[];
    allUsers?: UserSummary[];
    selectedProjectId?: string;
    prefill?: { title?: string; description?: string; due_date?: string };
    trigger?: ReactNode;
}

function humanizeAiError(raw?: string): string {
    if (!raw)
        return 'AI tidak bisa memproses permintaan ini. Coba lagi sebentar.';
    const message = raw.toLowerCase();

    if (
        message.includes('quota') ||
        message.includes('exceeded') ||
        message.includes('rate') ||
        message.includes('429')
    ) {
        return 'AI sedang sibuk atau terkena rate limit. Coba lagi sebentar.';
    }

    if (message.includes('api key') || message.includes('belum di-set')) {
        return 'AI belum diaktifkan oleh admin (DEEPSEEK_API_KEY belum di-set).';
    }

    return raw;
}

export function CreateTicketDialog({
    projects,
    clients,
    timelines,
    allUsers = [],
    selectedProjectId,
    prefill,
    trigger,
}: CreateTicketDialogProps) {
    const [open, setOpen] = useState(false);
    const [transcript, setTranscript] = useState('');
    const [aiBusy, setAiBusy] = useState<null | 'transcribe' | 'draft'>(null);
    const [aiError, setAiError] = useState<string | null>(null);
    const [draftWarnings, setDraftWarnings] = useState<string[]>([]);
    const { taskTypes, ai: aiConfig } = usePage<{
        taskTypes?: { id: number; nama: string }[];
        ai?: { enabled: boolean };
    }>().props;
    const aiEnabled = aiConfig?.enabled ?? true;
    const initialProjectId =
        selectedProjectId && selectedProjectId !== 'all'
            ? selectedProjectId
            : '';

    const {
        formData,
        errors,
        isSubmitting,
        handleInputChange,
        handleSubmit: submitForm,
        applyDraft,
    } = useTicketForm();

    const filteredTimelines = useFilteredTimelines(
        timelines,
        formData.project_id,
    );

    const { teamMembers } = useProjectTeamMembers(
        projects,
        formData.project_id,
    );

    const filteredProjects = projects.filter(
        (project) =>
            !formData.client_id ||
            String(project.client_id ?? '') === formData.client_id,
    );
    // Show project team members first, but keep every user selectable so a
    // task can be delegated to anyone in the org, not just the project team.
    const availableAssignees = withRequiredAssignees(
        formData.project_id ? teamMembers : allUsers,
        allUsers,
    );

    const {
        isDragging,
        handleDragEnter,
        handleDragLeave,
        handleDragOver,
        handleDrop,
        handleFileSelect,
        handlePaste,
        removeFile,
        fileErrors,
    } = useFileUpload({
        currentFiles: formData.attachments,
        onFilesChange: (files) => handleInputChange('attachments', files),
    });

    const attachmentServerError =
        errors.attachments ??
        Object.entries(errors).find(([key]) =>
            key.startsWith('attachments.'),
        )?.[1];

    const { tagInput, setTagInput, handleAddTag, handleTagKeyDown, removeTag } =
        useTagsInput({
            currentTags: formData.tags,
            onTagsChange: (tags) => handleInputChange('tags', tags),
        });

    useEffect(() => {
        if (open && initialProjectId && !formData.project_id) {
            const initialProject = projects.find(
                (project) => project.id.toString() === initialProjectId,
            );
            if (initialProject?.client_id) {
                handleInputChange(
                    'client_id',
                    initialProject.client_id.toString(),
                );
            }
            handleInputChange('project_id', initialProjectId);
        }
    }, [
        open,
        initialProjectId,
        formData.project_id,
        handleInputChange,
        projects,
    ]);

    const selectedProjectForAi = () => {
        const raw = formData.project_id || initialProjectId;
        if (!raw || raw === 'none' || raw === 'all') return null;

        const parsed = Number(raw);
        return Number.isFinite(parsed) ? parsed : null;
    };

    const selectedClientForAi = () => {
        const raw = formData.client_id;
        if (!raw || raw === 'none' || raw === 'all') return null;

        const parsed = Number(raw);
        return Number.isFinite(parsed) ? parsed : null;
    };

    const buildDraftFromTranscript = async (text: string) => {
        const clean = text.trim();
        if (!clean) return;

        setAiBusy('draft');
        setAiError(null);
        setDraftWarnings([]);

        try {
            const draft = await aiClient.draftTicket(
                clean,
                selectedProjectForAi(),
                selectedClientForAi(),
            );
            applyDraft(draft);
            setDraftWarnings(draft.warnings ?? []);
        } catch (e: any) {
            setAiError(humanizeAiError(e?.message));
        } finally {
            setAiBusy(null);
        }
    };

    const recorder = useVoiceRecorder({
        maxSeconds: 120,
        onStop: async (blob) => {
            setAiBusy('transcribe');
            setAiError(null);
            setDraftWarnings([]);

            try {
                const text = await aiClient.transcribe(blob, 'id-ID');
                setTranscript(text);
                await buildDraftFromTranscript(text);
            } catch (e: any) {
                setAiError(humanizeAiError(e?.message));
            } finally {
                setAiBusy(null);
            }
        },
    });

    const handleVoiceClick = () => {
        if (recorder.status === 'error') {
            recorder.dismissError();
        }

        if (recorder.status === 'recording') {
            recorder.stop();
        } else {
            recorder.start();
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        submitForm(e, () => setOpen(false));
    };

    const isRecording = recorder.status === 'recording';
    const isTranscribing = aiBusy === 'transcribe';
    const isDrafting = aiBusy === 'draft';
    const isAiBusy = isRecording || isTranscribing || isDrafting;

    useEffect(() => {
        if (open && prefill) {
            applyDraft({
                title: prefill.title ?? '',
                description: prefill.description ?? '',
                due_date: prefill.due_date ?? null,
            } as any);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button>
                        <IconPlus className="mr-2 h-4 w-4" />
                        Buat Tiket
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent className="flex max-h-[90vh] max-w-4xl flex-col gap-0 overflow-hidden p-0">
                <DialogHeader className="border-b px-5 py-4 pr-14">
                    <DialogTitle>Buat Tiket Baru</DialogTitle>
                    <DialogDescription>
                        Tambahkan tiket baru langsung ke To Do
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={handleSubmit}
                    onPaste={handlePaste}
                    id="create-ticket-form"
                    className="min-h-0 flex-1 overflow-y-auto"
                >
                    <div className="grid gap-5 px-5 py-4">
                        <div className="rounded-lg border bg-muted/30 p-3">
                            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">
                                        AI Voice
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {isRecording
                                            ? `Merekam ${recorder.seconds}s`
                                            : isTranscribing
                                              ? 'Membaca suara...'
                                              : isDrafting
                                                ? 'Menyusun tiket...'
                                                : transcript
                                                  ? 'Siap direview'
                                                  : 'Siap menyusun tiket'}
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant={
                                            isRecording
                                                ? 'destructive'
                                                : 'outline'
                                        }
                                        size="sm"
                                        onClick={handleVoiceClick}
                                        disabled={
                                            !aiEnabled ||
                                            !recorder.supported ||
                                            isSubmitting ||
                                            isTranscribing ||
                                            isDrafting
                                        }
                                    >
                                        {isRecording ? (
                                            <>
                                                <IconPlayerStopFilled className="mr-2 h-4 w-4" />{' '}
                                                Stop
                                            </>
                                        ) : isTranscribing ? (
                                            <>
                                                <IconLoader2 className="mr-2 h-4 w-4 animate-spin" />{' '}
                                                Transkrip
                                            </>
                                        ) : isDrafting ? (
                                            <>
                                                <IconSparkles className="mr-2 h-4 w-4 animate-pulse" />{' '}
                                                Susun
                                            </>
                                        ) : (
                                            <>
                                                <IconMicrophone className="mr-2 h-4 w-4" />{' '}
                                                Voice AI
                                            </>
                                        )}
                                    </Button>
                                    {transcript && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                buildDraftFromTranscript(
                                                    transcript,
                                                )
                                            }
                                            disabled={
                                                !aiEnabled ||
                                                isSubmitting ||
                                                isAiBusy
                                            }
                                        >
                                            <IconSparkles className="mr-2 h-4 w-4" />{' '}
                                            Susun ulang
                                        </Button>
                                    )}
                                </div>
                            </div>

                            {!aiEnabled && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    AI belum aktif di server.
                                </p>
                            )}
                            {recorder.error && (
                                <p className="mt-2 text-xs text-destructive">
                                    {recorder.error}
                                </p>
                            )}
                            {aiError && (
                                <p className="mt-2 text-xs text-destructive">
                                    {aiError}
                                </p>
                            )}

                            {transcript && (
                                <div className="mt-3 grid gap-2">
                                    <Label htmlFor="ai-ticket-transcript">
                                        Transkrip
                                    </Label>
                                    <Textarea
                                        id="ai-ticket-transcript"
                                        value={transcript}
                                        onChange={(e) =>
                                            setTranscript(e.target.value)
                                        }
                                        disabled={isSubmitting || isAiBusy}
                                        className="min-h-20"
                                    />
                                </div>
                            )}

                            {draftWarnings.length > 0 && (
                                <div className="mt-3 space-y-1 rounded-md border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
                                    {draftWarnings.map((warning) => (
                                        <div
                                            key={warning}
                                            className="flex gap-2"
                                        >
                                            <IconAlertCircle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                            <span>{warning}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="grid gap-5">
                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="client">
                                        Client{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Select
                                        value={formData.client_id}
                                        onValueChange={(value) => {
                                            handleInputChange(
                                                'client_id',
                                                value,
                                            );
                                            handleInputChange('project_id', '');
                                            handleInputChange(
                                                'timeline_id',
                                                '',
                                            );
                                            handleInputChange(
                                                'assigned_to',
                                                '',
                                            );
                                        }}
                                        disabled={isSubmitting}
                                    >
                                        <SelectTrigger
                                            id="client"
                                            className={
                                                errors.client_id
                                                    ? 'border-destructive'
                                                    : ''
                                            }
                                        >
                                            <SelectValue placeholder="Pilih client" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {clients.length === 0 ? (
                                                <p className="px-2 py-3 text-center text-xs text-muted-foreground">
                                                    Belum ada client aktif.
                                                </p>
                                            ) : (
                                                clients.map((client) => (
                                                    <SelectItem
                                                        key={client.id}
                                                        value={client.id.toString()}
                                                    >
                                                        {client.kode} -{' '}
                                                        {client.nama}
                                                    </SelectItem>
                                                ))
                                            )}
                                        </SelectContent>
                                    </Select>
                                    {errors.client_id && (
                                        <p className="text-sm text-destructive">
                                            {errors.client_id}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="project">Proyek</Label>
                                    <Select
                                        value={formData.project_id || 'none'}
                                        onValueChange={(value) => {
                                            const nextProjectId =
                                                value === 'none' ? '' : value;
                                            const selectedProject =
                                                projects.find(
                                                    (project) =>
                                                        project.id.toString() ===
                                                        nextProjectId,
                                                );
                                            if (
                                                selectedProject?.client_id &&
                                                !formData.client_id
                                            ) {
                                                handleInputChange(
                                                    'client_id',
                                                    selectedProject.client_id.toString(),
                                                );
                                            }
                                            handleInputChange(
                                                'project_id',
                                                nextProjectId,
                                            );
                                            handleInputChange(
                                                'timeline_id',
                                                '',
                                            );
                                            handleInputChange(
                                                'assigned_to',
                                                '',
                                            );
                                        }}
                                        disabled={
                                            isSubmitting || !formData.client_id
                                        }
                                    >
                                        <SelectTrigger
                                            id="project"
                                            className={
                                                errors.project_id
                                                    ? 'border-destructive'
                                                    : ''
                                            }
                                        >
                                            <SelectValue placeholder="Pilih proyek (opsional)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                Tanpa proyek
                                            </SelectItem>
                                            {filteredProjects.map((project) => (
                                                <SelectItem
                                                    key={project.id}
                                                    value={project.id.toString()}
                                                >
                                                    {project.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.project_id && (
                                        <p className="text-sm text-destructive">
                                            {errors.project_id}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="timeline">Timeline</Label>
                                    <Select
                                        value={formData.timeline_id || 'none'}
                                        onValueChange={(value) =>
                                            handleInputChange(
                                                'timeline_id',
                                                value === 'none' ? '' : value,
                                            )
                                        }
                                        disabled={
                                            isSubmitting || !formData.project_id
                                        }
                                    >
                                        <SelectTrigger id="timeline">
                                            <SelectValue placeholder="Pilih timeline (opsional)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">
                                                Tanpa timeline
                                            </SelectItem>
                                            {filteredTimelines.map(
                                                (timeline) => (
                                                    <SelectItem
                                                        key={timeline.id}
                                                        value={timeline.id.toString()}
                                                    >
                                                        {timeline.title}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="title">
                                    Judul{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="title"
                                    placeholder="contoh: Implementasi autentikasi pengguna"
                                    value={formData.title}
                                    onChange={(e) =>
                                        handleInputChange(
                                            'title',
                                            e.target.value,
                                        )
                                    }
                                    disabled={isSubmitting}
                                    className={
                                        errors.title ? 'border-destructive' : ''
                                    }
                                />
                                {errors.title && (
                                    <p className="text-sm text-destructive">
                                        {errors.title}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Deskripsi</Label>
                                <Textarea
                                    id="description"
                                    placeholder="Deskripsikan kebutuhan tiket..."
                                    value={formData.description}
                                    onChange={(e) =>
                                        handleInputChange(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    disabled={isSubmitting}
                                />
                            </div>

                            <div className="grid grid-cols-3 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="ticket-type">
                                        Tipe{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Select
                                        value={formData.type}
                                        onValueChange={(value: any) =>
                                            handleInputChange('type', value)
                                        }
                                        disabled={isSubmitting}
                                    >
                                        <SelectTrigger id="ticket-type">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="bug">
                                                Bug
                                            </SelectItem>
                                            <SelectItem value="feature">
                                                Fitur
                                            </SelectItem>
                                            <SelectItem value="task">
                                                Tugas
                                            </SelectItem>
                                            <SelectItem value="improvement">
                                                Peningkatan
                                            </SelectItem>
                                            <SelectItem value="documentation">
                                                Dokumentasi
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="task-type">
                                        Tipe Tugas
                                    </Label>
                                    <Select
                                        value={formData.task_type_id}
                                        onValueChange={(value) =>
                                            handleInputChange(
                                                'task_type_id',
                                                value,
                                            )
                                        }
                                        disabled={isSubmitting}
                                    >
                                        <SelectTrigger id="task-type">
                                            <SelectValue placeholder="Pilih task type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {(taskTypes ?? []).length === 0 ? (
                                                <p className="px-2 py-3 text-center text-xs text-muted-foreground">
                                                    Belum ada tipe tugas.
                                                    Hubungi admin.
                                                </p>
                                            ) : (
                                                (taskTypes ?? []).map((t) => (
                                                    <SelectItem
                                                        key={t.id}
                                                        value={t.id.toString()}
                                                    >
                                                        {t.nama}
                                                    </SelectItem>
                                                ))
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="request-type">
                                        Tipe Permintaan
                                    </Label>
                                    <Select
                                        value={formData.request_type}
                                        onValueChange={(value: any) =>
                                            handleInputChange(
                                                'request_type',
                                                value,
                                            )
                                        }
                                        disabled={isSubmitting}
                                    >
                                        <SelectTrigger id="request-type">
                                            <SelectValue placeholder="Berbayar / Gratis" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="berbayar">
                                                Berbayar
                                            </SelectItem>
                                            <SelectItem value="gratis">
                                                Gratis
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            {/* Row 2: Prioritas | Status */}
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="priority">
                                        Prioritas{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Select
                                        value={formData.priority}
                                        onValueChange={(value: any) =>
                                            handleInputChange('priority', value)
                                        }
                                        disabled={isSubmitting}
                                    >
                                        <SelectTrigger id="priority">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="highest">
                                                Tertinggi
                                            </SelectItem>
                                            <SelectItem value="high">
                                                Tinggi
                                            </SelectItem>
                                            <SelectItem value="medium">
                                                Sedang
                                            </SelectItem>
                                            <SelectItem value="low">
                                                Rendah
                                            </SelectItem>
                                            <SelectItem value="lowest">
                                                Terendah
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>
                                    <Select
                                        value={formData.status}
                                        onValueChange={(value: any) =>
                                            handleInputChange('status', value)
                                        }
                                        disabled={isSubmitting}
                                    >
                                        <SelectTrigger id="status">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="todo">
                                                To Do
                                            </SelectItem>
                                            <SelectItem value="pending">
                                                Menunggu Approval
                                            </SelectItem>
                                            <SelectItem value="inprogress">
                                                Sedang Dikerjakan
                                            </SelectItem>
                                            <SelectItem value="qa-ready">
                                                Siap QA
                                            </SelectItem>
                                            <SelectItem value="qa-test">
                                                QA Test
                                            </SelectItem>
                                            <SelectItem value="review">
                                                Review
                                            </SelectItem>
                                            <SelectItem value="done">
                                                Selesai
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            {/* Delegasi Tugas (penanggung jawab, bisa lebih dari 1) */}
                            <div className="grid gap-2">
                                <Label htmlFor="assignees">
                                    Delegasi Tugas
                                </Label>
                                <MultiUserSelect
                                    id="assignees"
                                    users={availableAssignees}
                                    value={formData.assignees}
                                    onChange={(v) =>
                                        handleInputChange('assignees', v)
                                    }
                                    placeholder="Pilih penanggung jawab (bisa lebih dari 1)"
                                    disabled={
                                        isSubmitting ||
                                        availableAssignees.length === 0
                                    }
                                />
                                {formData.project_id &&
                                    teamMembers.length === 0 && (
                                        <p className="text-xs text-muted-foreground">
                                            Tidak ada anggota tim tersedia di
                                            proyek ini
                                        </p>
                                    )}
                            </div>

                            {/* Row 3: Story Points | Tenggat */}
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="story-points">
                                        Story Points
                                    </Label>
                                    <Select
                                        value={formData.story_points}
                                        onValueChange={(value) =>
                                            handleInputChange(
                                                'story_points',
                                                value,
                                            )
                                        }
                                        disabled={isSubmitting}
                                    >
                                        <SelectTrigger id="story-points">
                                            <SelectValue placeholder="Pilih SP" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="1">
                                                <span className="font-medium">
                                                    1
                                                </span>
                                                <span className="ml-2 text-muted-foreground">
                                                    — Sangat Kecil
                                                </span>
                                            </SelectItem>
                                            <SelectItem value="2">
                                                <span className="font-medium">
                                                    2
                                                </span>
                                                <span className="ml-2 text-muted-foreground">
                                                    — Kecil
                                                </span>
                                            </SelectItem>
                                            <SelectItem value="3">
                                                <span className="font-medium">
                                                    3
                                                </span>
                                                <span className="ml-2 text-muted-foreground">
                                                    — Kecil–Sedang
                                                </span>
                                            </SelectItem>
                                            <SelectItem value="5">
                                                <span className="font-medium">
                                                    5
                                                </span>
                                                <span className="ml-2 text-muted-foreground">
                                                    — Sedang
                                                </span>
                                            </SelectItem>
                                            <SelectItem value="8">
                                                <span className="font-medium">
                                                    8
                                                </span>
                                                <span className="ml-2 text-muted-foreground">
                                                    — Besar
                                                </span>
                                            </SelectItem>
                                            <SelectItem value="13">
                                                <span className="font-medium">
                                                    13
                                                </span>
                                                <span className="ml-2 text-muted-foreground">
                                                    — Sangat Besar
                                                </span>
                                            </SelectItem>
                                            <SelectItem value="21">
                                                <span className="font-medium">
                                                    21
                                                </span>
                                                <span className="ml-2 text-muted-foreground">
                                                    — Epik
                                                </span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="due-date">Tenggat</Label>
                                    <Input
                                        id="due-date"
                                        type="date"
                                        value={formData.due_date}
                                        onChange={(e) =>
                                            handleInputChange(
                                                'due_date',
                                                e.target.value,
                                            )
                                        }
                                        disabled={isSubmitting}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="estimated-hours">
                                    Estimasi Jam
                                </Label>
                                <Input
                                    id="estimated-hours"
                                    type="number"
                                    step="0.5"
                                    placeholder="contoh: 8"
                                    value={formData.estimated_hours}
                                    onChange={(e) =>
                                        handleInputChange(
                                            'estimated_hours',
                                            e.target.value,
                                        )
                                    }
                                    disabled={isSubmitting}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="tags">Tag (Opsional)</Label>
                                <div className="space-y-2">
                                    <div className="flex gap-2">
                                        <Input
                                            id="tags"
                                            placeholder="Tambah tag..."
                                            value={tagInput}
                                            onChange={(e) =>
                                                setTagInput(e.target.value)
                                            }
                                            onKeyDown={handleTagKeyDown}
                                            disabled={isSubmitting}
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={handleAddTag}
                                            disabled={
                                                isSubmitting || !tagInput.trim()
                                            }
                                        >
                                            <IconTag className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    {formData.tags.length > 0 && (
                                        <div className="flex flex-wrap gap-2">
                                            {formData.tags.map((tag, index) => (
                                                <Badge
                                                    key={index}
                                                    variant="secondary"
                                                    className="gap-1"
                                                >
                                                    {tag}
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            removeTag(tag)
                                                        }
                                                        className="ml-1 rounded-full hover:bg-muted"
                                                        disabled={isSubmitting}
                                                    >
                                                        <IconX className="h-3 w-3" />
                                                    </button>
                                                </Badge>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="attachments">
                                    Lampiran (Opsional)
                                </Label>
                                <div className="space-y-3">
                                    <div
                                        onDragEnter={handleDragEnter}
                                        onDragOver={handleDragOver}
                                        onDragLeave={handleDragLeave}
                                        onDrop={handleDrop}
                                        onPaste={handlePaste}
                                        tabIndex={0}
                                        className={`relative rounded-lg border-2 border-dashed transition-colors outline-none ${
                                            isDragging
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border bg-muted/30'
                                        }`}
                                    >
                                        <input
                                            id="attachments"
                                            type="file"
                                            multiple
                                            accept={TICKET_ATTACHMENT_ACCEPT}
                                            onChange={handleFileSelect}
                                            disabled={isSubmitting}
                                            className="absolute inset-0 z-10 cursor-pointer opacity-0"
                                        />
                                        <div className="flex flex-col items-center justify-center gap-2 px-6 py-8 text-center">
                                            <div
                                                className={`rounded-full p-3 ${
                                                    isDragging
                                                        ? 'bg-primary/10'
                                                        : 'bg-muted'
                                                }`}
                                            >
                                                <IconUpload
                                                    className={`h-6 w-6 ${
                                                        isDragging
                                                            ? 'text-primary'
                                                            : 'text-muted-foreground'
                                                    }`}
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <p className="text-sm font-medium">
                                                    {isDragging ? (
                                                        <span className="text-primary">
                                                            Letakkan file di
                                                            sini
                                                        </span>
                                                    ) : (
                                                        <>
                                                            <span className="text-primary">
                                                                Pilih file
                                                            </span>
                                                            {
                                                                ' atau seret & lepas, atau paste (Ctrl+V)'
                                                            }
                                                        </>
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {
                                                        TICKET_ATTACHMENT_HELP_TEXT
                                                    }
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    {fileErrors.length > 0 && (
                                        <div className="space-y-1 rounded-md border border-destructive/30 bg-destructive/5 p-2 text-xs text-destructive">
                                            {fileErrors.map((error) => (
                                                <p key={error}>{error}</p>
                                            ))}
                                        </div>
                                    )}

                                    {attachmentServerError && (
                                        <div className="rounded-md border border-destructive/30 bg-destructive/5 p-2 text-xs text-destructive">
                                            {attachmentServerError}
                                        </div>
                                    )}

                                    {formData.attachments.length > 0 && (
                                        <div className="space-y-2">
                                            <p className="text-xs font-medium text-muted-foreground">
                                                {formData.attachments.length}{' '}
                                                file
                                                {formData.attachments.length > 1
                                                    ? 's'
                                                    : ''}{' '}
                                                dipilih
                                            </p>
                                            <div className="space-y-2">
                                                {formData.attachments.map(
                                                    (file, index) => (
                                                        <div
                                                            key={index}
                                                            className="group flex items-center gap-3 rounded-lg border border-border bg-background px-3 py-2.5 transition-colors hover:bg-muted/50"
                                                        >
                                                            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary/10">
                                                                <IconFile className="h-4 w-4 text-primary" />
                                                            </div>
                                                            <div className="min-w-0 flex-1">
                                                                <p className="truncate text-sm font-medium">
                                                                    {file.name}
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {formatAttachmentSize(
                                                                        file.size,
                                                                    )}
                                                                </p>
                                                            </div>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 opacity-0 transition-opacity group-hover:opacity-100"
                                                                onClick={() =>
                                                                    removeFile(
                                                                        index,
                                                                    )
                                                                }
                                                                disabled={
                                                                    isSubmitting
                                                                }
                                                            >
                                                                <IconX className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <DialogFooter className="m-0 shrink-0 rounded-b-xl px-5 py-4">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                        disabled={isSubmitting || isAiBusy}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        disabled={isSubmitting || isAiBusy}
                        form="create-ticket-form"
                    >
                        {isSubmitting ? 'Membuat...' : 'Buat Tiket'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
