import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { MentionTextarea, type MentionUser } from '@/components/mention-textarea';
import { IconFile, IconPaperclip, IconSend, IconX } from '@tabler/icons-react';
import { useRef } from 'react';
import { useAddComment } from '../hooks/use-add-comment';

interface AddCommentFormProps {
    ticketId: number;
    users?: MentionUser[];
}

export function AddCommentForm({ ticketId, users = [] }: AddCommentFormProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const {
        comment,
        setComment,
        attachments,
        setAttachments,
        attachmentErrors,
        isInternal,
        setIsInternal,
        isSubmitting,
        handleSubmit,
        handleFileChange,
    } = useAddComment({ ticketId });

    const removeAttachment = (index: number) => {
        setAttachments((prev) => prev.filter((_, i) => i !== index));
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-3">
            <div>
                <MentionTextarea
                    value={comment}
                    onValueChange={setComment}
                    users={users}
                    placeholder="Tulis komentar... (ketik @ untuk mention anggota tim)"
                    className="min-h-[90px]"
                    disabled={isSubmitting}
                />
            </div>

            {attachmentErrors.length > 0 && (
                <div className="text-xs text-destructive">
                    {attachmentErrors.join(' ')}
                </div>
            )}

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
                <div className="flex flex-wrap items-center gap-2 sm:gap-4">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="internal-note"
                            checked={isInternal}
                            onCheckedChange={(checked) =>
                                setIsInternal(checked as boolean)
                            }
                            disabled={isSubmitting}
                        />
                        <Label
                            htmlFor="internal-note"
                            className="text-sm font-normal text-muted-foreground"
                        >
                            Catatan Internal
                        </Label>
                    </div>

                    <input
                        ref={fileInputRef}
                        type="file"
                        multiple
                        className="hidden"
                        onChange={(e) => handleFileChange(e.target.files)}
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="h-8 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                        onClick={() => fileInputRef.current?.click()}
                        disabled={isSubmitting}
                    >
                        <IconPaperclip className="h-4 w-4" />
                        Lampirkan File
                    </Button>
                </div>

                <Button type="submit" disabled={isSubmitting || !comment.trim()}>
                    <IconSend className="mr-2 h-4 w-4" />
                    {isSubmitting ? 'Mengirim...' : 'Kirim'}
                </Button>
            </div>
        </form>
    );
}
