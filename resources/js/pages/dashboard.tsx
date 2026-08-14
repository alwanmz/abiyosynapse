import { UserAvatar } from '@/components/user-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    IconArrowRight,
    IconCalendar,
    IconFlag,
    IconRefresh,
    IconUsers,
} from '@tabler/icons-react';
import {
    Activity,
    AlertCircle,
    Briefcase,
    CheckCircle2,
    Clock,
    FolderKanban,
    Rocket,
    Target,
    TrendingUp,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Beranda',
        href: '/dashboard',
    },
];

// ─── Types ───────────────────────────────────────────────────────────────────
interface DashboardStats {
    stats: {
        activeProjects: number;
        totalTickets: number;
        completedTickets: number;
        teamMembers: number;
        completionRate: number;
    };
    sprintSummary: {
        completed: number;
        inProgress: number;
        pending: number;
    };
    myTasks: {
        id: string;
        title: string;
        project: string | null;
        priority: string;
        status: string;
    }[];
    recentProjects: {
        id: number;
        name: string;
        status: string;
        team: string | null;
        teamColor: string | null;
        progress: number;
        activeTickets: number;
        dueDate: string | null;
    }[];
    upcomingDeadlines: {
        task: string;
        project: string | null;
        dueDate: string;
        priority: string;
    }[];
    recentActivity: {
        user: string;
        userAvatarUrl?: string | null;
        action: string;
        target: string;
        time: string;
    }[];
    projectInfo?: {
        clientActive: number;
        clientInactive: number;
        totalProjects: number;
        mouExpired: number;
        mouExpiringSoon: number;
    };
    taskInfo?: {
        totalTask: number;
        high: number;
        medium: number;
        low: number;
        requestBerbayar: number;
        requestGratis: number;
        requestApproved: number;
        requestRejected: number;
        requestOnProgress: number;
    };
    indikator?: {
        selesaiTepatWaktu: number;
        selesaiTidakTepatWaktu: number;
        belumDirespon: number;
    };
    taskByPersonil?: {
        id: number;
        name: string;
        request: number;
        bug: number;
        done: number;
    }[];
    lastUpdated: string;
}

// ─── Constants ────────────────────────────────────────────────────────────────
const POLL_INTERVAL_MS = 30_000; // 30 seconds

const priorityColors: Record<string, { color: string; bgColor: string }> = {
    highest: { color: 'text-red-600', bgColor: 'bg-red-50' },
    high: { color: 'text-orange-500', bgColor: 'bg-orange-50' },
    medium: { color: 'text-yellow-600', bgColor: 'bg-yellow-50' },
    low: { color: 'text-green-600', bgColor: 'bg-green-50' },
    lowest: { color: 'text-slate-500', bgColor: 'bg-slate-50' },
};

const projectStatusMap: Record<string, { color: string; label: string }> = {
    planning: { color: 'bg-blue-500', label: 'Planning' },
    in_progress: { color: 'bg-indigo-500', label: 'In Progress' },
    on_hold: { color: 'bg-orange-400', label: 'On Hold' },
    completed: { color: 'bg-green-500', label: 'Completed' },
    cancelled: { color: 'bg-red-400', label: 'Cancelled' },
};

const ticketStatusLabel: Record<string, string> = {
    backlog: 'To Do',
    todo: 'To Do',
    pending: 'Pending',
    inprogress: 'In Progress',
    'qa-ready': 'QA Ready',
    'qa-test': 'QA Test',
    review: 'Review',
    done: 'Done',
};

