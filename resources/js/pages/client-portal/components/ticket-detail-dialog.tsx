import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useCallback, useEffect, useState } from 'react';
import { type PortalTicketDetail } from '../tickets/types';
import TicketDetailBody from './ticket-detail-body';

interface Props {
    ticketId: number | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/**
 * Fetches a ticket's full detail (ownership-checked JSON endpoint) on open and
 * renders the shared read-only body. Reused by the Kanban board and the table.
 */
export default function TicketDetailDialog({
    ticketId,
    open,
    onOpenChange,
}: Props) {
    const [ticket, setTicket] = useState<PortalTicketDetail | null>(null);
    const [loading, setLoading] = useState(false);

    const load = useCallback(async () => {
        if (!ticketId) return;
        setLoading(true);
        try {
            const res = await fetch(`/portal/tickets/${ticketId}/detail`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                setTicket(data.ticket);
            }
        } finally {
            setLoading(false);
        }
    }, [ticketId]);

    useEffect(() => {
        if (open && ticketId) {
            setTicket(null);
            load();
        }
    }, [open, ticketId, load]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                <DialogHeader className="sr-only">
                    <DialogTitle>Detail Tiket</DialogTitle>
                </DialogHeader>
                {loading || !ticket ? (
                    <div className="flex items-center justify-center py-20">
                        <Spinner />
                    </div>
                ) : (
                    <TicketDetailBody ticket={ticket} onCommented={load} />
                )}
            </DialogContent>
        </Dialog>
    );
}
