import AppLayout from '@/layouts/app-layout';
import { BreadcrumbItem } from '@/types';
import { AnalyticsPageProps } from '@/types/analytics';
import { Head } from '@inertiajs/react';
import { AIInsights } from './components/ai-insights';
import { OverviewStatsCards } from './components/overview-stats';
import { TeamPerformanceTable } from './components/team-performance-table';
import { TeamWorkloadChart } from './components/team-workload-chart';
import { TicketPriorityChart } from './components/ticket-priority-chart';
import { TicketStatusChart } from './components/ticket-status-chart';
import { TopAssigneesChart } from './components/top-assignees-chart';
import { UserActivityTable } from './components/user-activity-table';
import { WeeklyDigest } from './components/weekly-digest';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dasbor',
        href: '#',
    },
];

export default function AnalyticsPage({ analytics }: AnalyticsPageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Analitik" />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Dasbor Analitik
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Wawasan komprehensif tentang kinerja dan produktivitas tim Anda
                    </p>
                </div>

                {/* Overview Stats */}
                <OverviewStatsCards stats={analytics.overview} />

                {/* AI Insights — full project executive summary */}
                <AIInsights />

                {/* AI Weekly Digest — wellness/activity insight from Daily Logs */}
                <WeeklyDigest />

                {/* Ticket Analytics Section */}
                <div>
                    <h2 className="mb-4 text-lg font-semibold">
                        Analitik Tiket
                    </h2>
                    <div className="grid gap-4 md:grid-cols-2">
                        <TicketStatusChart data={analytics.tickets.byStatus} />
                        <TicketPriorityChart
                            data={analytics.tickets.byPriority}
                        />
                    </div>
                </div>

                {/* Team Performance Section */}
                <div>
                    <h2 className="mb-4 text-lg font-semibold">
                        Kinerja Tim
                    </h2>
                    <div className="grid gap-4 lg:grid-cols-2">
                        <TeamWorkloadChart data={analytics.teams.workload} />
                        <TeamPerformanceTable
                            data={analytics.teams.completionRate}
                        />
                    </div>
                </div>

                {/* Assignees */}
                <div>
                    <h2 className="mb-4 text-lg font-semibold">
                        Kontributor Teratas
                    </h2>
                    <TopAssigneesChart data={analytics.tickets.byAssignee} />
                </div>

                {/* User Activity Section */}
                <div>
                    <h2 className="mb-4 text-lg font-semibold">
                        Aktivitas Pengguna
                    </h2>
                    <UserActivityTable data={analytics.users.userActivity} />
                </div>
            </div>
        </AppLayout>
    );
}
