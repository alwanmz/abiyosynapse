export interface UserSummary {
    id: number;
    name: string;
    avatar_url?: string | null;
    avatar_path?: string | null;
    role?: { id: number; name: string; display_name?: string | null } | null;
}

export interface AttachmentMeta {
    name: string;
    path: string;
    disk?: string;
    url?: string;
    size: number;
    mime_type: string;
}

export interface TicketComment {
    id: number;
    ticket_id: number;
    user_id: number | null;
    client_id?: number | null;
    comment: string;
    attachments: AttachmentMeta[] | null;
    type: 'comment' | 'status_change' | 'assignment_change' | 'system';
    is_internal: boolean;
    reactions?: { emoji: string; count: number; reacted_by_me: boolean }[];
    created_at: string;
    updated_at: string;
    // Comments can come from staff (user) OR a client via the client portal
    // (client) — exactly one of the two is set, never both.
    user: UserSummary | null;
    client?: { id: number; nama: string } | null;
}

export interface Client {
    id: number;
    kode: string;
    nama: string;
    deskripsi?: string | null;
    is_active?: boolean;
}

export interface Ticket {
    id: number;
    client_id: number;
    project_id: number | null;
    timeline_id: number | null;
    reporter_id: number;
    assigned_to: number | null;
    qa_assigned_to?: number | null;
    title: string;
    description: string | null;
    ticket_number: string;
    type: 'bug' | 'feature' | 'task' | 'improvement' | 'documentation';
    task_type_id?: number | null;
    request_type?: 'berbayar' | 'gratis' | null;
    task_type?: { id: number; nama: string } | null;
    approved_by?: number | null;
    approved_at?: string | null;
    rejected_by?: number | null;
    rejected_at?: string | null;
    rejection_reason?: string | null;
    review_notes?: string | null;
    delegated_to?: number | null;
    delegated_at?: string | null;
    approver?: UserSummary | null;
    rejecter?: UserSummary | null;
    delegated_user?: UserSummary | null;
    priority: 'highest' | 'high' | 'medium' | 'low' | 'lowest';
    status:
        | 'backlog'
        | 'todo'
        | 'pending'
        | 'inprogress'
        | 'qa-ready'
        | 'qa-test'
        | 'review'
        | 'not-appropriate'
        | 'done';
    due_date: string | null;
    estimated_hours: number | null;
    actual_hours: number | null;
    tags: string[] | null;
    attachments: AttachmentMeta[] | null;
    story_points: number | null;
    resolved_at: string | null;
    closed_at: string | null;
    archived_at?: string | null;
    created_at: string;
    updated_at: string;
    client: Client;
    project: {
        id: number;
        name: string;
        status: string;
        client_id?: number | null;
    } | null;
    timeline: {
        id: number;
        title: string;
        type: string;
    } | null;
    reporter: UserSummary;
    assigned_user: UserSummary | null;
    qa_assigned_user?: UserSummary | null;
    assignees?: UserSummary[];
    comments?: TicketComment[];
    comments_count?: number;
    active_time_log?: { id: number; start_time: string }[];
}

export interface Project {
    id: number;
    name: string;
    status: string;
    team_id: number;
    client_id?: number | null;
    client?: Client | null;
    team?: {
        users: UserSummary[];
    };
}

export interface Timeline {
    id: number;
    project_id: number;
    title: string;
    type: string;
    status: string;
}

export interface TicketPageProps {
    tickets: Ticket[];
    projects: Project[];
    clients: Client[];
    timelines: Timeline[];
    allUsers?: UserSummary[];
    taskTypes?: { id: number; nama: string }[];
    filters?: {
        client_id?: string;
        project_id?: string;
        search?: string;
        view?: 'active' | 'archive';
        date_from?: string;
        date_to?: string;
    };
}

export type AttachmentItem = File | AttachmentMeta;

export interface TicketDraftData {
    title?: string | null;
    description?: string | null;
    client_id?: number | null;
    project_id?: number | null;
    timeline_id?: number | null;
    type?: Ticket['type'] | null;
    task_type_id?: number | null;
    request_type?: '' | 'berbayar' | 'gratis' | null;
    priority?: Ticket['priority'] | null;
    status?: Ticket['status'] | null;
    assigned_to?: number | null;
    due_date?: string | null;
    estimated_hours?: number | null;
    story_points?: number | null;
    tags?: string[] | null;
    warnings?: string[];
}

export interface TicketFormData {
    client_id: string;
    project_id: string;
    timeline_id: string;
    title: string;
    description: string;
    type: Ticket['type'];
    task_type_id: string;
    request_type: '' | 'berbayar' | 'gratis';
    priority: Ticket['priority'];
    status: Ticket['status'];
    assigned_to: string;
    delegated_to: string;
    assignees: string[];
    review_notes: string;
    due_date: string;
    estimated_hours: string;
    story_points: string;
    tags: string[];
    attachments: AttachmentItem[];
}
