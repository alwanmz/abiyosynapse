import { ConfirmDialog } from '@/components/confirm-dialog';
import { UserAvatar } from '@/components/user-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { CommentReactions } from '@/components/comment-reactions';
import { MentionText } from '@/components/mention-text';
import { TicketComment } from '@/types/ticket';
import { usePage } from '@inertiajs/react';
import {
    IconEdit,
    IconLock,
    IconPaperclip,
    IconTrash,
} from '@tabler/icons-react';
import { useState } from 'react';
import { useDeleteComment } from '../hooks/use-delete-comment';
import { useEditComment } from '../hooks/use-edit-comment';

interface CommentCardProps {
    comment: TicketComment;
}

export function CommentCard({ comment }: CommentCardProps) {
    const { auth } = usePage().props as any;
    const isOwner = auth.user.id === comment.user_id;

    const [showDelete, setShowDelete] = useState(false);

    const { isDeleting, handleDelete } = useDeleteComment(comment.id);

    const {
        isEditing,
        setIsEditing,
        editedComment,
        setEditedComment,
        isInternal,
        setIsInternal,
        isSubmitting,
        handleUpdate,
        handleCancel,
    } = useEditComment(comment);

    const formatDate = (date: string) => {
        return new Date(date).toLocaleString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    // Comments come from either a staff user or a client (via the client
    // portal) — never both. `comment.user` is null for client-authored
    // comments, so fall back to `comment.client` for the name/avatar.
    const isFromClient = !comment.user && !!comment.client;
    const authorName = comment.user?.name ?? comment.client?.nama ?? 'Client';
    const avatarUser = comment.user ?? (comment.client ? { name: comment.client.nama } : null);

    return (
        <div className="flex gap-3 rounded-lg border bg-card p-4">
            <UserAvatar
                user={avatarUser}
                className="h-8 w-8"
                fallbackClassName="text-xs"
            />

            <div className="flex-1 space-y-2">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex flex-wrap items-center gap-1.5 sm:gap-2">
                        <span className="font-semibold text-sm">
                            {authorName}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {formatDate(comment.created_at)}
                        </span>
                        {isFromClient && (
                            <Badge variant="outline" className="h-5 text-xs">
                                Client
                            </Badge>
                        )}
                        {comment.is_internal && (
                            <Badge variant="secondary" className="h-5 text-xs">
                                <IconLock className="mr-1 h-3 w-3" />
                                Internal
                            </Badge>
                        )}
                    </div>

                    {isOwner && !isEditing && (
                        <div className="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7"
                                onClick={() => setIsEditing(true)}
                            >
                                <IconEdit className="h-3.5 w-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-7 w-7 text-destructive hover:text-destructive"
                                onClick={() => setShowDelete(true)}
                                disabled={isDeleting}
                            >
                                <IconTrash className="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    )}
                </div>

                {isEditing ? (
                    <div className="space-y-2">
                        <Textarea
                            value={editedComment}
                            onChange={(e) => setEditedComment(e.target.value)}
                            className="min-h-[80px]"
                            disabled={isSubmitting}
                        />
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id={`internal-${comment.id}`}
                                    checked={isInternal}
                                    onCheckedChange={(checked) =>
                                        setIsInternal(checked as boolean)
                                    }
                                    disabled={isSubmitting}
                                />
                                <Label
                                    htmlFor={`internal-${comment.id}`}
                                    className="text-sm font-normal"
                                >
                                    Internal note
                                </Label>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleCancel}
                                    disabled={isSubmitting}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={handleUpdate}
                                    disabled={isSubmitting}
                                >
                                    {isSubmitting ? 'Saving...' : 'Save'}
                                </Button>
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="space-y-1">
                        <p className="text-sm leading-relaxed whitespace-pre-wrap">
                            <MentionText text={comment.comment} />
                        </p>
                        <CommentReactions
                            commentId={comment.id}
                            reactions={comment.reactions as any}
                        />
                    </div>
                )}

                {comment.attachments && comment.attachments.length > 0 && (
                    <div className="mt-2 space-y-2">
                        {comment.attachments.map((attachment, index) => {
                            const isImage = attachment.mime_type.startsWith('image/');
                            const attachmentUrl = attachment.url ?? `/storage/${attachment.path}`;

                            return isImage ? (
                                <a
                                    key={index}
                                    href={attachmentUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="block max-w-md overflow-hidden rounded-lg border border-border transition-all hover:border-primary"
                                >
                                    <img
                                        src={attachmentUrl}
                                        alt={attachment.name}
                                        className="max-h-50 w-full object-contain"
                                        loading="lazy"
                                    />
                                    <div className="bg-muted/50 px-3 py-2">
                                        <p className="text-xs text-muted-foreground">
                                            {attachment.name} ({(attachment.size / 1024).toFixed(1)} KB)
                                        </p>
                                    </div>
                                </a>
                            ) : (
                                <a
                                    key={index}
                                    href={attachmentUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2 text-xs transition-all hover:border-primary hover:bg-muted/50"
                                >
                                    <IconPaperclip className="h-4 w-4 text-muted-foreground" />
                                    <span className="flex-1 font-medium">{attachment.name}</span>
                                    <span className="text-muted-foreground">
                                        ({(attachment.size / 1024).toFixed(1)} KB)
                                    </span>
                                </a>
                            );
                        })}
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={showDelete}
                onOpenChange={setShowDelete}
                title="Hapus komentar?"
                description="Komentar ini akan dihapus permanen dan tidak bisa dipulihkan."
                confirmLabel="Ya, Hapus"
                loading={isDeleting}
                onConfirm={() => {
                    handleDelete();
                    setShowDelete(false);
                }}
            />
        </div>
    );
}
