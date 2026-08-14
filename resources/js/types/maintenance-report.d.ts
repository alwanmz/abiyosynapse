export type MaintenanceReportStatus = 'draft' | 'published';

export interface MaintenanceReportClient {
    id: number;
    kode: string | null;
    nama: string;
    alamat?: string | null;
    director_name?: string | null;
    director_title?: string | null;
}

export interface MaintenanceReportProject {
    id: number;
    name: string;
    client_id?: number | null;
}

export interface MaintenanceReportItem {
    id?: number;
    ticket_id: number | null;
    category: string | null;
    found_at: string | null;
    description: string;
    resolution: string | null;
    status_result: string | null;
    resolved_at: string | null;
    notes: string | null;
}

/** Baris pada daftar laporan (staf). */
export interface MaintenanceReportListItem {
    id: number;
    report_number: string;
    title: string;
    status: MaintenanceReportStatus;
    period_start: string;
    period_end: string;
    published_at: string | null;
    items_count: number;
    client: MaintenanceReportClient | null;
    project: MaintenanceReportProject | null;
    creator: { id: number; name: string } | null;
}

/** Payload lengkap untuk form edit. */
export interface MaintenanceReport {
    id: number;
    report_number: string;
    letter_number: string | null;
    letter_date: string | null;
    recipient_name: string | null;
    recipient_title: string | null;
    recipient_address: string | null;
    title: string;
    client_id: number;
    project_id: number | null;
    period_start: string;
    period_end: string;
    summary: string | null;
    signed_by_name: string | null;
    signed_by_role: string | null;
    signature_url: string | null;
    status: MaintenanceReportStatus;
    published_at: string | null;
    client: MaintenanceReportClient | null;
    project: MaintenanceReportProject | null;
    creator: { id: number; name: string } | null;
    items: MaintenanceReportItem[];
}

export interface MaintenanceReportFormData {
    client_id: string;
    project_id: string;
    title: string;
    period_start: string;
    period_end: string;
    letter_date: string;
    recipient_name: string;
    recipient_title: string;
    recipient_address: string;
    summary: string;
    signed_by_name: string;
    signed_by_role: string;
    signature: File | null;
    items: MaintenanceReportItem[];
}

/** Baris laporan terbit yang tampil di portal klien. */
export interface ClientMaintenanceReport {
    id: number;
    report_number: string;
    title: string;
    project: MaintenanceReportProject | null;
    period_start: string;
    period_end: string;
    summary: string | null;
    items_count: number;
    published_at: string | null;
}
