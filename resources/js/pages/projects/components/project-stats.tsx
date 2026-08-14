import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Briefcase,
    CheckCircle2,
    FolderKanban,
    TrendingUp,
} from 'lucide-react';

interface ProjectStatsProps {
    totalProjects: number;
    inProgressProjects: number;
    completedProjects: number;
    totalMembers: number;
}

export function ProjectStats({
    totalProjects,
    inProgressProjects,
    completedProjects,
    totalMembers,
}: ProjectStatsProps) {
    return (
        <div className="mb-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card className="border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-950/20">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Total Proyek
                    </CardTitle>
                    <FolderKanban className="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">{totalProjects}</div>
                    <p className="text-xs text-muted-foreground">
                        Proyek aktif
                    </p>
                </CardContent>
            </Card>

            <Card className="border-amber-200 bg-amber-50/50 dark:border-amber-900/50 dark:bg-amber-950/20">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Sedang Berjalan
                    </CardTitle>
                    <Briefcase className="h-4 w-4 text-amber-500" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-amber-600 dark:text-amber-400">
                        {inProgressProjects}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Sedang aktif
                    </p>
                </CardContent>
            </Card>

            <Card className="border-green-200 bg-green-50/50 dark:border-green-900/50 dark:bg-green-950/20">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Selesai
                    </CardTitle>
                    <CheckCircle2 className="h-4 w-4 text-green-500" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-green-600 dark:text-green-400">
                        {completedProjects}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Berhasil diselesaikan
                    </p>
                </CardContent>
            </Card>

            <Card className="border-purple-200 bg-purple-50/50 dark:border-purple-900/50 dark:bg-purple-950/20">
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Anggota Tim
                    </CardTitle>
                    <TrendingUp className="h-4 w-4 text-purple-500" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-purple-600 dark:text-purple-400">{totalMembers}</div>
                    <p className="text-xs text-muted-foreground">
                        Di semua proyek
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}
