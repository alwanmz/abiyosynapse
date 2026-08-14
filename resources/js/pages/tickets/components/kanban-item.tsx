import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { TicketCard } from './ticket-card';

interface Ticket {
    id: number;
    ticket_number: string;
    title: string;
    status: string;
    priority: string;
    type: string;
    assigned_user?: { name: string } | null;
    comments_count?: number;
    attachments?: any[] | null;
}

interface KanbanItemProps {
    ticket: Ticket;
    projects: any[];
    timelines: any[];
    clients: any[];
    allUsers?: any[];
}

export function KanbanItem({ ticket, projects, timelines, clients, allUsers = [] }: KanbanItemProps) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: ticket.id });

    const style = {
        transform: CSS.Translate.toString(transform),
        transition,
        opacity: isDragging ? 0.3 : 1,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            {...attributes}
            {...listeners}
            className="cursor-grab active:cursor-grabbing w-full"
        >
            {/* Wrap in a container to prevent dnd-kit from eating all clicks, 
                though TicketCard is clickable, listeners on the parent should handle drag initiation */}
            <TicketCard ticket={ticket as any} projects={projects} clients={clients} timelines={timelines} allUsers={allUsers} />
        </div>
    );
}
