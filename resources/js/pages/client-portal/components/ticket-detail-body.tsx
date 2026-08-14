import InputError from '@/components/input-error';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { CommentReactions } from '@/components/comment-reactions';
import { MentionText } from '@/components/mention-text';
import { MentionTextarea } from '@/components/mention-textarea';
import { FilePreviewDialog } from '@/pages/tickets/components/file-preview-dialog';
import {
    TICKET_PRIORITIES,
    type TicketPriority,
} from '@/pages/tickets/constants/ticket-priorities';
import {
    TICKET_STATUSES,
    type TicketStatusId,
} from '@/pages/tickets/constants/ticket-statuses';
import { router } from '@inertiajs/react';
import { IconFile, IconFileText, IconPaperclip, IconX } from '@tabler/icons-react';
import { useRef, useState } from 'react';
import { type FileAttachment, type PortalTicketDetail } from '../tickets/types';

function statusMeta(id: TicketStatusId) {
    return TICKET_STATUSES.find((s) => s.id === id);
}

function StatusBadge({ status }: { status: TicketStatusId }) {
    const meta = statusMeta(status);
    if (!meta) return <Badge variant="outline">{status}</Badge>;
    const Icon = meta.icon;
    return (
        <Badge variant="outline" className={`gap-1 ${meta.badgeClassName}`}>
            <Icon className="h-3 w-3" />
            {meta.label}
        </Badge>
    );
}

function PriorityBadge({ priority }: { priority: TicketPriority }) {
    const meta = TICKET_PRIORITIES[priority];
    if (!meta) return <Badge variant="outline">{priority}</Badge>;
    return (
        <Badge variant="outline" className={meta.badgeClassName}>
            {meta.label}
        </Badge>
    );
}

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function AttachmentTile({
    file,
    onOpen,
    onDelete,
}: {
    file: FileAttachment;
    onOpen: () => void;
    onDelete?: () => void;
}) {
    const isImage = file.mime_type.startsWith('image/');
    return (
        <div className="group relative block aspect-video rounded-lg border bg-muted/30 overflow-hidden text-muted-foreground transition-colors hover:border-primary/50 cursor-pointer">
            <button
                type="button"
                onClick={onOpen}
                className="h-full w-full flex items-center justify-center"
            >
                {isImage ? (
                    <img
                        src={file.url}
                        alt={file.name}
                        className="h-full w-full object-cover transition-transform group-hover:scale-105"
                        loading="lazy"
                    />
                ) : (
                    <div className="flex flex-col items-center gap-1 p-2 text-center">
                        <IconFileText className="h-8 w-8" />
                        <span className="line-clamp-2 text-xs">{file.name}</span>
                    </div>
                )}
            </button>

            {onDelete && (
                <Button
                    type="button"
                    variant="destructive"
                    size="icon"
                    className="absolute top-1.5 right-1.5 h-6 w-6 rounded-full opacity-0 shadow-md transition-opacity group-hover:opacity-100 z-10"
                    title="Hapus lampiran"
                    onClick={(e) => {
                        e.stopPropagation();
                        onDelete();
                    }}
                >
                    <IconX className="h-3.5 w-3.5" />
                </Button>
            )}
        </div>
    );
}

/**
 * Shared read-only ticket detail content, reused by the full-page view and the
 * in-place detail dialog. `onCommented` lets the container refresh after a reply.
 */
