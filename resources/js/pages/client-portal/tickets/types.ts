import { type TicketPriority } from '@/pages/tickets/constants/ticket-priorities';
import { type TicketStatusId } from '@/pages/tickets/constants/ticket-statuses';

export interface FileAttachment {
    name: string;
    path: string;
    size: number;
    mime_type: string;
    url: string;
}

export interface StatusLog {
    id: number;
    from_status: TicketStatusId | null;
    to_status: TicketStatusId;
    changed_at: string;
    user_name: string | null;
}

export interface PortalComment {
    id: number;
    comment: string;
    attachments: FileAttachment[] | null;
    reactions?: { emoji: string; count: number; reacted_by_me: boolean }[];
    created_at: string;
    is_from_client: boolean;
    author_name: string | null;
    author_avatar?: string | null;
}

/** Monthly "permintaan baru" allowance shown on every portal page. */
export interface PortalQuota {
    /** null when the client is unlimited. */
    remaining: number | null;
    limit: number;
    unlimited: boolean;
}

/** Lightweight row used by the table and Kanban card. */
export interface PortalTicket {
    id: number;
    ticket_number: string;
    title: string;
    status: TicketStatusId;
    priority: TicketPriority;
    type: string;
    project?: { id: number; name: string } | null;
    task_type?: { id: number; nama: string } | null;
    attachments_count?: number;
    comments_count?: number;
    created_at: string;
    resolved_at: string | null;
}

/** Full detail returned by show()/detail(). */
export interface PortalTicketDetail {
    id: number;
    ticket_number: string;
    title: string;
    description: string | null;
    status: TicketStatusId;
    priority: TicketPriority;
    type: string;
    project?: { id: number; name: string } | null;
    task_type?: { id: number; nama: string } | null;
    created_at: string;
    resolved_at: string | null;
    attachments: FileAttachment[];
    status_logs: StatusLog[];
    comments: PortalComment[];
    users?: { id: number; name: string; avatar_path?: string | null }[];
}
