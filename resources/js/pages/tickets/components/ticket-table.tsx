import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { UserAvatar } from '@/components/user-avatar';
import { userHasPermission } from '@/lib/permissions';
import { SharedData } from '@/types';
import { Ticket, Project, Timeline } from '@/types/ticket';
import { router, usePage } from '@inertiajs/react';
import { TICKET_PRIORITIES } from '../constants/ticket-priorities';
import { TICKET_STATUSES } from '../constants/ticket-statuses';
import { TicketDetailDialog } from './ticket-detail-dialog';
import { MoveStatusDialog } from './move-status-dialog';
import { useMemo, useState } from 'react';
import { IconArchive, IconArchiveOff, IconArrowRight, IconFlag } from '@tabler/icons-react';

interface TicketTableProps {
    tickets: Ticket[];
    projects: Project[];
    timelines: Timeline[];
}

export function TicketTable({ tickets }: TicketTableProps) {
    const [selectedTicket, setSelectedTicket] = useState<Ticket | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);
    const [moveTicket, setMoveTicket] = useState<Ticket | null>(null);
    const [moveOpen, setMoveOpen] = useState(false);

    // Filter bar (view Tabel saja)
    const [fKlien, setFKlien] = useState('all');
    const [fStatus, setFStatus] = useState('all');
    const [fPriority, setFPriority] = useState('all');

    const { auth } = usePage<SharedData>().props;
    const canMove =
        userHasPermission(auth, 'tickets.update') ||
        userHasPermission(auth, 'tickets.update-any');

    const clientOptions = useMemo(() => {
        const map = new Map<string, string>();
        tickets.forEach((t) => {
            if (t.client) map.set(String(t.client.id), t.client.nama);
        });
        return Array.from(map, ([id, nama]) => ({ id, nama })).sort((a, b) =>
            a.nama.localeCompare(b.nama),
        );
    }, [tickets]);

    const priorityOptions = useMemo(
        () => Object.entries(TICKET_PRIORITIES).map(([key, cfg]) => ({ key, label: cfg.label })),
        [],
    );

    const filtered = useMemo(
        () =>
            tickets.filter((t) => {
                const dStatus = t.status === 'backlog' ? 'todo' : t.status;
                const okKlien = fKlien === 'all' || String(t.client_id) === fKlien;
                const okStatus = fStatus === 'all' || dStatus === fStatus;
                const okPriority = fPriority === 'all' || t.priority === fPriority;
                return okKlien && okStatus && okPriority;
            }),
        [tickets, fKlien, fStatus, fPriority],
    );

    const handleRowClick = (ticket: Ticket) => {
        setSelectedTicket(ticket);
        setDetailOpen(true);
    };

    const handleMoveClick = (e: React.MouseEvent, ticket: Ticket) => {
        e.stopPropagation();
        setMoveTicket(ticket);
        setMoveOpen(true);
    };

    const handleArchiveClick = (e: React.MouseEvent, ticket: Ticket, archive: boolean) => {
        e.stopPropagation();
        router.post(`/tickets/${ticket.id}/${archive ? 'archive' : 'unarchive'}`, {}, { preserveScroll: true });
    };

    return (
        <>
            <div className="mb-3 flex flex-wrap items-center gap-2">
                <Select value={fKlien} onValueChange={setFKlien}>
                    <SelectTrigger className="h-9 w-48 shrink-0">
                        <SelectValue placeholder="Semua Klien" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Klien</SelectItem>
                        {clientOptions.map((c) => (
                            <SelectItem key={c.id} value={c.id}>
                                {c.nama}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select value={fStatus} onValueChange={setFStatus}>
                    <SelectTrigger className="h-9 w-44 shrink-0">
                        <SelectValue placeholder="Semua Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Status</SelectItem>
                        {TICKET_STATUSES.map((s) => (
                            <SelectItem key={s.id} value={s.id}>
                                {s.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Select value={fPriority} onValueChange={setFPriority}>
                    <SelectTrigger className="h-9 w-40 shrink-0">
                        <SelectValue placeholder="Semua Prioritas" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Prioritas</SelectItem>
                        {priorityOptions.map((p) => (
                            <SelectItem key={p.key} value={p.key}>
                                {p.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {(fKlien !== 'all' || fStatus !== 'all' || fPriority !== 'all') && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-9"
                        onClick={() => {
                            setFKlien('all');
                            setFStatus('all');
                            setFPriority('all');
                        }}
                    >
                        Reset
                    </Button>
                )}
            </div>

            <div className="rounded-md border bg-card overflow-hidden">
                <Table>
                    <TableHeader className="bg-muted/50">
                        <TableRow>
                            <TableHead className="w-[100px]">ID</TableHead>
                            <TableHead className="min-w-[200px]">Judul</TableHead>
                            <TableHead>Klien</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Prioritas</TableHead>
                            <TableHead>Ditugaskan Ke</TableHead>
                            <TableHead className="text-right">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filtered.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                    Tiket tidak ditemukan.
                                </TableCell>
                            </TableRow>
                        ) : (
                            filtered.map((ticket) => {
                                const priority = TICKET_PRIORITIES[ticket.priority];
                                const displayStatus = ticket.status === 'backlog' ? 'todo' : ticket.status;
                                const status = TICKET_STATUSES.find(s => s.id === displayStatus);

                                return (
                                    <TableRow
                                        key={ticket.id}
                                        className="cursor-pointer hover:bg-muted/30 transition-colors"
                                        onClick={() => handleRowClick(ticket)}
                                    >
                                        <TableCell className="font-mono text-xs font-medium">
                                            {ticket.ticket_number}
                                        </TableCell>
                                        <TableCell className="font-medium max-w-[300px]">
                                            <div className="flex flex-col">
                                                <span className="truncate">{ticket.title}</span>
                                                <span className="text-[10px] text-muted-foreground line-clamp-1">
                                                    {ticket.type.toUpperCase()}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {ticket.client?.nama || '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={`text-[10px] h-5 border-none text-white ${status?.color || 'bg-slate-500'}`}
                                            >
                                                {status?.label || ticket.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-1.5">
                                                <IconFlag className={`h-3 w-3 ${priority.color}`} />
                                                <span className={`text-xs ${priority.color}`}>
                                                    {priority.label}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {ticket.assignees && ticket.assignees.length > 0 ? (
                                                <div className="flex items-center gap-2">
                                                    <UserAvatar
                                                        user={ticket.assignees[0]}
                                                        className="h-6 w-6"
                                                        fallbackClassName="text-[10px]"
                                                    />
                                                    <span className="text-xs">
                                                        {ticket.assignees[0].name.split(' ')[0]}
                                                        {ticket.assignees.length > 1 ? ` +${ticket.assignees.length - 1}` : ''}
                                                    </span>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-muted-foreground italic">Belum Ditugaskan</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {!canMove ? (
                                                <span className="text-xs text-muted-foreground">-</span>
                                            ) : ticket.status === 'done' ? (
                                                ticket.archived_at ? (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-7"
                                                        onClick={(e) => handleArchiveClick(e, ticket, false)}
                                                    >
                                                        <IconArchiveOff className="mr-1 h-3.5 w-3.5" />
                                                        Batal Arsip
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-7"
                                                        onClick={(e) => handleArchiveClick(e, ticket, true)}
                                                    >
                                                        <IconArchive className="mr-1 h-3.5 w-3.5" />
                                                        Arsipkan
                                                    </Button>
                                                )
                                            ) : (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-7"
                                                    onClick={(e) => handleMoveClick(e, ticket)}
                                                >
                                                    <IconArrowRight className="mr-1 h-3.5 w-3.5" />
                                                    Pindahkan
                                                </Button>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                );
                            })
                        )}
                    </TableBody>
                </Table>
            </div>

            {selectedTicket && (
                <TicketDetailDialog
                    ticket={selectedTicket}
                    open={detailOpen}
                    onOpenChange={setDetailOpen}
                />
            )}

            {moveTicket && (
                <MoveStatusDialog
                    ticket={moveTicket}
                    open={moveOpen}
                    onOpenChange={setMoveOpen}
                />
            )}
        </>
    );
}
