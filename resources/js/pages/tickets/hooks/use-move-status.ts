import { toast } from '@/components/ui/toast';
import { Ticket } from '@/types/ticket';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface UseMoveStatusProps {
    ticket: Ticket;
    onSuccess?: () => void;
}

export function useMoveStatus({ ticket, onSuccess }: UseMoveStatusProps) {
    const [selectedStatus, setSelectedStatus] = useState('');
    const [reviewNotes, setReviewNotes] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = () => {
        if (!selectedStatus || selectedStatus === ticket.status) {
            onSuccess?.();
            return;
        }

        setIsSubmitting(true);

        const payload: Record<string, string> = { status: selectedStatus };
        // Only touch review_notes when flagging "Belum Sesuai" so moving a ticket
        // forward later doesn't wipe an existing review note.
        if (selectedStatus === 'not-appropriate') {
            payload.review_notes = reviewNotes;
        }

        router.patch(
            `/tickets/${ticket.id}/move`,
            payload,
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSubmitting(false);
                    setReviewNotes('');
                    onSuccess?.();
                },
                onError: (errors: Record<string, string>) => {
                    setIsSubmitting(false);
                    const msg =
                        errors?.status ||
                        Object.values(errors ?? {})[0] ||
                        'Terjadi kesalahan saat memindahkan tiket.';
                    toast.error('Gagal memindahkan tiket', { description: msg });
                },
            },
        );
    };

    const canSubmit =
        !isSubmitting && selectedStatus && selectedStatus !== ticket.status;

    return {
        selectedStatus,
        setSelectedStatus,
        reviewNotes,
        setReviewNotes,
        isSubmitting,
        handleSubmit,
        canSubmit,
    };
}
