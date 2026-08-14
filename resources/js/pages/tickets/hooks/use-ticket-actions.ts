import { Ticket } from '@/types/ticket';
import { router } from '@inertiajs/react';

interface UseTicketActionsProps {
    ticket: Ticket;
    onDeleteSuccess?: () => void;
}

export function useTicketActions({
    ticket,
    onDeleteSuccess,
}: UseTicketActionsProps) {
    const handleDelete = () => {
        router.delete(`/tickets/${ticket.id}`, {
            onSuccess: () => {
                onDeleteSuccess?.();
            },
        });
    };

    const handleArchive = () => {
        router.post(`/tickets/${ticket.id}/archive`, {}, { preserveScroll: true });
    };

    const handleUnarchive = () => {
        router.post(`/tickets/${ticket.id}/unarchive`, {}, { preserveScroll: true });
    };

    return {
        handleDelete,
        handleArchive,
        handleUnarchive,
    };
}
