import { AttachmentItem } from '@/types/ticket';
import { type ClipboardEvent, useState } from 'react';
import { validateTicketAttachmentFiles } from '../constants/attachment-limits';

interface UseFileUploadProps {
    currentFiles: AttachmentItem[];
    onFilesChange: (files: AttachmentItem[]) => void;
}

export function useFileUpload({ currentFiles, onFilesChange }: UseFileUploadProps) {
    const [isDragging, setIsDragging] = useState(false);
    const [fileErrors, setFileErrors] = useState<string[]>([]);

    const filterFiles = (files: File[]) => {
        const { accepted, rejected } = validateTicketAttachmentFiles(files);
        setFileErrors(rejected);

        return accepted;
    };

    const addFiles = (files: File[]) => {
        const accepted = filterFiles(files);
        if (accepted.length > 0) {
            onFilesChange([...currentFiles, ...accepted]);
        }
    };

    const handleDragEnter = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
        addFiles(Array.from(e.dataTransfer.files));
    };

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        addFiles(Array.from(e.target.files || []));
        e.target.value = '';
    };

    const removeFile = (index: number) => {
        const newFiles = currentFiles.filter((_, i) => i !== index);
        onFilesChange(newFiles);
    };

    const handlePaste = (e: ClipboardEvent) => {
        const items = Array.from(e.clipboardData?.items ?? []);
        const imageItems = items.filter((item) => item.type.startsWith('image/'));
        if (imageItems.length === 0) return;
        e.preventDefault();
        const files = imageItems
            .map((item) => item.getAsFile())
            .filter((f): f is File => f !== null);
        addFiles(files);
    };

    return {
        isDragging,
        fileErrors,
        handleDragEnter,
        handleDragLeave,
        handleDragOver,
        handleDrop,
        handleFileSelect,
        handlePaste,
        removeFile,
    };
}
