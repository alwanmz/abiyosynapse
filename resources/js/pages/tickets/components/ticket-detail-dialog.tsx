import { ConfirmDialog } from '@/components/confirm-dialog';
import { UserAvatar } from '@/components/user-avatar';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { MultiUserSelect } from '@/components/multi-user-select';
import { userHasPermission } from '@/lib/permissions';
import { SharedData } from '@/types';
import { Ticket, UserSummary } from '@/types/ticket';
import { router, usePage } from '@inertiajs/react';
import {
    IconCalendar,
    IconClock,
    IconFileText,
    IconFlag,
    IconPaperclip,
    IconTag,
    IconTrash,
    IconUser,
    IconX,
} from '@tabler/icons-react';
import { TICKET_PRIORITIES } from '../constants/ticket-priorities';
import { TICKET_STATUSES } from '../constants/ticket-statuses';
import { TICKET_TYPES } from '../constants/ticket-types';
import {
    formatAttachmentSize,
    TICKET_ATTACHMENT_ACCEPT,
    validateTicketAttachmentFiles,
} from '../constants/attachment-limits';
import { TicketComments } from './ticket-comments';
import { FilePreviewDialog } from './file-preview-dialog';
import { useEffect, useRef, useState } from 'react';

interface FileAttachment {
    name: string;
    path: string;
    disk?: string;
    url?: string;
    size: number;
    mime_type: string;
}

interface TicketDetailDialogProps {
    ticket: Ticket;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function TicketDetailDialog({
    ticket,
    open,
    onOpenChange,
}: TicketDetailDialogProps) {
    const [previewFile, setPreviewFile] = useState<FileAttachment | null>(null);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [rejectReason, setRejectReason] = useState('');
    const [showReject, setShowReject] = useState(false);
    const [uploadingFiles, setUploadingFiles] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [deletingAttachment, setDeletingAttachment] = useState<{
        attachment: FileAttachment;
        index: number;
    } | null>(null);
    const [isDeletingFile, setIsDeletingFile] = useState(false);
    const [assignees, setAssignees] = useState<string[]>([]);
    const [savingAssignees, setSavingAssignees] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const { allUsers, auth } = usePage<SharedData & { allUsers?: UserSummary[] }>().props;
    const canApproveTickets = userHasPermission(auth, 'tickets.approve');
    const canEdit =
        userHasPermission(auth, 'tickets.edit') ||
        userHasPermission(auth, 'tickets.update') ||
        userHasPermission(auth, 'tickets.update-any');
    // Tiket selesai bersifat final — kunci semua aksi edit di detail.
    const isDone = ticket.status === 'done';

    const currentAssigneeIds = (ticket.assignees ?? []).map((u) => u.id.toString());

    // Re-sync local picker when a different ticket is opened (table view reuses one dialog).
    useEffect(() => {
        setAssignees((ticket.assignees ?? []).map((u) => u.id.toString()));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ticket.id]);

    const assigneesChanged =
        JSON.stringify([...assignees].sort()) !== JSON.stringify([...currentAssigneeIds].sort());

    const saveAssignees = () => {
        setSavingAssignees(true);
        router.post(
            `/tickets/${ticket.id}`,
            { _method: 'put', sync_assignees: 1, assignees },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setSavingAssignees(false),
            },
        );
    };

    const approve = () =>
        router.post(`/tickets/${ticket.id}/approve`, {}, { preserveScroll: true });

    const reject = () => {
        if (!rejectReason.trim()) return;
        router.post(
            `/tickets/${ticket.id}/reject`,
            { rejection_reason: rejectReason },
            { preserveScroll: true, onSuccess: () => { setShowReject(false); setRejectReason(''); } },
        );
    };

