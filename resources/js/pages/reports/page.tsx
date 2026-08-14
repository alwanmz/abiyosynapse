import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { IconFileSpreadsheet } from '@tabler/icons-react';
import { type FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Laporan', href: '/reports' }];

interface Project { id: number; name: string; kode_project: string; status: string; start_date: string | null; end_date: string | null; team: { name: string } | null; client: { nama: string } | null; project_manager: { name: string } | null; tickets_count: number; tickets_done_count: number }
interface Ticket { id: number; ticket_number: string; title: string; type: string | null; priority: string; status: string; estimated_hours: number | null; actual_hours: number | null; due_date: string | null; created_at: string; project: { name: string } | null; assignedUser: { name: string } | null; reporter: { name: string } | null; taskType: { nama: string } | null }
interface DailyLog { id: number; log_number: string; log_date: string; duration_minutes: number | null; category: string | null; mood: string | null; energy_level: number | null; description: string; user: { name: string } | null; ticket: { ticket_number: string; title: string } | null }
interface Minute { id: number; title: string; meeting_date: string; location: string | null; attendees: Array<{ name: string }> | null; decisions: unknown[] | null; summary: string | null; project: { name: string } | null; creator: { name: string } | null }
interface Paginated<T> { data: T[]; current_page: number; last_page: number; total: number; per_page: number }
interface ProjectItem { id: number; name: string }
interface UserItem { id: number; name: string }

interface Props {
    summary: { projects_done: number; tickets_done: number; daily_logs: number; decisions_total: number };
    projects: Paginated<Project>;
    tickets: Paginated<Ticket>;
    dailyLogs: Paginated<DailyLog>;
    minutes: Paginated<Minute>;
    filters: Record<string, string>;
    users: UserItem[];
    projectList: ProjectItem[];
}

const STATUS_LABEL: Record<string, string> = {
    planning: 'Perencanaan', in_progress: 'Berjalan', completed: 'Selesai', on_hold: 'Ditahan', cancelled: 'Dibatalkan',
    backlog: 'To Do', todo: 'To Do', pending: 'Pending', inprogress: 'In Progress',
    'qa-ready': 'QA Ready', 'qa-test': 'QA Test', review: 'Review', done: 'Selesai',
};

const PRIORITY_COLOR: Record<string, string> = {
    highest: 'destructive', high: 'destructive', medium: 'secondary', low: 'outline', lowest: 'outline',
};

function fmtDate(d: string | null) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function buildExportUrl(base: string, filters: Record<string, string>, extra: Record<string, string> = {}) {
    const p = new URLSearchParams();
    const merged = { ...filters, ...extra };
    Object.entries(merged).forEach(([k, v]) => { if (v) p.set(k, v); });
    return `${base}?${p.toString()}`;
}

export default function ReportsPage({ summary, projects, tickets, dailyLogs, minutes, filters, users, projectList }: Props) {
    const [form, setForm] = useState({
        date_from: filters.date_from ?? '',
        date_to:   filters.date_to   ?? '',
        project_id: filters.project_id ?? '',
        user_id:    filters.user_id    ?? '',
        status:     filters.status     ?? '',
        priority:   filters.priority   ?? '',
    });

    const activeTab = filters.tab ?? 'projects';

    const applyFilter = (e: FormEvent) => {
        e.preventDefault();
        router.get('/reports', { ...form, tab: activeTab }, { preserveScroll: true });
    };

    const resetFilter = () => {
        router.get('/reports');
    };

    const setTab = (tab: string) => {
        router.get('/reports', { ...form, tab }, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Laporan" />

            <div className="p-6 space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Laporan</h1>
                    <p className="text-sm text-muted-foreground mt-1">Rekap data proyek, tiket, catatan harian, dan notulensi.</p>
                </div>

                {/* Filter */}
                <Card>
                    <CardContent className="p-4">
                        <form onSubmit={applyFilter} className="flex flex-wrap gap-3 items-end">
                            <div className="space-y-1">
                                <Label className="text-xs">Dari Tanggal</Label>
                                <Input type="date" className="h-9 text-sm w-36 shrink-0" value={form.date_from} onChange={e => setForm(p => ({ ...p, date_from: e.target.value }))} />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">Sampai Tanggal</Label>
                                <Input type="date" className="h-9 text-sm w-36 shrink-0" value={form.date_to} onChange={e => setForm(p => ({ ...p, date_to: e.target.value }))} />
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">Proyek</Label>
                                <Select value={form.project_id || 'all'} onValueChange={v => setForm(p => ({ ...p, project_id: v === 'all' ? '' : v }))}>
                                    <SelectTrigger className="h-9 text-sm w-44 shrink-0"><SelectValue placeholder="Semua proyek" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua proyek</SelectItem>
                                        {projectList.map(p => <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">User</Label>
                                <Select value={form.user_id || 'all'} onValueChange={v => setForm(p => ({ ...p, user_id: v === 'all' ? '' : v }))}>
                                    <SelectTrigger className="h-9 text-sm w-44 shrink-0"><SelectValue placeholder="Semua user" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua user</SelectItem>
                                        {users.map(u => <SelectItem key={u.id} value={String(u.id)}>{u.name}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs">Status</Label>
                                <Select value={form.status || 'all'} onValueChange={v => setForm(p => ({ ...p, status: v === 'all' ? '' : v }))}>
                                    <SelectTrigger className="h-9 text-sm w-36 shrink-0"><SelectValue placeholder="Semua status" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua</SelectItem>
                                        <SelectItem value="completed">Selesai</SelectItem>
                                        <SelectItem value="in_progress">Berjalan</SelectItem>
                                        <SelectItem value="planning">Perencanaan</SelectItem>
                                        <SelectItem value="on_hold">Ditahan</SelectItem>
                                        <SelectItem value="done">Tiket Done</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex shrink-0 gap-2">
                                <Button type="submit" size="sm" className="h-9">Terapkan</Button>
                                <Button type="button" variant="outline" size="sm" className="h-9" onClick={resetFilter}>Reset</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Summary cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        { label: 'Proyek Selesai', value: summary.projects_done, card: 'border-green-200 bg-green-50/50 dark:border-green-900/50 dark:bg-green-950/20', text: 'text-green-600 dark:text-green-400' },
                        { label: 'Tiket Selesai', value: summary.tickets_done, card: 'border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-950/20', text: 'text-blue-600 dark:text-blue-400' },
                        { label: 'Catatan Harian', value: summary.daily_logs, card: 'border-violet-200 bg-violet-50/50 dark:border-violet-900/50 dark:bg-violet-950/20', text: 'text-violet-600 dark:text-violet-400' },
                        { label: 'Total Keputusan Rapat', value: summary.decisions_total, card: 'border-amber-200 bg-amber-50/50 dark:border-amber-900/50 dark:bg-amber-950/20', text: 'text-amber-600 dark:text-amber-400' },
                    ].map(c => (
                        <Card key={c.label} className={c.card}>
                            <CardContent className="p-4">
                                <p className="text-xs text-muted-foreground">{c.label}</p>
                                <p className={`text-3xl font-bold mt-1 ${c.text}`}>{c.value.toLocaleString('id-ID')}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Tabs */}
                <Tabs value={activeTab} onValueChange={setTab}>
                    <TabsList>
                        <TabsTrigger value="projects">Proyek ({projects.total})</TabsTrigger>
                        <TabsTrigger value="tickets">Tiket ({tickets.total})</TabsTrigger>
                        <TabsTrigger value="daily-logs">Catatan Harian ({dailyLogs.total})</TabsTrigger>
                        <TabsTrigger value="minutes">Notulensi ({minutes.total})</TabsTrigger>
                    </TabsList>

                    {/* ── TAB PROYEK ─────────────────────────────────────────── */}
                    <TabsContent value="projects" className="mt-4 space-y-3">
                        <div className="flex justify-end">
                            <Button variant="outline" size="sm" onClick={() => { window.location.href = buildExportUrl('/reports/export/projects', filters); }}>
                                <IconFileSpreadsheet className="mr-1.5 h-4 w-4" />Export Excel
                            </Button>
                        </div>
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-8">No</TableHead>
                                        <TableHead>Kode</TableHead>
                                        <TableHead>Nama Proyek</TableHead>
                                        <TableHead>Klien</TableHead>
                                        <TableHead>Tim</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Tiket</TableHead>
                                        <TableHead className="text-right">Selesai</TableHead>
                                        <TableHead>Mulai</TableHead>
                                        <TableHead>Selesai Tgl</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {projects.data.length === 0 ? (
                                        <TableRow><TableCell colSpan={10} className="text-center text-muted-foreground py-8">Tidak ada data</TableCell></TableRow>
                                    ) : projects.data.map((p, i) => (
                                        <TableRow key={p.id}>
                                            <TableCell className="text-muted-foreground">{(projects.current_page - 1) * projects.per_page + i + 1}</TableCell>
                                            <TableCell className="font-mono text-xs">{p.kode_project}</TableCell>
                                            <TableCell className="font-medium max-w-48 truncate">{p.name}</TableCell>
                                            <TableCell className="text-sm">{p.client?.nama ?? '-'}</TableCell>
                                            <TableCell className="text-sm">{p.team?.name ?? '-'}</TableCell>
                                            <TableCell><Badge variant="outline">{STATUS_LABEL[p.status] ?? p.status}</Badge></TableCell>
                                            <TableCell className="text-right">{p.tickets_count}</TableCell>
                                            <TableCell className="text-right">{p.tickets_done_count}</TableCell>
                                            <TableCell className="text-sm">{fmtDate(p.start_date)}</TableCell>
                                            <TableCell className="text-sm">{fmtDate(p.end_date)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination data={projects} paramName="projects_page" filters={form} />
                    </TabsContent>

                    {/* ── TAB TIKET ──────────────────────────────────────────── */}
                    <TabsContent value="tickets" className="mt-4 space-y-3">
                        <div className="flex justify-end">
                            <Button variant="outline" size="sm" onClick={() => { window.location.href = buildExportUrl('/reports/export/tickets', filters); }}>
                                <IconFileSpreadsheet className="mr-1.5 h-4 w-4" />Export Excel
                            </Button>
                        </div>
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-8">No</TableHead>
                                        <TableHead>Nomor</TableHead>
                                        <TableHead>Judul</TableHead>
                                        <TableHead>Proyek</TableHead>
                                        <TableHead>Prioritas</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Ditugaskan</TableHead>
                                        <TableHead>Tenggat</TableHead>
                                        <TableHead>Dibuat</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {tickets.data.length === 0 ? (
                                        <TableRow><TableCell colSpan={9} className="text-center text-muted-foreground py-8">Tidak ada data</TableCell></TableRow>
                                    ) : tickets.data.map((t, i) => (
                                        <TableRow key={t.id}>
                                            <TableCell className="text-muted-foreground">{(tickets.current_page - 1) * tickets.per_page + i + 1}</TableCell>
                                            <TableCell className="font-mono text-xs">{t.ticket_number}</TableCell>
                                            <TableCell className="max-w-56 truncate text-sm">{t.title}</TableCell>
                                            <TableCell className="text-sm">{t.project?.name ?? '-'}</TableCell>
                                            <TableCell>
                                                <Badge variant={PRIORITY_COLOR[t.priority] as 'destructive' | 'secondary' | 'outline' ?? 'outline'}>
                                                    {t.priority}
                                                </Badge>
                                            </TableCell>
                                            <TableCell><Badge variant="outline">{STATUS_LABEL[t.status] ?? t.status}</Badge></TableCell>
                                            <TableCell className="text-sm">{t.assignedUser?.name ?? '-'}</TableCell>
                                            <TableCell className="text-sm">{fmtDate(t.due_date)}</TableCell>
                                            <TableCell className="text-sm">{fmtDate(t.created_at)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination data={tickets} paramName="tickets_page" filters={form} />
                    </TabsContent>

                    {/* ── TAB CATATAN HARIAN ─────────────────────────────────── */}
                    <TabsContent value="daily-logs" className="mt-4 space-y-3">
                        <div className="flex justify-end">
                            <Button variant="outline" size="sm" onClick={() => { window.location.href = buildExportUrl('/reports/export/daily-logs', filters); }}>
                                <IconFileSpreadsheet className="mr-1.5 h-4 w-4" />Export Excel
                            </Button>
                        </div>
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-8">No</TableHead>
                                        <TableHead>Nomor Log</TableHead>
                                        <TableHead>Tanggal</TableHead>
                                        <TableHead>User</TableHead>
                                        <TableHead>Tiket</TableHead>
                                        <TableHead>Kategori</TableHead>
                                        <TableHead className="text-right">Durasi (mnt)</TableHead>
                                        <TableHead>Mood</TableHead>
                                        <TableHead>Deskripsi</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {dailyLogs.data.length === 0 ? (
                                        <TableRow><TableCell colSpan={9} className="text-center text-muted-foreground py-8">Tidak ada data</TableCell></TableRow>
                                    ) : dailyLogs.data.map((l, i) => (
                                        <TableRow key={l.id}>
                                            <TableCell className="text-muted-foreground">{(dailyLogs.current_page - 1) * dailyLogs.per_page + i + 1}</TableCell>
                                            <TableCell className="font-mono text-xs">{l.log_number}</TableCell>
                                            <TableCell className="text-sm">{fmtDate(l.log_date)}</TableCell>
                                            <TableCell className="text-sm">{l.user?.name ?? '-'}</TableCell>
                                            <TableCell className="font-mono text-xs">{l.ticket?.ticket_number ?? '-'}</TableCell>
                                            <TableCell className="text-sm">{l.category ?? '-'}</TableCell>
                                            <TableCell className="text-right">{l.duration_minutes ?? '-'}</TableCell>
                                            <TableCell className="text-sm">{l.mood ?? '-'}</TableCell>
                                            <TableCell className="text-sm max-w-56 truncate">{l.description}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination data={dailyLogs} paramName="logs_page" filters={form} />
                    </TabsContent>

                    {/* ── TAB NOTULENSI ──────────────────────────────────────── */}
                    <TabsContent value="minutes" className="mt-4 space-y-3">
                        <div className="flex justify-end">
                            <Button variant="outline" size="sm" onClick={() => { window.location.href = buildExportUrl('/reports/export/minutes', filters); }}>
                                <IconFileSpreadsheet className="mr-1.5 h-4 w-4" />Export Excel
                            </Button>
                        </div>
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-8">No</TableHead>
                                        <TableHead>Judul Rapat</TableHead>
                                        <TableHead>Tanggal</TableHead>
                                        <TableHead>Proyek</TableHead>
                                        <TableHead>Dibuat Oleh</TableHead>
                                        <TableHead className="text-right">Peserta</TableHead>
                                        <TableHead className="text-right">Keputusan</TableHead>
                                        <TableHead>Ringkasan</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {minutes.data.length === 0 ? (
                                        <TableRow><TableCell colSpan={8} className="text-center text-muted-foreground py-8">Tidak ada data</TableCell></TableRow>
                                    ) : minutes.data.map((m, i) => (
                                        <TableRow key={m.id}>
                                            <TableCell className="text-muted-foreground">{(minutes.current_page - 1) * minutes.per_page + i + 1}</TableCell>
                                            <TableCell className="font-medium max-w-48 truncate">{m.title}</TableCell>
                                            <TableCell className="text-sm">{fmtDate(m.meeting_date)}</TableCell>
                                            <TableCell className="text-sm">{m.project?.name ?? '-'}</TableCell>
                                            <TableCell className="text-sm">{m.creator?.name ?? '-'}</TableCell>
                                            <TableCell className="text-right">{m.attendees?.length ?? 0}</TableCell>
                                            <TableCell className="text-right">{m.decisions?.length ?? 0}</TableCell>
                                            <TableCell className="text-sm max-w-56 truncate text-muted-foreground">{m.summary ?? '-'}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination data={minutes} paramName="minutes_page" filters={form} />
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}

function Pagination({ data, paramName, filters }: { data: Paginated<unknown>; paramName: string; filters: Record<string, string> }) {
    if (data.last_page <= 1) return null;

    const goTo = (page: number) => {
        router.get('/reports', { ...filters, [paramName]: page }, { preserveScroll: true });
    };

    return (
        <div className="flex items-center justify-between text-sm text-muted-foreground">
            <span>
                Menampilkan {Math.min((data.current_page - 1) * data.per_page + 1, data.total)}–{Math.min(data.current_page * data.per_page, data.total)} dari {data.total} data
            </span>
            <div className="flex gap-1">
                <Button variant="outline" size="sm" disabled={data.current_page === 1} onClick={() => goTo(data.current_page - 1)}>
                    ← Sebelumnya
                </Button>
                <Button variant="outline" size="sm" disabled={data.current_page === data.last_page} onClick={() => goTo(data.current_page + 1)}>
                    Berikutnya →
                </Button>
            </div>
        </div>
    );
}
