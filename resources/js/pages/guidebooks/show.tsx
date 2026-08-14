import { PdfViewer } from '@/components/pdf-viewer';
import { RichTextViewer } from '@/components/rich-text-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { toEmbedUrl, toSafeEmbedUrl } from '@/lib/video-embed';
import { type BreadcrumbItem } from '@/types';
import type { Guidebook, GuidebookCategory } from '@/types/guidebook';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Eye,
    ExternalLink,
    Maximize2,
    Paperclip,
    Pencil,
} from 'lucide-react';
import { useState } from 'react';
import { ChecklistViewer } from './components/checklist-viewer';
import { GuidebookFormDialog } from './components/guidebook-form-dialog';
import { contentTypeMeta } from './constants/content-types';

interface Props {
    guidebook: Guidebook;
    categories: GuidebookCategory[];
    can: { manage: boolean };
}

export default function GuidebookShow({ guidebook, categories, can }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const meta = contentTypeMeta(guidebook.content_type);
    const Icon = meta.icon;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Guidebook', href: '/guidebooks' },
        { title: guidebook.title, href: `/guidebooks/${guidebook.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={guidebook.title} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex flex-col gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="w-fit px-2 text-muted-foreground"
                            asChild
                        >
                            <Link href="/guidebooks">
                                <ArrowLeft className="mr-1.5 h-4 w-4" />
                                Kembali
                            </Link>
                        </Button>
                        <h1 className="text-2xl font-bold tracking-tight">{guidebook.title}</h1>
                        {guidebook.description && (
                            <p className="text-muted-foreground">{guidebook.description}</p>
                        )}
                        <div className="flex flex-wrap items-center gap-1.5">
                            <Badge variant="secondary" className={cn('gap-1 text-xs', meta.badgeClass)}>
                                <Icon className="h-3 w-3" />
                                {meta.label}
                            </Badge>
                            {guidebook.category && (
                                <Badge variant="outline" className="text-xs">
                                    {guidebook.category.name}
                                </Badge>
                            )}
                            <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                <Eye className="h-3.5 w-3.5" />
                                {guidebook.view_count} tampilan
                            </span>
                            {guidebook.creator && (
                                <span className="text-xs text-muted-foreground">
                                    oleh {guidebook.creator.name}
                                </span>
                            )}
                        </div>
                    </div>

                    {can.manage && (
                        <Button variant="outline" onClick={() => setEditOpen(true)}>
                            <Pencil className="mr-2 h-4 w-4" />
                            Ubah
                        </Button>
                    )}
                </div>

                <Card>
                    <CardContent className="p-4">
                        <GuidebookContent guidebook={guidebook} />
                    </CardContent>
                </Card>

                {guidebook.attachments.length > 0 && (
                    <Card>
                        <CardContent className="flex flex-col gap-2 p-4">
                            <h2 className="flex items-center gap-1.5 text-sm font-semibold">
                                <Paperclip className="h-4 w-4" />
                                Lampiran ({guidebook.attachments.length})
                            </h2>
                            <ul className="flex flex-col gap-1">
                                {guidebook.attachments.map((attachment) => (
                                    <li key={attachment.path}>
                                        <a
                                            href={attachment.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-primary hover:bg-muted"
                                        >
                                            <ExternalLink className="h-3.5 w-3.5 shrink-0" />
                                            <span className="truncate">{attachment.name}</span>
                                            <span className="shrink-0 text-xs text-muted-foreground">
                                                {(attachment.size / 1024).toFixed(0)} KB
                                            </span>
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>

            <GuidebookFormDialog
                open={editOpen}
                onOpenChange={setEditOpen}
                categories={categories}
                guidebook={guidebook}
            />
        </AppLayout>
    );
}

function GuidebookContent({ guidebook }: { guidebook: Guidebook }) {
    switch (guidebook.content_type) {
        case 'embed':
            return <EmbedFrame url={toSafeEmbedUrl(guidebook.embed_url)} rawUrl={guidebook.embed_url} />;

        case 'video':
            return (
                <EmbedFrame
                    url={toEmbedUrl(guidebook.embed_url)}
                    rawUrl={guidebook.embed_url}
                    aspect
                />
            );

        case 'pdf':
            return guidebook.pdf_url ? (
                <PdfViewer url={guidebook.pdf_url} downloadName={`${guidebook.slug}.pdf`} />
            ) : (
                <EmptyState message="Berkas PDF belum diunggah." />
            );

        case 'native':
            return guidebook.content ? (
                <RichTextViewer html={guidebook.content} />
            ) : (
                <EmptyState message="Artikel masih kosong." />
            );

        case 'checklist':
            return guidebook.checklist_items.length > 0 ? (
                <ChecklistViewer guidebookId={guidebook.id} items={guidebook.checklist_items} />
            ) : (
                <EmptyState message="Checklist belum memiliki langkah." />
            );

        default:
            return <EmptyState message="Tipe konten tidak dikenali." />;
    }
}

function EmbedFrame({
    url,
    rawUrl,
    aspect = false,
}: {
    url: string | null;
    rawUrl: string | null;
    aspect?: boolean;
}) {
    const [fullscreen, setFullscreen] = useState(false);

    if (!url) {
        return (
            <EmptyState
                message="Tautan tidak dapat disematkan."
                action={
                    rawUrl ? (
                        <Button variant="outline" size="sm" asChild>
                            <a href={rawUrl} target="_blank" rel="noreferrer">
                                <ExternalLink className="mr-1.5 h-4 w-4" />
                                Buka tautan
                            </a>
                        </Button>
                    ) : undefined
                }
            />
        );
    }

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center justify-end gap-2">
                <Button variant="outline" size="sm" onClick={() => setFullscreen((v) => !v)}>
                    <Maximize2 className="mr-1.5 h-4 w-4" />
                    {fullscreen ? 'Perkecil' : 'Layar penuh'}
                </Button>
                <Button variant="outline" size="sm" asChild>
                    <a href={rawUrl ?? url} target="_blank" rel="noreferrer">
                        <ExternalLink className="mr-1.5 h-4 w-4" />
                        Buka di tab baru
                    </a>
                </Button>
            </div>
            <div
                className={cn(
                    'overflow-hidden rounded-md border bg-muted/30',
                    aspect ? 'aspect-video' : fullscreen ? 'h-[85vh]' : 'h-[70vh]',
                    fullscreen && aspect && 'aspect-auto h-[85vh]',
                )}
            >
                <iframe
                    src={url}
                    className="h-full w-full"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                    allowFullScreen
                    referrerPolicy="strict-origin-when-cross-origin"
                    sandbox="allow-scripts allow-same-origin allow-popups allow-forms allow-presentation"
                    title="Guidebook embed"
                />
            </div>
        </div>
    );
}

function EmptyState({ message, action }: { message: string; action?: React.ReactNode }) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 py-16 text-center text-muted-foreground">
            <p className="text-sm">{message}</p>
            {action}
        </div>
    );
}