// ─── Component ────────────────────────────────────────────────────────────────
export default function Dashboard({
    dashboardStats: initial,
}: {
    dashboardStats: DashboardStats;
}) {
    const [data, setData] = useState<DashboardStats>(initial);
    const [refreshing, setRefreshing] = useState(false);
    const [lastUpdated, setLastUpdated] = useState<Date>(new Date(initial.lastUpdated));
    const timerRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const fetchStats = useCallback(async (manual = false) => {
        if (manual) setRefreshing(true);
        try {
            const res = await fetch('/api/dashboard-stats', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (res.ok) {
                const json: DashboardStats = await res.json();
                setData(json);
                setLastUpdated(new Date(json.lastUpdated));
            }
        } catch {
            // Silently fail — data stays as-is
        } finally {
            if (manual) setRefreshing(false);
        }
    }, []);

    // Auto-refresh every 30 seconds
    useEffect(() => {
        timerRef.current = setInterval(() => fetchStats(), POLL_INTERVAL_MS);
        return () => {
            if (timerRef.current) clearInterval(timerRef.current);
        };
    }, [fetchStats]);

    // Refresh on tab focus
    useEffect(() => {
        const handleVisibility = () => {
            if (document.visibilityState === 'visible') fetchStats();
        };
        document.addEventListener('visibilitychange', handleVisibility);
        return () => document.removeEventListener('visibilitychange', handleVisibility);
    }, [fetchStats]);

    const { stats, sprintSummary, myTasks, recentProjects, upcomingDeadlines, recentActivity, projectInfo, taskInfo, indikator, taskByPersonil } = data;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dasbor" />

            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Selamat datang kembali!
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Berikut perkembangan proyek Anda hari ini
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {/* Last updated indicator */}
                        <span className="hidden text-xs text-muted-foreground md:inline-flex items-center gap-1">
                            <span
                                className={`inline-block h-2 w-2 rounded-full ${refreshing ? 'animate-pulse bg-yellow-400' : 'bg-green-400'
                                    }`}
                            />
                            {refreshing ? 'Memperbarui…' : `Diperbarui ${lastUpdated.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}`}
                        </span>

                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => fetchStats(true)}
                            disabled={refreshing}
                        >
                            <IconRefresh className={`mr-2 h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                            Segarkan
                        </Button>
                        <Button asChild>
                            <Link href="/projects">
                                <Briefcase className="mr-2 h-4 w-4" />
                                Proyek
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Stats Overview */}
                <div className="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <Card className="border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-950/20">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Proyek Aktif
                            </CardTitle>
                            <FolderKanban className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            {refreshing ? (
                                <Skeleton className="h-8 w-16" />
                            ) : (
                                <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                    {stats.activeProjects}
                                </div>
                            )}
                            <p className="text-xs text-muted-foreground mt-1">
                                Belum selesai atau dibatalkan
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-violet-200 bg-violet-50/50 dark:border-violet-900/50 dark:bg-violet-950/20">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Tiket
                            </CardTitle>
                            <Activity className="h-4 w-4 text-violet-500" />
                        </CardHeader>
                        <CardContent>
                            {refreshing ? (
                                <Skeleton className="h-8 w-20" />
                            ) : (
                                <div className="text-2xl font-bold text-violet-600 dark:text-violet-400">
                                    {stats.totalTickets}
                                </div>
                            )}
                            <p className="text-xs text-muted-foreground mt-1">
                                {stats.completedTickets} selesai
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-green-200 bg-green-50/50 dark:border-green-900/50 dark:bg-green-950/20">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Tingkat Penyelesaian
                            </CardTitle>
                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            {refreshing ? (
                                <div className="space-y-3">
                                    <Skeleton className="h-8 w-16" />
                                    <Skeleton className="h-1 w-full" />
                                </div>
                            ) : (
                                <>
                                    <div className="text-2xl font-bold text-green-600 dark:text-green-400">
                                        {stats.completionRate}%
                                    </div>
                                    <Progress
                                        value={stats.completionRate}
                                        className="mt-2 h-1"
                                    />
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="border-purple-200 bg-purple-50/50 dark:border-purple-900/50 dark:bg-purple-950/20">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Anggota Tim
                            </CardTitle>
                            <IconUsers className="h-4 w-4 text-purple-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                {stats.teamMembers}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Di semua tim
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-amber-200 bg-amber-50/50 dark:border-amber-900/50 dark:bg-amber-950/20">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Sedang Berjalan
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-amber-600 dark:text-amber-400">
                                {sprintSummary.inProgress}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {sprintSummary.pending} tertunda
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* ─── PROJECT INFO (mockup hal. 3) ─── */}
                {projectInfo && (
                    <div className="mb-6">
                        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-muted-foreground">Info Proyek</h2>
                        <div className="grid gap-4 md:grid-cols-3 lg:grid-cols-5">
                            <Link href="/master/clients" className="rounded-lg border border-emerald-300 bg-emerald-50 p-4 transition-colors hover:bg-emerald-100 dark:bg-emerald-950/30">
                                <p className="text-2xl font-bold text-emerald-600">{projectInfo.clientActive}</p>
                                <p className="text-xs text-muted-foreground">Client aktif</p>
                            </Link>
                            <Link href="/projects" className="rounded-lg border border-blue-300 bg-blue-50 p-4 transition-colors hover:bg-blue-100 dark:bg-blue-950/30">
                                <p className="text-2xl font-bold text-blue-600">{projectInfo.totalProjects}</p>
                                <p className="text-xs text-muted-foreground">Total project</p>
                            </Link>
                            <Link href="/projects" className="rounded-lg border border-amber-300 bg-amber-50 p-4 transition-colors hover:bg-amber-100 dark:bg-amber-950/30">
                                <p className="text-2xl font-bold text-amber-600">{projectInfo.mouExpiringSoon}</p>
                                <p className="text-xs text-muted-foreground">MOU mau expired (≤30 hari)</p>
                            </Link>
                            <Link href="/projects" className="rounded-lg border border-red-300 bg-red-50 p-4 transition-colors hover:bg-red-100 dark:bg-red-950/30">
                                <p className="text-2xl font-bold text-red-600">{projectInfo.mouExpired}</p>
                                <p className="text-xs text-muted-foreground">MOU expired</p>
                            </Link>
                            <Link href="/master/clients" className="rounded-lg border border-slate-300 bg-slate-100 p-4 transition-colors hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800/40">
                                <p className="text-2xl font-bold text-slate-600 dark:text-slate-300">{projectInfo.clientInactive}</p>
                                <p className="text-xs text-muted-foreground">Client tidak kerjasama</p>
                            </Link>
                        </div>
                    </div>
                )}

                {/* ─── TASK INFO + INDIKATOR (mockup hal. 3) ─── */}
                {taskInfo && indikator && (
                    <div className="mb-6 grid gap-4 lg:grid-cols-3">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">{taskInfo.totalTask} Total Task</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-1 text-sm">
                                <div className="flex justify-between"><span>Prioritas tinggi</span><span className="font-semibold text-red-600">{taskInfo.high}</span></div>
                                <div className="flex justify-between"><span>Prioritas sedang</span><span className="font-semibold text-amber-600">{taskInfo.medium}</span></div>
                                <div className="flex justify-between"><span>Prioritas rendah</span><span className="font-semibold text-green-600">{taskInfo.low}</span></div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">Request</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-1 text-sm">
                                <div className="flex justify-between"><span>Disetujui</span><span className="font-semibold text-green-600">{taskInfo.requestApproved}</span></div>
                                <div className="flex justify-between"><span>Ditolak</span><span className="font-semibold text-red-600">{taskInfo.requestRejected}</span></div>
                                <div className="flex justify-between"><span>On progress</span><span className="font-semibold text-amber-600">{taskInfo.requestOnProgress}</span></div>
                                <Separator className="my-2" />
                                <div className="flex justify-between"><span>Request berbayar</span><span className="font-semibold text-indigo-600">{taskInfo.requestBerbayar}</span></div>
                                <div className="flex justify-between"><span>Request gratis</span><span className="font-semibold text-teal-600">{taskInfo.requestGratis}</span></div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-base">Indikator</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-1 text-sm">
                                <div className="flex justify-between"><span>Selesai tepat waktu</span><span className="font-semibold text-green-600">{indikator.selesaiTepatWaktu}</span></div>
                                <div className="flex justify-between"><span>Selesai tidak tepat waktu</span><span className="font-semibold text-orange-600">{indikator.selesaiTidakTepatWaktu}</span></div>
                                <div className="flex justify-between"><span>Belum direspon</span><span className="font-semibold text-red-600">{indikator.belumDirespon}</span></div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* ─── TASK INFO BY PERSONIL (mockup hal. 3) ─── */}
                {taskByPersonil && taskByPersonil.length > 0 && (
                    <Card className="mb-6">
                        <CardHeader>
                            <CardTitle className="text-base">Task Info by Personil</CardTitle>
                            <CardDescription>Request &amp; bug per anggota tim</CardDescription>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="overflow-hidden">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50">
                                        <tr>
                                            <th className="px-4 py-2 text-left font-medium">Nama</th>
                                            <th className="px-4 py-2 text-right font-medium">Request</th>
                                            <th className="px-4 py-2 text-right font-medium">Bug</th>
                                            <th className="px-4 py-2 text-right font-medium">Selesai</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {taskByPersonil.map((p) => (
                                            <tr key={p.id} className="hover:bg-muted/30">
                                                <td className="px-4 py-2">{p.name}</td>
                                                <td className="px-4 py-2 text-right">{p.request}</td>
                                                <td className="px-4 py-2 text-right">{p.bug}</td>
                                                <td className="px-4 py-2 text-right font-semibold text-green-600">{p.done}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Left Column - 2/3 width */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Active Projects */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="text-base">
                                            Proyek Aktif
                                        </CardTitle>
                                        <CardDescription>
                                            Pipeline proyek Anda saat ini
                                        </CardDescription>
                                    </div>
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href="/projects">
                                            Lihat Semua
                                            <IconArrowRight className="ml-2 h-4 w-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {recentProjects.length === 0 ? (
                                    <p className="py-6 text-center text-sm text-muted-foreground">
                                        Belum ada proyek aktif.{' '}
                                        <Link href="/projects" className="text-primary underline">
                                            Buat satu →
                                        </Link>
                                    </p>
                                ) : (
                                    <div className="space-y-4">
                                        {recentProjects.map((project) => {
                                            const statusInfo = projectStatusMap[project.status] ?? {
                                                color: 'bg-slate-400',
                                                label: project.status,
                                            };
                                            return (
                                                <div
                                                    key={project.id}
                                                    className="rounded-lg border p-4 transition-colors hover:bg-muted/50"
                                                >
                                                    <div className="mb-3 flex items-start justify-between">
                                                        <div className="flex-1">
                                                            <div className="flex items-center gap-2">
                                                                {project.teamColor && (
                                                                    <span
                                                                        className="inline-block h-2.5 w-2.5 rounded-full flex-shrink-0"
                                                                        style={{ backgroundColor: project.teamColor }}
                                                                    />
                                                                )}
                                                                <h3 className="font-semibold">{project.name}</h3>
                                                            </div>
                                                            <p className="mt-0.5 text-sm text-muted-foreground">
                                                                {project.team ?? '—'} •{' '}
                                                                {project.activeTickets} active tickets
                                                            </p>
                                                        </div>
                                                        <Badge className={`${statusInfo.color} text-white`}>
                                                            {statusInfo.label}
                                                        </Badge>
                                                    </div>

                                                    <div className="space-y-2">
                                                        <div className="flex items-center justify-between text-sm">
                                                            <span className="text-muted-foreground">Progres</span>
                                                            <span className="font-semibold">{project.progress}%</span>
                                                        </div>
                                                        <Progress value={project.progress} className="h-2" />

                                                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                                                            <div className="flex items-center gap-1">
                                                                <IconCalendar className="h-3 w-3" />
                                                                {project.dueDate
                                                                    ? `Tenggat: ${new Date(project.dueDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`
                                                                    : 'Tanpa tenggat'}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* My Tasks */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="text-base">Tugas Saya</CardTitle>
                                        <CardDescription>
                                            Tiket yang ditugaskan kepada Anda
                                        </CardDescription>
                                    </div>
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href="/tickets">
                                            Lihat Semua
                                            <IconArrowRight className="ml-2 h-4 w-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {myTasks.length === 0 ? (
                                    <p className="py-6 text-center text-sm text-muted-foreground">
                                        Tidak ada tugas terbuka untuk Anda. Kerja bagus!
                                    </p>
                                ) : (
                                    <div className="space-y-3">
                                        {myTasks.map((task) => (
                                            <div
                                                key={task.id}
                                                className="flex items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50"
                                            >
                                                <div className="flex h-8 w-8 items-center justify-center rounded bg-primary/10 flex-shrink-0">
                                                    <Target className="h-4 w-4 text-primary" />
                                                </div>
                                                <div className="flex-1 space-y-1 min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <Badge variant="outline" className="font-mono text-xs flex-shrink-0">
                                                            {task.id}
                                                        </Badge>
                                                        <span className="text-sm font-medium truncate">
                                                            {task.title}
                                                        </span>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground truncate">
                                                        {task.project}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2 flex-shrink-0">
                                                    <IconFlag
                                                        className={`h-4 w-4 ${priorityColors[task.priority]?.color ?? 'text-muted-foreground'}`}
                                                    />
                                                    <Badge
                                                        variant={task.status === 'inprogress' ? 'default' : 'secondary'}
                                                        className="text-xs"
                                                    >
                                                        {ticketStatusLabel[task.status] ?? task.status}
                                                    </Badge>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Recent Activity */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Aktivitas Terbaru</CardTitle>
                                <CardDescription>
                                    Update terbaru dari tim Anda
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {recentActivity.length === 0 ? (
                                    <p className="py-6 text-center text-sm text-muted-foreground">
                                        Belum ada aktivitas terbaru.
                                    </p>
                                ) : (
                                    <div className="space-y-4">
                                        {recentActivity.map((activity, index) => (
                                            <div key={index} className="flex items-start gap-3">
                                                <UserAvatar
                                                    user={{
                                                        name: activity.user,
                                                        avatar_url: activity.userAvatarUrl ?? null,
                                                    }}
                                                    className="h-8 w-8 flex-shrink-0"
                                                    fallbackClassName="text-xs"
                                                />
                                                <div className="flex-1 space-y-1">
                                                    <p className="text-sm">
                                                        <span className="font-medium">{activity.user}</span>{' '}
                                                        <span className="text-muted-foreground">{activity.action}</span>{' '}
                                                        <span className="font-medium text-primary">{activity.target}</span>
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">{activity.time}</p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column - 1/3 width */}
                    <div className="space-y-6">
                        {/* Quick Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Aksi Cepat</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-2">
                                <Button className="w-full justify-start" asChild>
                                    <Link href="/tickets">
                                        <Target className="mr-2 h-4 w-4" />
                                        Lihat Tiket
                                    </Link>
                                </Button>
                                <Button variant="outline" className="w-full justify-start" asChild>
                                    <Link href="/projects">
                                        <Briefcase className="mr-2 h-4 w-4" />
                                        Projects
                                    </Link>
                                </Button>
                                <Button variant="outline" className="w-full justify-start" asChild>
                                    <Link href="/teams">
                                        <IconUsers className="mr-2 h-4 w-4" />
                                        Lihat Tim
                                    </Link>
                                </Button>
                                <Button variant="outline" className="w-full justify-start" asChild>
                                    <Link href="/timelines">
                                        <Rocket className="mr-2 h-4 w-4" />
                                        Timeline
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>

                        {/* Upcoming Deadlines */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Tenggat Mendatang</CardTitle>
                                <CardDescription>
                                    Tiket yang jatuh tempo dalam 7 hari ke depan
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {upcomingDeadlines.length === 0 ? (
                                    <p className="py-4 text-center text-sm text-muted-foreground">
                                        Tidak ada tenggat mendatang. 🎯
                                    </p>
                                ) : (
                                    <div className="space-y-3">
                                        {upcomingDeadlines.map((deadline, index) => (
                                            <div key={index} className="rounded-lg border p-3">
                                                <div className="mb-2 flex items-start justify-between">
                                                    <div className="flex-1 min-w-0 mr-2">
                                                        <p className="text-sm font-medium truncate">{deadline.task}</p>
                                                        <p className="text-xs text-muted-foreground truncate">
                                                            {deadline.project}
                                                        </p>
                                                    </div>
                                                    <IconFlag
                                                        className={`h-4 w-4 flex-shrink-0 ${priorityColors[deadline.priority]?.color ?? 'text-muted-foreground'}`}
                                                    />
                                                </div>
                                                <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                                    <Clock className="h-3 w-3" />
                                                    {new Date(deadline.dueDate).toLocaleDateString('en-US', {
                                                        month: 'short',
                                                        day: 'numeric',
                                                        year: 'numeric',
                                                    })}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Sprint / Ticket Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Ringkasan Tiket</CardTitle>
                                <CardDescription>Kesehatan tiket keseluruhan</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">Penyelesaian</span>
                                        <span className="font-semibold">{stats.completionRate}%</span>
                                    </div>
                                    <Progress value={stats.completionRate} className="h-2" />
                                </div>

                                <Separator />

                                <div className="space-y-2 text-sm">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                                            <span>Selesai</span>
                                        </div>
                                        <span className="font-semibold">{sprintSummary.completed}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Clock className="h-4 w-4 text-blue-500" />
                                            <span>Sedang Berjalan</span>
                                        </div>
                                        <span className="font-semibold">{sprintSummary.inProgress}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <AlertCircle className="h-4 w-4 text-orange-500" />
                                            <span>Tertunda / To Do</span>
                                        </div>
                                        <span className="font-semibold">{sprintSummary.pending}</span>
                                    </div>
                                </div>

                                <Separator />

                                <div className="flex items-center justify-between text-xs">
                                    <span className="text-muted-foreground">Total tiket</span>
                                    <span className="font-semibold">{stats.totalTickets}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
