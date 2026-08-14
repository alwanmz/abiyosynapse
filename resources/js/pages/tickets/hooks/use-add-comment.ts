import { router } from '@inertiajs/react';
import { useState } from 'react';
import { validateTicketAttachmentFiles } from '../constants/attachment-limits';

interface UseAddCommentProps {
    ticketId: number;
    onSuccess?: () => void;
}

export function useAddComment({ ticketId, onSuccess }: UseAddCommentProps) {
    const [comment, setComment] = useState('');
    const [attachments, setAttachments] = useState<File[]>([]);
    const [attachmentErrors, setAttachmentErrors] = useState<string[]>([]);
    const [isInternal, setIsInternal] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!comment.trim()) return;

        setIsSubmitting(true);

        const formData = new FormData();
        formData.append('comment', comment);
        formData.append('is_internal', isInternal ? '1' : '0');

        attachments.forEach((file) => {
            formData.append('attachments[]', file);
        });

        router.post(`/tickets/${ticketId}/comments`, formData, {
            preserveScroll: true,
            onSuccess: () => {
                setComment('');
                setAttachments([]);
                setAttachmentErrors([]);
                setIsInternal(false);
                setIsSubmitting(false);
                onSuccess?.();
            },
            onError: () => {
                setIsSubmitting(false);
            },
        });
    };

    const handleFileChange = (files: FileList | null) => {
        if (files) {
            const { accepted, rejected } = validateTicketAttachmentFiles(Array.from(files));
            setAttachments(accepted);
            setAttachmentErrors(rejected);
        }
    };

    return {
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
    };
}
