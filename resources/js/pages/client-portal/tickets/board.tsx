import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import ClientPortalLayout from '@/layouts/client-portal-layout';
import { TICKET_PRIORITIES } from '@/pages/tickets/constants/ticket-priorities';
import { TICKET_STATUSES } from '@/pages/tickets/constants/ticket-statuses';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import {
    IconMessageCircle,
    IconPaperclip,
    IconPlus,
} from '@tabler/icons-react';
import { useMemo, useState } from 'react';
import NewTicketDialog from '../components/new-ticket-dialog';
import TicketDetailDialog from '../components/ticket-detail-dialog';
import { type PortalQuota, type PortalTicket } from './types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Papan Kanban', href: '/portal/kanban' },
];

function PortalTicketCard({
    ticket,
    onClick,
}: {
    ticket: PortalTicket;
    onClick: () => void;
}) {
    const priorityMeta = TICKET_PRIORITIES[ticket.priority];
    return (
        <button
            type="button"
            onClick={onClick}
            className="flex w-full flex-col gap-2 rounded-lg border bg-card p-3 text-left shadow-sm transition-colors hover:border-primary/50"
        >
            <div className="flex items-center justify-between gap-2">
                <span className="font-mono text-xs text-muted-foreground">
                    {ticket.ticket_number}
                </span>
                {priorityMeta && (
                    <Badge
                        variant="outline"
                        className={`${priorityMeta.badgeClassName} text-[10px]`}
                    >
                        {priorityMeta.label}
                    </Badge>
                )}
            </div>
            <p className="line-clamp-3 text-sm font-medium">{ticket.title}</p>
            <div className="flex items-center gap-3 text-xs text-muted-foreground">
                {!!ticket.comments_count && (
                    <span className="flex items-center gap-1">
                        <IconMessageCircle className="h-3.5 w-3.5" />
                        {ticket.comments_count}
                    </span>
                )}
                {!!ticket.attachments_count && (
                    <span className="flex items-center gap-1">
                        <IconPaperclip className="h-3.5 w-3.5" />
                        {ticket.attachments_count}
                    </span>
                )}
            </div>
        </button>
    );
}

export default function ClientTicketBoard({
    tickets,
    quota,
}: {
    tickets: PortalTicket[];
    quota: PortalQuota;
}) {
    const [activeId, setActiveId] = useState<number | null>(null);
    const [open, setOpen] = useState(false);
    const [creating, setCreating] = useState(false);

    const openDetail = (id: number) => {
        setActiveId(id);
        setOpen(true);
    };

    // Group tickets by status; fold "backlog" into the "todo" column.
    const grouped = useMemo(() => {
        const map: Record<string, PortalTicket[]> = {};
        for (const s of TICKET_STATUSES) map[s.id] = [];
        for (const ticket of tickets) {
            const key =
                (ticket.status as string) === 'backlog'
                    ? 'todo'
                    : ticket.status;
            (map[key] ??= []).push(ticket);
        }
        return map;
    }, [tickets]);

    return (
        <ClientPortalLayout breadcrumbs={breadcrumbs}>
            <Head title="Papan Kanban" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Papan Kanban
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Lihat sebaran tiket Anda di setiap tahap pengerjaan.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant="secondary">
                            {quota.unlimited
                                ? 'Request: Unlimited'
                                : `Sisa request: ${quota.remaining}/${quota.limit}`}
                        </Badge>
                        <Button size="sm" onClick={() => setCreating(true)}>
                            <IconPlus className="mr-1 h-4 w-4" /> Buat Tiket
                        </Button>
                    </div>
                </div>

                <div className="flex gap-4 overflow-x-auto pb-4">
                    {TICKET_STATUSES.map((status) => {
                        const items = grouped[status.id] ?? [];
                        return (
                            <div
                                key={status.id}
                                className="flex w-72 shrink-0 flex-col rounded-xl border bg-muted/30"
                            >
                                <div className="flex items-center justify-between gap-2 border-b px-3 py-2.5">
                                    <div className="flex items-center gap-2">
                                        <span
                                            className={`h-2.5 w-2.5 rounded-full ${status.color}`}
                                        />
                                        <span className="text-sm font-semibold">
                                            {status.label}
                                        </span>
                                    </div>
                                    <span className="rounded-full bg-background px-2 py-0.5 text-xs text-muted-foreground">
                                        {items.length}
                                    </span>
                                </div>
                                <div className="flex flex-col gap-2 p-2">
                                    {items.length === 0 ? (
                                        <p className="px-1 py-6 text-center text-xs text-muted-foreground">
                                            Kosong
                                        </p>
                                    ) : (
                                        items.map((ticket) => (
                                            <PortalTicketCard
                                                key={ticket.id}
                                                ticket={ticket}
                                                onClick={() =>
                                                    openDetail(ticket.id)
                                                }
                                            />
                                        ))
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            <NewTicketDialog
                quota={quota}
                open={creating}
                onOpenChange={setCreating}
            />

            <TicketDetailDialog
                ticketId={activeId}
                open={open}
                onOpenChange={setOpen}
            />
        </ClientPortalLayout>
    );
}
