import { Separator } from '@/components/ui/separator';
import { Ticket } from '@/types/ticket';
import { IconMessage } from '@tabler/icons-react';
import { AddCommentForm } from './add-comment-form';
import { CommentCard } from './comment-card';

interface TicketCommentsProps {
    ticket: Ticket;
    allUsers?: any[];
}

export function TicketComments({ ticket, allUsers = [] }: TicketCommentsProps) {
    const comments = ticket.comments || [];

    return (
        <div className="space-y-4">
            <div className="flex items-center gap-2">
                <IconMessage className="h-5 w-5" />
                <h3 className="font-semibold">
                    Komentar ({comments.length})
                </h3>
            </div>

            <AddCommentForm ticketId={ticket.id} users={allUsers} />

            {comments.length > 0 && (
                <>
                    <Separator />
                    <div className="space-y-3">
                        {comments.map((comment) => (
                            <CommentCard key={comment.id} comment={comment} />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
