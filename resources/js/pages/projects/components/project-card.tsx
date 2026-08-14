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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { Project } from '@/types/project';
import {
    IconCalendar,
    IconDotsVertical,
    IconEdit,
    IconEye,
    IconTrash,
    IconUsers,
} from '@tabler/icons-react';
import { statusColors } from '../constants';

interface ProjectCardProps {
    project: Project;
    canActions?: boolean;
    onEdit?: (project: Project) => void;
    onView?: (project: Project) => void;
    onDelete?: (project: Project) => void;
}

export function ProjectCard({
    project,
    canActions = false,
    onEdit,
    onView,
    onDelete,
}: ProjectCardProps) {
    const statusConfig = statusColors[project.status];

    return (
        <Card className="group transition-all hover:shadow-md">
            <CardHeader className="pb-3">
                <div className="mb-2 flex items-start justify-between">
                    <Badge
                        variant="outline"
                        className={`${statusConfig.bgColor} ${statusConfig.color}`}
                    >
                        {statusConfig.label}
                    </Badge>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 opacity-0 transition-opacity group-hover:opacity-100"
                            >
                                <IconDotsVertical className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel>
                                Aksi Proyek
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onSelect={() => onView?.(project)}
                            >
                                <IconEye className="mr-2 h-4 w-4" />
                                Lihat Detail
                            </DropdownMenuItem>
                            {canActions && (
                                <div>
                                    <DropdownMenuItem
                                        onSelect={() => onEdit?.(project)}
                                    >
                                        <IconEdit className="mr-2 h-4 w-4" />
                                        Edit Proyek
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        className="text-destructive"
                                        onSelect={() => onDelete?.(project)}
                                    >
                                        <IconTrash className="mr-2 h-4 w-4" />
                                        Hapus Proyek
                                    </DropdownMenuItem>
                                </div>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <CardTitle className="text-lg">{project.name}</CardTitle>
                <CardDescription className="line-clamp-2">
                    {project.description}
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                <Separator />

                <div className="space-y-3">
                    {/* Team */}
                    <div className="flex items-center gap-2">
                        <IconUsers className="h-4 w-4 text-muted-foreground" />
                        <span className="text-sm font-medium">
                            {project.team.name}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            ({project.team.members_count} members)
                        </span>
                    </div>

                    {/* Team Members Preview */}
                    <div className="flex -space-x-2">
                        {project.team.members.map((member) => (
                            <UserAvatar
                                key={member.id}
                                user={member}
                                className="h-8 w-8 border-2 border-background"
                                fallbackStyle={{
                                    backgroundColor: `${project.team.color}20`,
                                    color: project.team.color,
                                }}
                            />
                        ))}
                        {project.team.members_count > 6 && (
                            <div
                                className="flex h-8 w-8 items-center justify-center rounded-full border-2 border-background text-xs font-semibold"
                                style={{
                                    backgroundColor: `${project.team.color}20`,
                                    color: project.team.color,
                                }}
                            >
                                +{project.team.members_count - 6}
                            </div>
                        )}
                    </div>

                    {/* Project Manager */}
                    <div className="flex items-center gap-2 rounded-lg bg-muted/50 p-2">
                        <div className="text-xs text-muted-foreground">
                            Manajer Proyek:
                        </div>
                        <div className="text-sm font-medium">
                            {project.project_manager.name}
                        </div>
                    </div>

                    {/* Timeline */}
                    {(project.start_date || project.end_date) && (
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <IconCalendar className="h-4 w-4" />
                            <span>
                                {project.start_date
                                    ? new Date(
                                          project.start_date,
                                      ).toLocaleDateString('id-ID', {
                                          month: 'short',
                                          day: 'numeric',
                                          year: 'numeric',
                                      })
                                    : 'Belum ditentukan'}
                                {' - '}
                                {project.end_date
                                    ? new Date(
                                          project.end_date,
                                      ).toLocaleDateString('id-ID', {
                                          month: 'short',
                                          day: 'numeric',
                                          year: 'numeric',
                                      })
                                    : 'Belum ditentukan'}
                            </span>
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