export default function TicketDetailBody({
    ticket,
    onCommented,
}: {
    ticket: PortalTicketDetail;
    onCommented?: () => void;
}) {
    const [preview, setPreview] = useState<FileAttachment | null>(null);
    const [deletingFile, setDeletingFile] = useState<{ file: FileAttachment; index: number } | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const priorityMeta = TICKET_PRIORITIES[ticket.priority];

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-col gap-2">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="font-mono text-sm text-muted-foreground">
                        {ticket.ticket_number}
                    </span>
                    <StatusBadge status={ticket.status} />
                    {priorityMeta && (
                        <PriorityBadge priority={ticket.priority} />
                    )}
                </div>
                <h1 className="text-2xl font-bold tracking-tight">
                    {ticket.title}
                </h1>
                {ticket.project && (
                    <p className="text-sm text-muted-foreground">
                        Proyek: {ticket.project.name}
                    </p>
                )}
            </div>

            {ticket.description && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Deskripsi</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                            {ticket.description}
                        </p>
                    </CardContent>
                </Card>
            )}

            {ticket.attachments.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Lampiran ({ticket.attachments.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {ticket.attachments.map((file, idx) => (
                                <AttachmentTile
                                    key={file.url}
                                    file={file}
                                    onOpen={() => setPreview(file)}
                                    onDelete={() => setDeletingFile({ file, index: idx })}
                                />
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        Progres Pengerjaan
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {ticket.status_logs.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Belum ada perubahan status.
                        </p>
                    ) : (
                        <ol className="relative space-y-4 border-l border-border pl-6">
                            {ticket.status_logs.map((log) => {
                                const meta = statusMeta(log.to_status);
                                return (
                                    <li key={log.id} className="relative">
                                        <span
                                            className={`absolute -left-[1.72rem] mt-1 h-3 w-3 rounded-full ring-4 ring-background ${meta?.color ?? 'bg-slate-400'}`}
                                        />
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-sm font-medium">
                                                {meta?.label ?? log.to_status}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {formatDateTime(log.changed_at)}
                                            </span>
                                        </div>
                                    </li>
                                );
                            })}
                        </ol>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Diskusi</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    {ticket.comments.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Belum ada komentar. Mulai diskusi dengan tim kami di
                            bawah.
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-4">
                            {ticket.comments.map((comment) => (
                                <li
                                    key={comment.id}
                                    className="rounded-lg border bg-muted/30 p-3"
                                >
                                    <div className="mb-1 flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-semibold">
                                            {comment.author_name ?? 'Pengguna'}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className={
                                                comment.is_from_client
                                                    ? 'border-blue-300 bg-blue-100 text-blue-700'
                                                    : 'border-slate-300 bg-slate-100 text-slate-700'
                                            }
                                        >
                                            {comment.is_from_client
                                                ? 'Anda'
                                                : 'Tim'}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            {formatDateTime(comment.created_at)}
                                        </span>
                                    </div>
                                    <p className="text-sm leading-relaxed whitespace-pre-wrap">
                                        <MentionText text={comment.comment} users={ticket.users} />
                                    </p>
                                    <CommentReactions
                                        commentId={comment.id}
                                        reactions={comment.reactions}
                                        reactUrl={`/portal/tickets/comments/${comment.id}/react`}
                                    />
                                    {comment.attachments &&
                                        comment.attachments.length > 0 && (
                                            <div className="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                                {comment.attachments.map(
                                                    (file) => (
                                                        <AttachmentTile
                                                            key={file.url}
                                                            file={file}
                                                            onOpen={() =>
                                                                setPreview(file)
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        )}
                                </li>
                            ))}
                        </ul>
                    )}

                    <ReplyForm ticketId={ticket.id} users={ticket.users} onCommented={onCommented} />
                </CardContent>
            </Card>

            <FilePreviewDialog
                file={preview}
                open={preview !== null}
                onOpenChange={(open) => !open && setPreview(null)}
            />

            <ConfirmDialog
                open={deletingFile !== null}
                onOpenChange={(open) => !open && setDeletingFile(null)}
                title="Hapus Lampiran?"
                description={`Apakah Anda yakin ingin menghapus lampiran "${deletingFile?.file.name}"?`}
                confirmLabel="Ya, Hapus"
                loading={isDeleting}
                onConfirm={() => {
                    if (!deletingFile) return;
                    setIsDeleting(true);
                    router.delete(`/portal/tickets/${ticket.id}/attachments`, {
                        data: { index: deletingFile.index, filename: deletingFile.file.name },
                        preserveScroll: true,
                        onSuccess: () => {
                            setDeletingFile(null);
                            setIsDeleting(false);
                            onCommented?.();
                        },
                        onError: () => {
                            setIsDeleting(false);
                        },
                    });
                }}
            />
        </div>
    );
}

function ReplyForm({
    ticketId,
    users = [],
    onCommented,
}: {
    ticketId: number;
    users?: { id: number; name: string }[];
    onCommented?: () => void;
}) {
    const [comment, setComment] = useState('');
    const [attachments, setAttachments] = useState<File[]>([]);
    const [submitting, setSubmitting] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files) {
            setAttachments((prev) => [
                ...prev,
                ...Array.from(e.target.files!),
            ]);
        }
    };

    const removeAttachment = (index: number) => {
        setAttachments((prev) => prev.filter((_, i) => i !== index));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!comment.trim() || submitting) return;

        setSubmitting(true);
        const formData = new FormData();
        formData.append('comment', comment);
        attachments.forEach((file) => {
            formData.append('attachments[]', file);
        });

        router.post(`/portal/tickets/${ticketId}/comments`, formData, {
            preserveScroll: true,
            onSuccess: () => {
                setComment('');
                setAttachments([]);
                setSubmitting(false);
                onCommented?.();
            },
            onError: () => {
                setSubmitting(false);
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-3">
            <MentionTextarea
                value={comment}
                onValueChange={setComment}
                users={users}
                rows={3}
                placeholder="Tulis balasan atau pertanyaan Anda... (ketik @ untuk mention tim)"
                disabled={submitting}
            />

            {attachments.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {attachments.map((file, i) => (
                        <div
                            key={i}
                            className="flex items-center gap-1.5 rounded-md border border-primary/30 bg-primary/5 px-2.5 py-1 text-xs"
                        >
                            <IconFile className="h-3.5 w-3.5 shrink-0 text-primary" />
                            <span className="max-w-[160px] truncate">{file.name}</span>
                            <button
                                type="button"
                                onClick={() => removeAttachment(i)}
                                className="text-muted-foreground hover:text-destructive"
                            >
                                <IconX className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    ))}
                </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-2">
                <input
                    ref={fileInputRef}
                    type="file"
                    multiple
                    className="hidden"
                    onChange={handleFileChange}
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-8 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={submitting}
                >
                    <IconPaperclip className="h-4 w-4" />
                    Lampirkan File
                </Button>

                <Button type="submit" disabled={submitting || !comment.trim()}>
                    {submitting && <Spinner className="mr-2" />}
                    Kirim
                </Button>
            </div>
        </form>
    );
}
