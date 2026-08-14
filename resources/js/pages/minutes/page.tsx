import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { IconCalendar, IconClipboardList, IconPlus, IconTrash, IconUsers } from '@tabler/icons-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notulensi', href: '/minutes' }];

interface Project { id: number; name: string }
interface MinuteItem {
    id: number;
    title: string;
    meeting_date: string;
    location: string | null;
    project: Project | null;
    creator: { id: number; name: string };
    attendees: Array<{ name: string }> | null;
    decisions: Array<{ text: string }> | null;
    created_by: number;
}

interface PaginatedMinutes {
    data: MinuteItem[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

interface Props {
    minutes: PaginatedMinutes;
    projects: Project[];
    filters: { project_id?: string | null };
}

export default function MinutesPage({ minutes, projects, filters }: Props) {
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const handleProjectFilter = (value: string) => {
        router.get('/minutes', value === 'all' ? {} : { project_id: value }, { preserveState: true });
    };

    const confirmDelete = () => {
        if (deletingId === null) return;
        router.delete(`/minutes/${deletingId}`, {
            preserveScroll: true,
            onFinish: () => setDeletingId(null),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Notulensi Rapat" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Notulensi Rapat</h1>
                        <p className="text-muted-foreground">Rekap rapat, keputusan, dan action items tim.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Select value={filters.project_id ?? 'all'} onValueChange={handleProjectFilter}>
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="Semua proyek" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua proyek</SelectItem>
                                {projects.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button asChild>
                            <Link href="/minutes/create">
                                <IconPlus className="mr-2 h-4 w-4" />
                                Tambah
                            </Link>
                        </Button>
                    </div>
                </div>

                {minutes.data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-muted-foreground">
                            <IconClipboardList className="mb-3 h-12 w-12 opacity-20" />
                            <p className="font-medium">Belum ada notulensi.</p>
                            <p className="mt-1 text-sm">Mulai dengan merekam rapat pertama kamu.</p>
                            <Button asChild className="mt-4">
                                <Link href="/minutes/create">
                                    <IconPlus className="mr-2 h-4 w-4" />
                                    Tambah Notulensi
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {minutes.data.map((minute) => (
                            <Card
                                key={minute.id}
                                className="group cursor-pointer transition-shadow hover:shadow-md"
                                onClick={() => router.visit(`/minutes/${minute.id}`)}
                            >
                                <CardContent className="p-4">
                                    <div className="mb-2 flex items-start justify-between gap-2">
                                        <span className="line-clamp-2 text-sm font-semibold leading-snug">
                                            {minute.title}
                                        </span>
                                        <button
                                            type="button"
                                            className="shrink-0 opacity-0 transition-opacity group-hover:opacity-100"
                                            onClick={(e) => { e.stopPropagation(); setDeletingId(minute.id); }}
                                            title="Hapus notulensi"
                                        >
                                            <IconTrash className="h-4 w-4 text-destructive/60 hover:text-destructive" />
                                        </button>
                                    </div>

                                    <div className="space-y-1 text-xs text-muted-foreground">
                                        <div className="flex items-center gap-1.5">
                                            <IconCalendar className="h-3.5 w-3.5 shrink-0" />
                                            {new Date(minute.meeting_date).toLocaleDateString('id-ID', {
                                                day: 'numeric', month: 'long', year: 'numeric',
                                            })}
                                        </div>
                                        {minute.project && (
                                            <div className="flex items-center gap-1.5">
                                                <span className="h-1.5 w-1.5 rounded-full bg-primary/60" />
                                                {minute.project.name}
                                            </div>
                                        )}
                                        {(minute.attendees?.length ?? 0) > 0 && (
                                            <div className="flex items-center gap-1.5">
                                                <IconUsers className="h-3.5 w-3.5 shrink-0" />
                                                {minute.attendees!.length} peserta
                                            </div>
                                        )}
                                    </div>

                                    {(minute.decisions?.length ?? 0) > 0 && (
                                        <div className="mt-3">
                                            <Badge variant="secondary" className="text-xs">
                                                {minute.decisions!.length} keputusan
                                            </Badge>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {(minutes.prev_page_url || minutes.next_page_url) && (
                    <div className="flex justify-center gap-2">
                        {minutes.prev_page_url && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={minutes.prev_page_url}>← Sebelumnya</Link>
                            </Button>
                        )}
                        {minutes.next_page_url && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={minutes.next_page_url}>Berikutnya →</Link>
                            </Button>
                        )}
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={deletingId !== null}
                onOpenChange={(o) => !o && setDeletingId(null)}
                title="Hapus notulensi?"
                description="Notulensi ini beserta seluruh isinya akan dihapus permanen."
                confirmLabel="Ya, Hapus"
                onConfirm={confirmDelete}
            />
        </AppLayout>
    );
}
