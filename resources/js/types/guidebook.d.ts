export type GuidebookContentType = 'embed' | 'video' | 'pdf' | 'native' | 'checklist';

export interface GuidebookCategory {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
    color: string | null;
    order: number;
    guidebooks_count?: number;
}

export interface GuidebookChecklistItem {
    text: string;
    note?: string | null;
}

export interface GuidebookAttachment {
    name: string;
    path: string;
    disk?: string;
    url: string;
    size: number;
    mime_type: string;
}

export interface GuidebookCreator {
    id: number;
    name: string;
    avatar_path?: string | null;
}

/** Shape returned by GuidebookController::summaryPayload(). */
export interface GuidebookSummary {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    content_type: GuidebookContentType;
    is_pinned: boolean;
    view_count: number;
    category: GuidebookCategory | null;
    creator: GuidebookCreator | null;
    checklist_count: number;
    attachments_count: number;
    updated_at: string;
}

/** Shape returned by GuidebookController::detailPayload(). */
export interface Guidebook extends GuidebookSummary {
    embed_url: string | null;
    content: string | null;
    checklist_items: GuidebookChecklistItem[];
    attachments: GuidebookAttachment[];
    pdf_url: string | null;
    created_at: string;
}

export interface GuidebookFormData {
    category_id: string;
    title: string;
    description: string;
    content_type: GuidebookContentType;
    embed_url: string;
    content: string;
    checklist_items: GuidebookChecklistItem[];
    is_pinned: boolean;
    pdf: File | null;
    attachments: File[];
}
