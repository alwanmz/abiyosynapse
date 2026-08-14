import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { IconCalendar, IconCircleCheckFilled, IconEdit, IconExternalLink, IconFile, IconMapPin, IconPaperclip, IconTicket, IconTrash, IconUsers } from '@tabler/icons-react';
import React, { useState } from 'react';
import { type Client, type Project, type Timeline, type UserSummary } from '@/types/ticket';
import { CreateTicketDialog } from '../tickets/components/create-ticket-dialog';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notulensi', href: '/minutes' }];
interface Decision { text: string; owner_name: string | null; due_date: string | null }
interface MinuteAttachmentData { id: number; original_name: string; path: string; mime_type: string; size_bytes: number; url: string }
interface ActivityLog {
    id: number;
    action: string;
    meta: Record<string, any> | null;
    created_at: string;
    user: { id: number; name: string } | null;
}

interface Minute {
    id: number;
    title: string;
    meeting_date: string;
    location: string | null;
    project: { id: number; name: string } | null;
    creator: { id: number; name: string };
    attendees: Array<{ name: string }> | null;
    agenda: string | null;
    raw_transcript: string | null;
    summary: string | null;
    decisions: Decision[] | null;
    attachments: MinuteAttachmentData[] | null;
    activity_logs: ActivityLog[] | null;
    created_by: number;
}

interface Props {
    minute: Minute;
    projects: Project[];
    clients: Client[];
    timelines: Timeline[];
    allUsers?: UserSummary[];
    canEdit: boolean;
}

function linkify(text: string) {
    const urlRegex = /https?:\/\/[^\s]+/g;
    const parts: React.ReactNode[] = [];
    let lastIndex = 0;
    let match: RegExpExecArray | null;

    while ((match = urlRegex.exec(text)) !== null) {
        if (match.index > lastIndex) {
            parts.push(text.slice(lastIndex, match.index));
        }
        const url = match[0];
        parts.push(
            <a
                key={match.index}
                href={url}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-0.5 text-primary underline break-all"
                onClick={(e) => e.stopPropagation()}
            >
                {url}
                <IconExternalLink className="inline h-3 w-3 shrink-0 ml-0.5" />
            </a>
        );
        lastIndex = match.index + url.length;
    }

    if (lastIndex < text.length) {
        parts.push(text.slice(lastIndex));
    }

    return parts;
}

const FIELD_LABELS: Record<string, string> = {
    title: 'Judul', meeting_date: 'Tanggal', location: 'Lokasi',
    agenda: 'Agenda', summary: 'Ringkasan', raw_transcript: 'Transkripsi',
};

function activityLabel(log: ActivityLog): string {
    const who = log.user?.name ?? 'Seseorang';
    switch (log.action) {
        case 'created':
            return `${who} membuat notulensi ini`;
        case 'updated': {
            const fields = (log.meta?.changed as string[] | undefined)
                ?.map((f) => FIELD_LABELS[f] ?? f)
                .join(', ') ?? 'data';
            return `${who} memperbarui ${fields}`;
        }
        case 'attachment_added':
            return `${who} menambahkan lampiran "${log.meta?.filename}"`;
        case 'attachment_deleted':
            return `${who} menghapus lampiran "${log.meta?.filename}"`;
        default:
            return `${who} melakukan aksi "${log.action}"`;
    }
}

