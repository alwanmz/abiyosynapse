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
import { Client, Project, Ticket, Timeline, UserSummary } from '@/types/ticket';
import {
    IconFile,
    IconPaperclip,
    IconTag,
    IconUpload,
    IconX,
} from '@tabler/icons-react';
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

interface EditTicketDialogProps {
    ticket: Ticket;
    projects: Project[];
    clients: Client[];
    timelines: Timeline[];
    allUsers?: UserSummary[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function EditTicketDialog({
    ticket,
    projects,
    clients,
    timelines,
    allUsers = [],
    open,
    onOpenChange,
}: EditTicketDialogProps) {
    const {
        formData,
        errors,
        isSubmitting,
        handleInputChange,
        handleSubmit: submitForm,
        removeAttachment,
    } = useTicketForm({ ticket, open });

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

    const handleSubmit = (e: React.FormEvent) => {
        submitForm(e, () => onOpenChange(false));
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[80vh] max-w-3xl"
                onPointerDown={(e) => e.stopPropagation()}
            >
                <DialogHeader>
                    <DialogTitle>Edit Tiket</DialogTitle>
                    <DialogDescription>
                        Perbarui informasi dan detail tiket.
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={handleSubmit}
                    onPaste={handlePaste}
                    id="edit-ticket-form"
                >
                    <div className="no-scrollbar -mx-4 grid max-h-[60vh] gap-6 overflow-y-auto px-4">
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="client">
                                    Client{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={formData.client_id}
                                    onValueChange={(value) => {
                                        handleInputChange('client_id', value);
                                        handleInputChange('project_id', '');
                                        handleInputChange('timeline_id', '');
                                        handleInputChange('assigned_to', '');
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
                                        {clients.map((client) => (
                                            <SelectItem
                                                key={client.id}
                                                value={client.id.toString()}
                                            >
                                                {client.kode} - {client.nama}
                                            </SelectItem>
                                        ))}
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
                                        const selectedProject = projects.find(
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
                                        handleInputChange('timeline_id', '');
                                        handleInputChange('assigned_to', '');
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
                                        {filteredTimelines.map((timeline) => (
                                            <SelectItem
                                                key={timeline.id}
                                                value={timeline.id.toString()}
                                            >
                                                {timeline.title}
                                            </SelectItem>
                                        ))}
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
                                    handleInputChange('title', e.target.value)
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
                                    <span className="text-destructive">*</span>
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
                                        <SelectItem value="bug">Bug</SelectItem>
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
                                <Label htmlFor="priority">
                                    Prioritas{' '}
                                    <span className="text-destructive">*</span>
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
                                        <SelectItem value="inprogress">
                                            Sedang Dikerjakan
                                        </SelectItem>
                                        {formData.status ===
                                            'not-appropriate' && (
                                            <SelectItem value="not-appropriate">
                                                Belum Sesuai
                                            </SelectItem>
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {formData.status === 'not-appropriate' && (
                            <div className="grid gap-2">
                                <Label htmlFor="review-notes">
                                    Keterangan Review
                                </Label>
                                <Textarea
                                    id="review-notes"
                                    placeholder="Jelaskan kenapa hasil QC belum sesuai..."
                                    rows={3}
                                    value={formData.review_notes}
                                    onChange={(e) =>
                                        handleInputChange(
                                            'review_notes',
                                            e.target.value,
                                        )
                                    }
                                    disabled={isSubmitting}
                                />
                            </div>
                        )}

                        {/* Delegasi Tugas (penanggung jawab, bisa lebih dari 1) */}
                        <div className="grid gap-2">
                            <Label htmlFor="assignees">Delegasi Tugas</Label>
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
                                        Tidak ada anggota tim tersedia di proyek
                                        ini
                                    </p>
                                )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="story-points">
                                    Story Points
                                </Label>
                                <Select
                                    value={formData.story_points}
                                    onValueChange={(value) =>
                                        handleInputChange('story_points', value)
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
                                                        Letakkan file di sini
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
                                                {TICKET_ATTACHMENT_HELP_TEXT}
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
                                            {formData.attachments.length} file
                                            {formData.attachments.length > 1
                                                ? 's'
                                                : ''}{' '}
                                            terlampir
                                        </p>
                                        <div className="space-y-2">
                                            {formData.attachments.map(
                                                (attachment, index) => {
                                                    const isFile =
                                                        attachment instanceof
                                                        File;
                                                    const name = isFile
                                                        ? attachment.name
                                                        : attachment.name;
                                                    const size = isFile
                                                        ? attachment.size
                                                        : attachment.size;

                                                    return (
                                                        <div
                                                            key={index}
                                                            className="group flex items-center gap-3 rounded-lg border border-border bg-background px-3 py-2.5 transition-colors hover:bg-muted/50"
                                                        >
                                                            <div
                                                                className={`flex h-8 w-8 items-center justify-center rounded-md ${isFile ? 'bg-primary/10' : 'bg-muted'}`}
                                                            >
                                                                {isFile ? (
                                                                    <IconFile className="h-4 w-4 text-primary" />
                                                                ) : (
                                                                    <IconPaperclip className="h-4 w-4 text-muted-foreground" />
                                                                )}
                                                            </div>
                                                            <div className="min-w-0 flex-1">
                                                                <p className="truncate text-sm font-medium">
                                                                    {name}
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {formatAttachmentSize(
                                                                        size,
                                                                    )}
                                                                    {!isFile &&
                                                                        ' • File Lama'}
                                                                </p>
                                                            </div>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 opacity-0 transition-opacity group-hover:opacity-100"
                                                                onClick={() =>
                                                                    removeAttachment(
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
                                                    );
                                                },
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </form>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={isSubmitting}
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        disabled={isSubmitting}
                        form="edit-ticket-form"
                    >
                        {isSubmitting ? 'Memperbarui...' : 'Perbarui Tiket'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