    const uploadFiles = (files: File[]) => {
        if (isDone || !files.length) return;
        const { accepted, rejected } = validateTicketAttachmentFiles(files);
        if (rejected.length > 0) {
            setUploadError(rejected.join(' '));
            return;
        }
        setUploadError(null);
        setUploadingFiles(true);
        // POST with a spoofed _method: PHP doesn't parse multipart bodies on a real
        // PUT, so router.put + forceFormData would upload nothing. Laravel rewrites
        // the POST back to the PUT route.
        router.post(`/tickets/${ticket.id}`, { _method: 'put', attachments: accepted } as any, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setUploadingFiles(false),
            onError: () => setUploadingFiles(false),
        });
    };

    const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
        uploadFiles(Array.from(e.target.files ?? []));
        e.target.value = '';
    };

    const handleAttachmentPaste = (e: React.ClipboardEvent) => {
        const items = Array.from(e.clipboardData?.items ?? []);
        const imageFiles = items
            .filter((item) => item.type.startsWith('image/'))
            .map((item) => item.getAsFile())
            .filter((f): f is File => f !== null);
        if (imageFiles.length === 0) return;
        e.preventDefault();
        uploadFiles(imageFiles);
    };

    const handlePreview = (file: FileAttachment) => {
        setPreviewFile(file);
        setPreviewOpen(true);
    };

    const priorityConfig = TICKET_PRIORITIES[ticket.priority];
    const displayStatus = ticket.status === 'backlog' ? 'todo' : ticket.status;
    const statusConfig = TICKET_STATUSES.find((status) => status.id === displayStatus);
    const typeConfig = TICKET_TYPES[ticket.type]

    const getAttachmentUrl = (attachment: FileAttachment) => {
        const filename = attachment.path.split('/').pop();
        if (filename) return `/attachments/${ticket.id}/preview?filename=${encodeURIComponent(filename)}`;
        return attachment.url ?? attachment.path;
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className="max-h-[90vh] max-w-6xl w-[95vw] sm:w-full overflow-y-auto"
                onPaste={handleAttachmentPaste}
                onPointerDown={(e) => e.stopPropagation()}
            >
                <DialogHeader className="pr-8">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex-1">
                            <DialogTitle className="text-xl">
                                <Badge
                                    variant="outline"
                                    className="font-mono text-sm mr-2"
                                >
                                    {ticket.ticket_number}
                                </Badge>
                                {ticket.title}
                            </DialogTitle>
                        </div>
                    </div>
                </DialogHeader>

                <div className="space-y-4 overflow-y-auto -mx-4 px-4" style={{ maxHeight: 'calc(90vh - 120px)' }}>
                    <div className="flex flex-wrap gap-2">
                        <Badge
                            variant="secondary"
                            className={typeConfig.className}
                        >
                            <typeConfig.icon className="mr-1 h-3 w-3" />
                            {typeConfig.label}
                        </Badge>
                        <Badge
                            variant="secondary"
                            className={priorityConfig.badgeClassName}
                        >
                            <IconFlag className="mr-1 h-3 w-3" />
                            {priorityConfig.label}
                        </Badge>
                        <Badge
                            variant="outline"
                            className={statusConfig?.badgeClassName}
                        >
                            {statusConfig?.label}
                        </Badge>
                        {ticket.story_points && (
                            <Badge variant="outline">
                                {ticket.story_points} SP
                            </Badge>
                        )}
                    </div>

                    <Separator />

                    <div className="grid gap-6 lg:grid-cols-12">
                        <div className="space-y-4 lg:col-span-8">
                            {ticket.description && (
                                <div className="space-y-2">
                                    <Label className="flex items-center gap-2 text-sm font-semibold">
                                        <IconFileText className="h-4 w-4" />
                                        Deskripsi
                                    </Label>
                                    <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                                        {ticket.description}
                                    </p>
                                </div>
                            )}

                            {ticket.review_notes && (
                                <div className="space-y-2 rounded-lg border border-rose-200 bg-rose-50/60 p-3 dark:border-rose-900/40 dark:bg-rose-950/20">
                                    <Label className="flex items-center gap-2 text-sm font-semibold text-rose-700 dark:text-rose-400">
                                        <IconFileText className="h-4 w-4" />
                                        Keterangan Review
                                    </Label>
                                    <p className="text-sm text-rose-900/80 whitespace-pre-wrap dark:text-rose-200/80">
                                        {ticket.review_notes}
                                    </p>
                                </div>
                            )}

                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <Label className="flex items-center gap-2 text-sm font-semibold">
                                        <IconPaperclip className="h-4 w-4" />
                                        Lampiran
                                    </Label>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        disabled={uploadingFiles || isDone}
                                        onClick={() => fileInputRef.current?.click()}
                                    >
                                        {uploadingFiles ? 'Mengunggah...' : '+ Tambah File'}
                                    </Button>
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        multiple
                                        accept={TICKET_ATTACHMENT_ACCEPT}
                                        className="hidden"
                                        onChange={handleFileUpload}
                                    />
                                </div>
                                {uploadError && (
                                    <p className="text-xs text-destructive">{uploadError}</p>
                                )}
                                {ticket.attachments && ticket.attachments.length > 0 && (
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {ticket.attachments.map(
                                            (attachment, index) => {
                                                const isImage = attachment.mime_type.startsWith('image/');

                                                return (
                                                    <div
                                                        key={index}
                                                        className="group relative block overflow-hidden rounded-lg border border-border transition-all hover:border-primary cursor-pointer"
                                                        onClick={() => handlePreview(attachment)}
                                                    >
                                                        {!isDone && (
                                                            <Button
                                                                type="button"
                                                                variant="destructive"
                                                                size="icon"
                                                                className="absolute top-2 right-2 h-7 w-7 rounded-full opacity-0 shadow-md transition-opacity group-hover:opacity-100 z-10"
                                                                title="Hapus lampiran"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    setDeletingAttachment({ attachment, index });
                                                                }}
                                                            >
                                                                <IconX className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {isImage ? (
                                                            <img
                                                                src={getAttachmentUrl(attachment)}
                                                                alt={attachment.name}
                                                                className="max-h-40 w-full object-cover"
                                                                loading="lazy"
                                                            />
                                                        ) : (
                                                            <div className="flex h-40 items-center justify-center bg-muted">
                                                                <IconFileText className="h-12 w-12 text-muted-foreground" />
                                                            </div>
                                                        )}
                                                        <div className="bg-background px-3 py-2 border-t">
                                                            <p className="text-xs font-medium truncate">
                                                                {attachment.name}
                                                            </p>
                                                            <p className="text-[10px] text-muted-foreground">
                                                                {formatAttachmentSize(attachment.size)}
                                                            </p>
                                                        </div>
                                                    </div>
                                                );
                                            }
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="lg:col-span-4">
                            {/* People Section */}
                            <div className="mb-4">
                                <Label className="text-sm font-semibold">
                                    Orang
                                </Label>
                                <div className="mt-2">
                                    {/* Reporter */}
                                    <div className="mb-2">
                                        <Label className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <IconUser className="h-3 w-3" />
                                            Pelapor
                                        </Label>
                                        <div className="flex items-center gap-2 mt-2">
                                            <UserAvatar
                                                user={ticket.reporter}
                                                className="h-7 w-7"
                                                fallbackClassName="text-xs"
                                            />
                                            <span className="text-sm">
                                                {ticket.reporter.name}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Delegasi Tugas (penanggung jawab, bisa lebih dari 1) */}
                                    <div className="mb-2">
                                        <Label className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <IconUser className="h-3 w-3" />
                                            Delegasi Tugas
                                        </Label>

                                        {ticket.assignees && ticket.assignees.length > 0 ? (
                                            <div className="mt-2 space-y-1.5">
                                                {ticket.assignees.map((u) => (
                                                    <div key={u.id} className="flex items-center gap-2">
                                                        <UserAvatar user={u} className="h-7 w-7" fallbackClassName="text-xs" />
                                                        <span className="text-sm">{u.name}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <span className="text-sm text-muted-foreground">
                                                Belum ditugaskan
                                            </span>
                                        )}

                                        {canEdit && !isDone && (
                                            <div className="mt-2 space-y-2">
                                                <MultiUserSelect
                                                    users={allUsers ?? []}
                                                    value={assignees}
                                                    onChange={setAssignees}
                                                    placeholder="Delegasikan ke (bisa lebih dari 1)"
                                                    disabled={savingAssignees}
                                                />
                                                {assigneesChanged && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={saveAssignees}
                                                        disabled={savingAssignees}
                                                    >
                                                        {savingAssignees ? 'Menyimpan...' : 'Simpan Delegasi'}
                                                    </Button>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                </div>
                            </div>

                            {/* Approval untuk tiket berbayar, atau tiket apa pun yang
                                sedang berstatus "Menunggu Approval" (pending) agar
                                tidak tersangkut tanpa tombol persetujuan. */}
                            {(() => {
                                const requiresApproval = ticket.request_type === 'berbayar';
                                const awaitingApproval = ticket.status === 'pending';
                                if (!requiresApproval && !awaitingApproval) return null;
                                return (
                                    <div className="mb-4">
                                        <Label className="text-sm font-semibold">Approval</Label>
                                        <div className="mt-2 space-y-2">
                                            {ticket.approved_at ? (
                                                <Badge className="bg-green-600 text-white">
                                                    Disetujui{ticket.approver ? ` oleh ${ticket.approver.name}` : ''}
                                                </Badge>
                                            ) : ticket.rejected_at ? (
                                                <div className="space-y-1">
                                                    <Badge variant="destructive">
                                                        Ditolak{ticket.rejecter ? ` oleh ${ticket.rejecter.name}` : ''}
                                                    </Badge>
                                                    {ticket.rejection_reason && (
                                                        <p className="text-xs text-muted-foreground">
                                                            Alasan: {ticket.rejection_reason}
                                                        </p>
                                                    )}
                                                </div>
                                            ) : (
                                                <Badge variant="outline">Menunggu approval</Badge>
                                            )}

                                            {canApproveTickets && !ticket.approved_at && !ticket.rejected_at && (
                                                <div className="flex gap-2">
                                                    <Button size="sm" onClick={approve}>
                                                        Setujui
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() => setShowReject((v) => !v)}
                                                    >
                                                        Tolak
                                                    </Button>
                                                </div>
                                            )}

                                            {canApproveTickets && showReject && !ticket.rejected_at && (
                                                <div className="space-y-2">
                                                    <Textarea
                                                        placeholder="Alasan penolakan..."
                                                        rows={2}
                                                        value={rejectReason}
                                                        onChange={(e) => setRejectReason(e.target.value)}
                                                    />
                                                    <Button size="sm" variant="destructive" onClick={reject}>
                                                        Kirim Penolakan
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })()}

                            {/* Timeline & Dates */}
                            {(ticket.timeline || ticket.due_date) && (
                                <div className="mb-4">
                                    <Label className="text-sm font-semibold">
                                        Timeline & Tanggal
                                    </Label>
                                    <div className="mt-2">
                                        {ticket.timeline && (
                                            <div className="mb-2">
                                                <Label className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <IconCalendar className="h-3 w-3" />
                                                    Timeline
                                                </Label>
                                                <p className="text-sm">
                                                    {ticket.timeline.title}
                                                </p>
                                            </div>
                                        )}

                                        {ticket.due_date && (
                                            <div className="mb-2">
                                                <Label className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <IconCalendar className="h-3 w-3" />
                                                    Tenggat
                                                </Label>
                                                <p className="text-sm">
                                                    {new Date(
                                                        ticket.due_date,
                                                    ).toLocaleDateString('id-ID', {
                                                        day: 'numeric',
                                                        month: 'long',
                                                        year: 'numeric',
                                                    })}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Tags */}
                            {ticket.tags && ticket.tags.length > 0 && (
                                <div className="mb-4">
                                    <Label className="flex items-center gap-2 text-sm font-semibold">
                                        <IconTag className="h-4 w-4" />
                                        Tag
                                    </Label>
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {ticket.tags.map((tag, index) => (
                                            <Badge
                                                key={index}
                                                variant="outline"
                                                className="text-xs"
                                            >
                                                {tag}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Effort Tracking */}
                            {(ticket.estimated_hours || ticket.actual_hours) && (
                                <div className="mb-4">
                                    <Label className="text-sm font-semibold">
                                        Pelacakan Effort
                                    </Label>
                                    <div className="mt-2 grid gap-3 sm:grid-cols-2">
                                        {ticket.estimated_hours && (
                                            <div className="mb-2">
                                                <Label className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <IconClock className="h-3 w-3" />
                                                    Estimasi Jam
                                                </Label>
                                                <p className="text-sm">
                                                    {ticket.estimated_hours} jam
                                                </p>
                                            </div>
                                        )}

                                        {ticket.actual_hours && (
                                            <div className="mb-2">
                                                <Label className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <IconClock className="h-3 w-3" />
                                                    Jam Aktual
                                                </Label>
                                                <p className="text-sm">
                                                    {ticket.actual_hours} jam
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    <Separator />

                    {/* Comments Section */}
                    <TicketComments ticket={ticket} allUsers={allUsers} />
                </div>
            </DialogContent>

            <FilePreviewDialog 
                file={previewFile}
                ticketId={ticket.id}
                open={previewOpen}
                onOpenChange={setPreviewOpen}
            />

            <ConfirmDialog
                open={deletingAttachment !== null}
                onOpenChange={(open) => !open && setDeletingAttachment(null)}
                title="Hapus Lampiran?"
                description={`Apakah Anda yakin ingin menghapus lampiran "${deletingAttachment?.attachment.name}"?`}
                confirmLabel="Ya, Hapus"
                loading={isDeletingFile}
                onConfirm={() => {
                    if (!deletingAttachment) return;
                    setIsDeletingFile(true);
                    router.delete(`/tickets/${ticket.id}/attachments`, {
                        data: { index: deletingAttachment.index, filename: deletingAttachment.attachment.name },
                        preserveScroll: true,
                        onSuccess: () => {
                            setDeletingAttachment(null);
                            setIsDeletingFile(false);
                        },
                        onError: () => {
                            setIsDeletingFile(false);
                        },
                    });
                }}
            />
        </Dialog>
    );
}
