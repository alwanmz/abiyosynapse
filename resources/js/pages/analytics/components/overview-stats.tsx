import { OverviewStats } from '@/types/analytics';
import { BarChart3, CheckCircle2, Clock, FolderKanban, Users, Ticket } from 'lucide-react';
import { StatCard } from './stat-card';

interface OverviewStatsProps {
    stats: OverviewStats;
}

export function OverviewStatsCards({ stats }: OverviewStatsProps) {
    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <StatCard
                title="Total Tiket"
                value={stats.totalTickets}
                icon={Ticket}
                iconColor="text-blue-600 dark:text-blue-400"
                iconBgColor="bg-blue-100 dark:bg-blue-900/20"
            />
            <StatCard
                title="Proyek Aktif"
                value={stats.activeProjects}
                icon={FolderKanban}
                iconColor="text-purple-600 dark:text-purple-400"
                iconBgColor="bg-purple-100 dark:bg-purple-900/20"
            />
            <StatCard
                title="Total Tim"
                value={stats.totalTeams}
                icon={Users}
                iconColor="text-green-600 dark:text-green-400"
                iconBgColor="bg-green-100 dark:bg-green-900/20"
            />
            <StatCard
                title="Total Pengguna"
                value={stats.totalUsers}
                icon={Users}
                iconColor="text-orange-600 dark:text-orange-400"
                iconBgColor="bg-orange-100 dark:bg-orange-900/20"
            />
            <StatCard
                title="Selesai Bulan Ini"
                value={stats.completedThisMonth}
                icon={CheckCircle2}
                iconColor="text-emerald-600 dark:text-emerald-400"
                iconBgColor="bg-emerald-100 dark:bg-emerald-900/20"
            />
            <StatCard
                title="Rata-rata Waktu Selesai"
                value={`${stats.avgCompletionTime} hari`}
                icon={Clock}
                iconColor="text-amber-600 dark:text-amber-400"
                iconBgColor="bg-amber-100 dark:bg-amber-900/20"
            />
        </div>
    );
}
