import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import ClientPortalLayout from '@/layouts/client-portal-layout';
import { type PortalQuota } from '@/pages/client-portal/tickets/types';
import { StatCard } from '@/pages/analytics/components/stat-card';
import {
    TICKET_PRIORITIES,
    type TicketPriority,
} from '@/pages/tickets/constants/ticket-priorities';
import {
    TICKET_STATUSES,
    type TicketStatusId,
} from '@/pages/tickets/constants/ticket-statuses';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { CheckCircle2, Circle, ListTodo, TriangleAlert } from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

// Hex values mirror the Tailwind tokens used on the internal Kanban board so the
// dashboard reads as the same system. Keyed by status/priority identity (fixed).
const STATUS_HEX: Record<TicketStatusId, string> = {
    todo: '#64748b',
    pending: '#eab308',
    inprogress: '#3b82f6',
    'qa-ready': '#a855f7',
    'qa-test': '#f97316',
    review: '#6366f1',
    'not-appropriate': '#f43f5e',
    done: '#22c55e',
};

const PRIORITY_HEX: Record<TicketPriority, string> = {
    highest: '#dc2626',
    high: '#ea580c',
    medium: '#ca8a04',
    low: '#16a34a',
    lowest: '#4b5563',
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/portal' }];

interface Props {
    stats: {
        total: number;
        inprogress: number;
        done: number;
        attention: number;
    };
    byStatus: { status: TicketStatusId; count: number }[];
    byPriority: { priority: TicketPriority; count: number }[];
    trend: { date: string; created: number; done: number }[];
    quota: PortalQuota;
}

function statusLabel(id: TicketStatusId) {
    return TICKET_STATUSES.find((s) => s.id === id)?.label ?? id;
}

function shortDate(date: string) {
    return new Date(date).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
    });
}

export default function ClientDashboard({
    stats,
    byStatus,
    byPriority,
    trend,
    quota,
}: Props) {
    const statusData = byStatus
        .filter((s) => s.count > 0)
        .map((s) => ({
            name: statusLabel(s.status),
            value: s.count,
            id: s.status,
        }));

    const priorityData = byPriority.map((p) => ({
        name: TICKET_PRIORITIES[p.priority]?.label ?? p.priority,
        value: p.count,
        id: p.priority,
    }));

    const trendData = trend.map((t) => ({
        date: shortDate(t.date),
        Dibuat: t.created,
        Selesai: t.done,
    }));

    return (
        <ClientPortalLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Dashboard
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Ringkasan progres tiket Anda.
                        </p>
                    </div>
                    <Badge variant="secondary">
                        {quota.unlimited
                            ? 'Request: Unlimited'
                            : `Sisa request: ${quota.remaining}/${quota.limit}`}
                    </Badge>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Total Tiket"
                        value={stats.total}
                        icon={ListTodo}
                        iconColor="text-violet-600"
                        iconBgColor="bg-violet-100 dark:bg-violet-950"
                    />
                    <StatCard
                        title="Sedang Dikerjakan"
                        value={stats.inprogress}
                        icon={Circle}
                        iconColor="text-blue-600"
                        iconBgColor="bg-blue-100 dark:bg-blue-950"
                    />
                    <StatCard
                        title="Selesai"
                        value={stats.done}
                        icon={CheckCircle2}
                        iconColor="text-green-600"
                        iconBgColor="bg-green-100 dark:bg-green-950"
                    />
                    <StatCard
                        title="Perlu Perhatian"
                        value={stats.attention}
                        icon={TriangleAlert}
                        iconColor="text-rose-600"
                        iconBgColor="bg-rose-100 dark:bg-rose-950"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Tiket per Status
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {statusData.length === 0 ? (
                                <EmptyChart />
                            ) : (
                                <ResponsiveContainer width="100%" height={300}>
                                    <PieChart>
                                        <Pie
                                            data={statusData}
                                            cx="50%"
                                            cy="50%"
                                            innerRadius={60}
                                            outerRadius={95}
                                            paddingAngle={2}
                                            dataKey="value"
                                            nameKey="name"
                                        >
                                            {statusData.map((entry) => (
                                                <Cell
                                                    key={entry.id}
                                                    fill={STATUS_HEX[entry.id]}
                                                    stroke="var(--background)"
                                                    strokeWidth={2}
                                                />
                                            ))}
                                        </Pie>
                                        <Tooltip />
                                        <Legend />
                                    </PieChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Tiket per Prioritas
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {stats.total === 0 ? (
                                <EmptyChart />
                            ) : (
                                <ResponsiveContainer width="100%" height={300}>
                                    <BarChart data={priorityData}>
                                        <CartesianGrid
                                            strokeDasharray="3 3"
                                            stroke="var(--border)"
                                            vertical={false}
                                        />
                                        <XAxis
                                            dataKey="name"
                                            fontSize={12}
                                            stroke="var(--muted-foreground)"
                                        />
                                        <YAxis
                                            allowDecimals={false}
                                            fontSize={12}
                                            stroke="var(--muted-foreground)"
                                        />
                                        <Tooltip
                                            cursor={{ fill: 'var(--muted)' }}
                                        />
                                        <Bar
                                            dataKey="value"
                                            name="Jumlah"
                                            radius={[4, 4, 0, 0]}
                                        >
                                            {priorityData.map((entry) => (
                                                <Cell
                                                    key={entry.id}
                                                    fill={
                                                        PRIORITY_HEX[entry.id]
                                                    }
                                                />
                                            ))}
                                        </Bar>
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Tren 30 Hari Terakhir
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ResponsiveContainer width="100%" height={300}>
                            <LineChart data={trendData}>
                                <CartesianGrid
                                    strokeDasharray="3 3"
                                    stroke="var(--border)"
                                    vertical={false}
                                />
                                <XAxis
                                    dataKey="date"
                                    fontSize={12}
                                    stroke="var(--muted-foreground)"
                                    interval="preserveStartEnd"
                                    minTickGap={24}
                                />
                                <YAxis
                                    allowDecimals={false}
                                    fontSize={12}
                                    stroke="var(--muted-foreground)"
                                />
                                <Tooltip />
                                <Legend />
                                <Line
                                    type="monotone"
                                    dataKey="Dibuat"
                                    stroke="#3b82f6"
                                    strokeWidth={2}
                                    dot={false}
                                />
                                <Line
                                    type="monotone"
                                    dataKey="Selesai"
                                    stroke="#22c55e"
                                    strokeWidth={2}
                                    dot={false}
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </CardContent>
                </Card>
            </div>
        </ClientPortalLayout>
    );
}

function EmptyChart() {
    return (
        <div className="flex h-[300px] items-center justify-center text-sm text-muted-foreground">
            Belum ada data tiket.
        </div>
    );
}
