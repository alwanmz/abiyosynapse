import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import ClientPortalLayout from '@/layouts/client-portal-layout';
import {
    TICKET_PRIORITIES,
    type TicketPriority,
} from '@/pages/tickets/constants/ticket-priorities';
import {
    TICKET_STATUSES,
    type TicketStatusId,
} from '@/pages/tickets/constants/ticket-statuses';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { IconMessageCircle, IconPaperclip, IconPlus } from '@tabler/icons-react';
import { useState } from 'react';
import NewTicketDialog from '../components/new-ticket-dialog';
import TicketDetailDialog from '../components/ticket-detail-dialog';
import { type PortalQuota, type PortalTicket } from './types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tiket Saya', href: '/portal/tickets' },
];

function StatusBadge({ status }: { status: TicketStatusId }) {
    const meta = TICKET_STATUSES.find((s) => s.id === status);
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

function formatDate(value: string | null): string {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

export default function ClientTicketIndex({
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

    return (
        <ClientPortalLayout breadcrumbs={breadcrumbs}>
            <Head title="Tiket Saya" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Tiket Saya
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Pantau progres pengerjaan tiket Anda secara
                            real-time.
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

                <div className="w-full overflow-x-auto rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nomor</TableHead>
                                <TableHead>Judul</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Prioritas</TableHead>
                                <TableHead>Dibuat</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tickets.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="py-10 text-center text-sm text-muted-foreground"
                                    >
                                        Belum ada tiket.
                                    </TableCell>
                                </TableRow>
                            )}
                            {tickets.map((ticket) => (
                                <TableRow
                                    key={ticket.id}
                                    onClick={() => openDetail(ticket.id)}
                                    className="cursor-pointer"
                                >
                                    <TableCell className="font-mono text-xs font-medium text-blue-600 dark:text-blue-400">
                                        {ticket.ticket_number}
                                    </TableCell>
                                    <TableCell className="max-w-md">
                                        <span className="flex items-center gap-2 font-medium">
                                            {ticket.title}
                                            {!!ticket.comments_count && (
                                                <span className="inline-flex items-center gap-0.5 text-xs text-muted-foreground">
                                                    <IconMessageCircle className="h-3.5 w-3.5" />
                                                    {ticket.comments_count}
                                                </span>
                                            )}
                                            {!!ticket.attachments_count && (
                                                <span className="inline-flex items-center gap-0.5 text-xs text-muted-foreground">
                                                    <IconPaperclip className="h-3.5 w-3.5" />
                                                    {ticket.attachments_count}
                                                </span>
                                            )}
                                        </span>
                                        {ticket.project && (
                                            <div className="text-xs text-muted-foreground">
                                                {ticket.project.name}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={ticket.status} />
                                    </TableCell>
                                    <TableCell>
                                        <PriorityBadge
                                            priority={ticket.priority}
                                        />
                                    </TableCell>
                                    <TableCell className="text-sm text-muted-foreground">
                                        {formatDate(ticket.created_at)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
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
