import { TicketComment } from '@/types/ticket';
import { router } from '@inertiajs/react';
import { useState } from 'react';

export function useEditComment(comment: TicketComment) {
    const [isEditing, setIsEditing] = useState(false);
    const [editedComment, setEditedComment] = useState(comment.comment);
    const [isInternal, setIsInternal] = useState(comment.is_internal);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleUpdate = () => {
        if (!editedComment.trim()) return;

        setIsSubmitting(true);

        router.put(
            `/tickets/comments/${comment.id}`,
            {
                comment: editedComment,
                is_internal: isInternal,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsEditing(false);
                    setIsSubmitting(false);
                },
                onError: () => {
                    setIsSubmitting(false);
                },
            },
        );
    };

    const handleCancel = () => {
        setEditedComment(comment.comment);
        setIsInternal(comment.is_internal);
        setIsEditing(false);
    };

    return {
        isEditing,
        setIsEditing,
        editedComment,
        setEditedComment,
        isInternal,
        setIsInternal,
        isSubmitting,
        handleUpdate,
        handleCancel,
    };
}
