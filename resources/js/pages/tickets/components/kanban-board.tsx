import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    DndContext,
    DragOverlay,
    rectIntersection,
    useSensor,
    useSensors,
    type DragEndEvent,
    type DragStartEvent,
    PointerSensor,
} from '@dnd-kit/core';
import {
    SortableContext,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { router, usePage } from '@inertiajs/react';
import { IconFlag } from '@tabler/icons-react';
import { MessageSquare, Paperclip, User } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { KanbanColumn } from './kanban-column';
import { KanbanItem } from './kanban-item';
import { TicketCard } from './ticket-card';
import { toast } from '@/components/ui/toast';
import { userHasPermission } from '@/lib/permissions';
import { type SharedData } from '@/types';

// Colors mirror TICKET_STATUSES in constants/ticket-statuses.ts so column
// headers match the status badges shown elsewhere (table, detail dialog).
const columns = [
    { id: 'todo', title: 'To Do', color: 'bg-slate-500' },
    { id: 'pending', title: 'Menunggu Approval', color: 'bg-yellow-500' },
    { id: 'inprogress', title: 'Sedang Dikerjakan', color: 'bg-blue-500' },
    { id: 'qa-ready', title: 'Siap QA', color: 'bg-purple-500' },
    { id: 'qa-test', title: 'QA Test', color: 'bg-orange-500' },
    { id: 'review', title: 'Review', color: 'bg-indigo-500' },
    { id: 'not-appropriate', title: 'Belum Sesuai', color: 'bg-rose-500' },
    { id: 'done', title: 'Selesai', color: 'bg-green-500' },
];

const priorityColors: Record<string, string> = {
    highest: 'text-red-500',
    high: 'text-orange-500',
    medium: 'text-yellow-600',
    low: 'text-blue-500',
    lowest: 'text-slate-400',
};

interface Ticket {
    id: number;
    ticket_number: string;
    title: string;
    status: string;
    priority: string;
    type: string;
    task_type?: { id: number; nama: string } | null;
    request_type?: 'berbayar' | 'gratis' | null;
    assigned_user?: { name: string } | null;
    assignees?: { id: number }[] | null;
    comments_count?: number;
    attachments?: any[] | null;
    approved_at?: string | null;
    approved_by?: number | null;
    rejected_at?: string | null;
}

interface KanbanBoardProps {
    tickets: Ticket[];
    projects: any[];
    timelines: any[];
    clients: any[];
    allUsers?: any[];
    view?: 'active' | 'archive';
}

export function KanbanBoard({ tickets, projects, timelines, clients, allUsers = [], view = 'active' }: KanbanBoardProps) {
    // Di view Arsip, Kanban hanya menampilkan kolom "Selesai".
    const visibleColumns = view === 'archive' ? columns.filter((c) => c.id === 'done') : columns;
    const { auth } = usePage<SharedData>().props;
    const canMoveTickets =
        userHasPermission(auth, 'tickets.update') ||
        userHasPermission(auth, 'tickets.update-any');
    const [activeTicket, setActiveTicket] = useState<Ticket | null>(null);
    // Optimistic state: track local status overrides so the UI moves instantly
    const [optimisticStatuses, setOptimisticStatuses] = useState<Record<number, string>>({});

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: {
                distance: 8,
            },
        })
    );

    // Merge server tickets with optimistic local status overrides
    const effectiveTickets = useMemo(() => {
        return tickets.map((ticket) => {
            if (optimisticStatuses[ticket.id]) {
                return { ...ticket, status: optimisticStatuses[ticket.id] };
            }
            return ticket;
        });
    }, [tickets, optimisticStatuses]);

    const ticketsByStatus = useMemo(() => {
        const acc: Record<string, Ticket[]> = {};
        columns.forEach((col) => (acc[col.id] = []));
        effectiveTickets.forEach((ticket) => {
            const status = ticket.status === 'backlog' ? 'todo' : ticket.status;
            if (acc[status]) {
                acc[status].push(ticket);
            } else {
                acc['todo'].push(ticket);
            }
        });
        return acc;
    }, [effectiveTickets, canMoveTickets]);

    const handleDragStart = (event: DragStartEvent) => {
        const { active } = event;
        const ticket = effectiveTickets.find((t) => t.id === active.id);
        if (ticket) setActiveTicket(ticket);
    };

    const handleDragEnd = useCallback((event: DragEndEvent) => {
        const { active, over } = event;
        setActiveTicket(null);

        if (!over) return;

        const ticketId = active.id as number;
        let overId = over.id as string;

        // If dropping over a card, find its column
        const overTicket = effectiveTickets.find(t => t.id === over.id);
        if (overTicket) {
            overId = overTicket.status;
        }

        const draggedTicket = effectiveTickets.find(t => t.id === ticketId);
        if (!draggedTicket || draggedTicket.status === overId) return;

        // Tiket selesai bersifat final — tidak bisa dipindah lagi.
        if (draggedTicket.status === 'done') {
            toast.error('Tiket sudah selesai', {
                description: 'Tiket yang sudah selesai tidak bisa dipindah lagi.',
            });
            return;
        }

        // Integritas data: tiket tidak boleh keluar dari To Do tanpa Delegasi Tugas.
        if (draggedTicket.status === 'todo' && !(draggedTicket.assignees && draggedTicket.assignees.length > 0)) {
            toast.error('Delegasi Tugas belum diisi', {
                description: 'Isi Delegasi Tugas dulu sebelum memindahkan tiket dari To Do.',
            });
            return;
        }

        if (!canMoveTickets) {
            toast.error('Tidak punya akses', {
                description: 'Role Anda hanya boleh melihat tiket, belum boleh memindahkan status.',
            });
            return;
        }

        // Block rejected tickets from moving
        if (draggedTicket.rejected_at && !draggedTicket.approved_at) {
            toast.error('Tiket ditolak', {
                description: 'Tiket ini telah ditolak. Hubungi admin untuk review ulang sebelum bisa dipindah.',
            });
            return;
        }

        const needsApproval = draggedTicket.request_type === 'berbayar';
        if (needsApproval && overId !== 'pending' && !draggedTicket.approved_at) {
            toast.error('Tiket belum disetujui', {
                description: 'Tiket berbayar harus disetujui terlebih dahulu sebelum bisa dipindah status.',
            });
            return;
        }

        const oldStatus = draggedTicket.status;

        // Optimistic update: move card immediately in UI
        setOptimisticStatuses((prev) => ({ ...prev, [ticketId]: overId }));

        // Send to backend without triggering a full page reload
        router.patch(`/tickets/${ticketId}/move`, {
            status: overId,
        }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                // Clear optimistic override - server state is now up to date
                setOptimisticStatuses((prev) => {
                    const next = { ...prev };
                    delete next[ticketId];
                    return next;
                });
            },
            onError: () => {
                // Revert optimistic update on failure
                setOptimisticStatuses((prev) => {
                    const next = { ...prev };
                    delete next[ticketId];
                    return next;
                });
                toast.error('Gagal memindahkan tiket', {
                    description: 'Terjadi kesalahan saat memperbarui status. Silakan coba lagi.',
                });
            },
        });
    }, [effectiveTickets, canMoveTickets]);

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={rectIntersection}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
        >
            <div className="flex gap-4 overflow-x-auto pb-4 pt-2">
                {visibleColumns.map((column) => (
                    <KanbanColumn
                        key={column.id}
                        id={column.id}
                        title={column.title}
                        count={ticketsByStatus[column.id].length}
                        color={column.color}
                    >
                        <SortableContext
                            items={ticketsByStatus[column.id].map((t) => t.id)}
                            strategy={verticalListSortingStrategy}
                        >
                            <div className="flex min-h-[500px] flex-col gap-3">
                                {ticketsByStatus[column.id].map((ticket) => (
                                    <KanbanItem 
                                        key={ticket.id} 
                                        ticket={ticket} 
                                        projects={projects} 
                                        timelines={timelines} 
                                        clients={clients}
                                        allUsers={allUsers}
                                    />
                                ))}
                            </div>
                        </SortableContext>
                    </KanbanColumn>
                ))}
            </div>

            <DragOverlay>
                {activeTicket ? (
                    <div className="rotate-3 opacity-80 cursor-grabbing w-full max-w-[280px]">
                        <TicketCard ticket={activeTicket as any} projects={projects} clients={clients} timelines={timelines} allUsers={allUsers} />
                    </div>
                ) : null}
            </DragOverlay>
        </DndContext>
    );
}
