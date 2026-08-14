export const TICKET_ATTACHMENT_MAX_MB = 30;
export const TICKET_ATTACHMENT_MAX_BYTES = TICKET_ATTACHMENT_MAX_MB * 1024 * 1024;
export const TICKET_ATTACHMENT_ALLOWED_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];
export const TICKET_ATTACHMENT_ACCEPT = '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf';
export const TICKET_ATTACHMENT_HELP_TEXT = `JPG, PNG, atau PDF. Maksimal ${TICKET_ATTACHMENT_MAX_MB} MB per file`;

export function formatAttachmentSize(bytes: number) {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    }

    return `${(bytes / 1024).toFixed(1)} KB`;
}

export function validateTicketAttachmentFiles(files: File[]) {
    const accepted: File[] = [];
    const rejected: string[] = [];

    files.forEach((file) => {
        if (!TICKET_ATTACHMENT_ALLOWED_TYPES.includes(file.type)) {
            rejected.push(`${file.name}: hanya JPG, PNG, atau PDF.`);
            return;
        }

        if (file.size > TICKET_ATTACHMENT_MAX_BYTES) {
            rejected.push(`${file.name}: ukuran maksimal ${TICKET_ATTACHMENT_MAX_MB} MB.`);
            return;
        }

        accepted.push(file);
    });

    return { accepted, rejected };
}
