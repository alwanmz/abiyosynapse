import type { GuidebookContentType } from '@/types/guidebook';
import { BookText, FileText, Globe, ListChecks, Video, type LucideIcon } from 'lucide-react';

interface ContentTypeMeta {
    value: GuidebookContentType;
    label: string;
    description: string;
    icon: LucideIcon;
    /** Tailwind classes untuk badge tipe konten. */
    badgeClass: string;
}

export const CONTENT_TYPES: ContentTypeMeta[] = [
    {
        value: 'embed',
        label: 'Web / Google Sites',
        description: 'Sematkan situs Google Sites atau dokumentasi eksternal.',
        icon: Globe,
        badgeClass: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    },
    {
        value: 'video',
        label: 'Video Panduan',
        description: 'Video walkthrough dari YouTube atau Google Drive.',
        icon: Video,
        badgeClass: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
    },
    {
        value: 'pdf',
        label: 'Dokumen PDF',
        description: 'Unggah berkas SOP resmi dalam format PDF.',
        icon: FileText,
        badgeClass: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    },
    {
        value: 'native',
        label: 'Artikel',
        description: 'Tulis artikel internal langsung di Teamboard.',
        icon: BookText,
        badgeClass: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
    },
    {
        value: 'checklist',
        label: 'Checklist',
        description: 'SOP interaktif berbasis daftar periksa langkah demi langkah.',
        icon: ListChecks,
        badgeClass: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    },
];

export const contentTypeMeta = (type: GuidebookContentType): ContentTypeMeta =>
    CONTENT_TYPES.find((item) => item.value === type) ?? CONTENT_TYPES[3];