function formatBytes(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function MinutesShow({ minute, projects, clients, timelines, allUsers = [], canEdit }: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);

    const handleDelete = () => {
        router.delete(`/minutes/${minute.id}`, {
            onFinish: () => setConfirmDelete(false),
        });
    };

    const breadcrumbsWithTitle: BreadcrumbItem[] = [
        ...breadcrumbs,
        { title: minute.title, href: `/minutes/${minute.id}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbsWithTitle}>
            <Head title={minute.title} />

            <div className="p-6">
                <div className="mx-auto max-w-3xl space-y-6">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{minute.title}</h1>
                            <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                                <span className="flex items-center gap-1.5">
                                    <IconCalendar className="h-4 w-4" />
                                    {new Date(minute.meeting_date).toLocaleDateString('id-ID', {
                                        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
                                    })}
                                </span>
                                {minute.location && (
                                    <span className="flex items-center gap-1.5">
                                        <IconMapPin className="h-4 w-4" />
                                        {minute.location}
                                    </span>
                                )}
                                {minute.project && (
                                    <Badge variant="outline">{minute.project.name}</Badge>
                                )}
                            </div>
                        </div>
                        {canEdit && (
                            <div className="flex shrink-0 gap-2">
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`/minutes/${minute.id}/edit`}>
                                        <IconEdit className="mr-1.5 h-4 w-4" />
                                        Edit
                                    </Link>
                                </Button>
                                <Button variant="outline" size="sm" onClick={() => setConfirmDelete(true)}>
                                    <IconTrash className="mr-1.5 h-4 w-4 text-destructive" />
                                    Hapus
                                </Button>
                            </div>
                        )}
                    </div>

                    {(minute.attendees?.length ?? 0) > 0 && (
                        <Card>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-2 text-sm font-medium">
                                    <IconUsers className="h-4 w-4 text-muted-foreground" />
                                    Peserta ({minute.attendees!.length})
                                </div>
                                <div className="mt-2 flex flex-wrap gap-1.5">
                                    {minute.attendees!.map((a, i) => (
                                        <Badge key={i} variant="secondary">{a.name}</Badge>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {minute.agenda && (
                        <Card>
                            <CardContent className="p-4">
                                <p className="mb-2 text-sm font-medium">Agenda</p>
                                <p className="whitespace-pre-wrap text-sm text-muted-foreground">{linkify(minute.agenda)}</p>
                            </CardContent>
                        </Card>
                    )}

                    {minute.summary && (
                        <Card>
                            <CardContent className="p-4">
                                <p className="mb-2 text-sm font-medium">Ringkasan Rapat</p>
                                <p className="whitespace-pre-wrap text-sm">{linkify(minute.summary)}</p>
                            </CardContent>
                        </Card>
                    )}

                    {(minute.decisions?.length ?? 0) > 0 && (
                        <Card>
                            <CardContent className="p-4">
                                <p className="mb-3 font-medium">Keputusan &amp; Action Items ({minute.decisions!.length})</p>
                                <div className="space-y-3">
                                    {minute.decisions!.map((d, idx) => (
                                        <div key={idx} className="rounded-lg border p-3">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="flex gap-2">
                                                    <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                                        {idx + 1}
                                                    </span>
                                                    <p className="text-sm">{linkify(d.text)}</p>
                                                </div>
                                                <CreateTicketDialog
                                                    projects={projects}
                                                    clients={clients}
                                                    timelines={timelines}
                                                    allUsers={allUsers}
                                                    prefill={{
                                                        title: d.text.length > 90 ? d.text.slice(0, 87) + '...' : d.text,
                                                        description: `Dari notulensi: ${minute.title}\n\n${d.text}`,
                                                        due_date: d.due_date ?? undefined,
                                                    }}
                                                    trigger={
                                                        <Button variant="ghost" size="sm" className="shrink-0">
                                                            <IconTicket className="mr-1.5 h-3.5 w-3.5" />
                                                            Buat Tiket
                                                        </Button>
                                                    }
                                                />
                                            </div>
                                            {(d.owner_name || d.due_date) && (
                                                <>
                                                    <Separator className="my-2" />
                                                    <div className="ml-7 flex flex-wrap gap-3 text-xs text-muted-foreground">
                                                        {d.owner_name && <span>PIC: <strong>{d.owner_name}</strong></span>}
                                                        {d.due_date && (
                                                            <span>Tenggat: <strong>
                                                                {new Date(d.due_date).toLocaleDateString('id-ID', {
                                                                    day: 'numeric', month: 'short', year: 'numeric',
                                                                })}
                                                            </strong></span>
                                                        )}
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {(minute.attachments?.length ?? 0) > 0 && (
                        <Card>
                            <CardContent className="p-4">
                                <div className="mb-3 flex items-center gap-2 font-medium">
                                    <IconPaperclip className="h-4 w-4 text-muted-foreground" />
                                    Lampiran ({minute.attachments!.length})
                                </div>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {minute.attachments!.map((att) => {
                                        const isImage = att.mime_type.startsWith('image/');
                                        return (
                                            <a
                                                key={att.id}
                                                href={att.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="group flex items-center gap-3 overflow-hidden rounded-lg border p-3 transition-colors hover:border-primary hover:bg-muted/50"
                                            >
                                                {isImage ? (
                                                    <img
                                                        src={att.url}
                                                        alt={att.original_name}
                                                        className="h-10 w-10 shrink-0 rounded object-cover"
                                                        loading="lazy"
                                                    />
                                                ) : (
                                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded bg-muted">
                                                        <IconFile className="h-5 w-5 text-muted-foreground" />
                                                    </div>
                                                )}
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium group-hover:text-primary">{att.original_name}</p>
                                                    <p className="text-xs text-muted-foreground">{formatBytes(att.size_bytes)}</p>
                                                </div>
                                                <IconExternalLink className="h-4 w-4 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                                            </a>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {minute.raw_transcript && (
                        <Card>
                            <CardContent className="p-4">
                                <p className="mb-2 text-sm font-medium text-muted-foreground">Transkripsi Mentah</p>
                                <p className="whitespace-pre-wrap text-xs text-muted-foreground">{linkify(minute.raw_transcript)}</p>
                            </CardContent>
                        </Card>
                    )}

                    {(minute.activity_logs?.length ?? 0) > 0 && (
                        <Card>
                            <CardContent className="p-4">
                                <p className="mb-4 flex items-center gap-2 font-medium">
                                    <IconCircleCheckFilled className="h-4 w-4 text-muted-foreground" />
                                    Log Aktivitas
                                </p>
                                <ol className="relative border-l border-border ml-2">
                                    {minute.activity_logs!.map((log) => (
                                        <li key={log.id} className="mb-4 ml-4 last:mb-0">
                                            <span className="absolute -left-1.5 flex h-3 w-3 items-center justify-center rounded-full bg-muted ring-2 ring-background" />
                                            <p className="text-sm">{activityLabel(log)}</p>
                                            <time className="text-xs text-muted-foreground">
                                                {new Date(log.created_at).toLocaleString('id-ID', {
                                                    day: 'numeric', month: 'short', year: 'numeric',
                                                    hour: '2-digit', minute: '2-digit',
                                                })}
                                            </time>
                                        </li>
                                    ))}
                                </ol>
                            </CardContent>
                        </Card>
                    )}

                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>Dibuat oleh {minute.creator.name}</span>
                        <Link href="/minutes" className="hover:underline">← Kembali ke daftar</Link>
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                title="Hapus notulensi?"
                description="Notulensi ini beserta seluruh isinya akan dihapus permanen."
                confirmLabel="Ya, Hapus"
                onConfirm={handleDelete}
            />
        </AppLayout>
    );
}
