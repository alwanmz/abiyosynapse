import { UserAvatar } from '@/components/user-avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Project } from '@/types/project';
import {
    IconCalendar,
    IconDownload,
    IconFile,
    IconUsers,
} from '@tabler/icons-react';
import { statusColors } from '../constants';

interface ViewProjectDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    project: Project | null;
}

export function ViewProjectDialog({
    open,
    onOpenChange,
    project,
}: ViewProjectDialogProps) {
    if (!project) return null;

    const statusConfig = statusColors[project.status];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                <DialogHeader className="mt-2">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex-1">
                            <DialogTitle className="text-2xl">
                                {project.name}
                            </DialogTitle>
                            <DialogDescription className="mt-2">
                                Created on {project.created_at} by{' '}
                                {project.creator}
                            </DialogDescription>
                        </div>
                        <Badge
                            variant="outline"
                            className={`${statusConfig.bgColor} ${statusConfig.color}`}
                        >
                            {statusConfig.label}
                        </Badge>
                    </div>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Description */}
                    <div>
                        <Label className="text-base font-semibold">
                            Deskripsi
                        </Label>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {project.description || 'Tidak ada deskripsi.'}
                        </p>
                    </div>

                    <Separator />

                    {/* Files & Image */}
                    {(project.file_path || project.image_path) && (
                        <>
                            <div>
                                <Label className="text-base font-semibold">
                                    Lampiran
                                </Label>
                                <div className="mt-3 grid gap-3">
                                    {project.file_path && (
                                        <div className="flex items-center justify-between rounded-lg border p-3">
                                            <div className="flex items-center gap-3">
                                                <div className="rounded-lg bg-primary/10 p-2">
                                                    <IconFile className="h-5 w-5 text-primary" />
                                                </div>
                                                <div>
                                                    <div className="font-medium">
                                                        {project.file_name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        File dokumen
                                                    </div>
                                                </div>
                                            </div>
                                            <a
                                                href={`/storage/${project.file_path}`}
                                                download
                                            >
                                                <Button
                                                    size="sm"
                                                    className="gap-1"
                                                >
                                                    <IconDownload className="h-4 w-4" />
                                                    Unduh
                                                </Button>
                                            </a>
                                        </div>
                                    )}
                                    {project.image_path && (
                                        <div className="rounded-lg border p-3">
                                            <img
                                                src={`/storage/${project.image_path}`}
                                                alt={project.name}
                                                className="h-48 w-full rounded object-cover"
                                            />
                                            <div className="mt-2 text-sm text-muted-foreground">
                                                {project.image_name}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                            <Separator />
                        </>
                    )}

                    {/* Timeline */}
                    <div>
                        <Label className="text-base font-semibold">
                            Timeline
                        </Label>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            <div className="rounded-lg border p-3">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <IconCalendar className="h-4 w-4" />
                                    Tanggal Mulai
                                </div>
                                <div className="mt-1 text-sm font-medium">
                                    {project.start_date
                                        ? new Date(
                                              project.start_date,
                                          ).toLocaleDateString('id-ID', {
                                              day: 'numeric',
                                              month: 'long',
                                              year: 'numeric',
                                          })
                                        : 'Belum diatur'}
                                </div>
                            </div>
                            <div className="rounded-lg border p-3">
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <IconCalendar className="h-4 w-4" />
                                    Tanggal Target Selesai
                                </div>
                                <div className="mt-1 text-sm font-medium">
                                    {project.end_date
                                        ? new Date(
                                              project.end_date,
                                          ).toLocaleDateString('id-ID', {
                                              day: 'numeric',
                                              month: 'long',
                                              year: 'numeric',
                                          })
                                        : 'Belum diatur'}
                                </div>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Project Manager */}
                    <div>
                        <Label className="text-base font-semibold">
                            Manajer Proyek
                        </Label>
                        <div className="mt-3 rounded-lg border p-4">
                            <div className="flex items-center gap-3">
                                <UserAvatar
                                    user={project.project_manager}
                                    className="h-12 w-12"
                                    fallbackClassName="bg-primary/10 text-primary"
                                />
                                <div>
                                    <div className="font-medium">
                                        {project.project_manager.name}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        Manajer Produk
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Team Information */}
                    <div>
                        <Label className="text-base font-semibold">
                            Informasi Tim
                        </Label>
                        <div className="mt-3 rounded-lg border p-4">
                            <div className="mb-3 flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <div
                                        className="h-4 w-4 rounded-full"
                                        style={{
                                            backgroundColor: project.team.color,
                                        }}
                                    />
                                    <span className="font-medium">
                                        {project.team.name}
                                    </span>
                                </div>
                                <div className="flex items-center gap-1 text-sm text-muted-foreground">
                                    <IconUsers className="h-4 w-4" />
                                    {project.team.members_count} members
                                </div>
                            </div>

                            <Separator className="my-3" />

                            <div>
                                <div className="mb-2 text-sm font-medium">
                                    Anggota Tim
                                </div>
                                <div className="grid gap-2">
                                    {project.team.members.map((member) => (
                                        <div
                                            key={member.id}
                                            className="flex items-center gap-3 rounded-lg bg-muted/50 p-2"
                                        >
                                            <UserAvatar
                                                user={member}
                                                className="h-8 w-8"
                                                fallbackClassName="text-xs font-semibold"
                                                fallbackStyle={{
                                                    backgroundColor: `${project.team.color}20`,
                                                    color: project.team.color,
                                                }}
                                            />
                                            <span className="text-sm">
                                                {member.name}
                                            </span>
                                        </div>
                                    ))}
                                    {project.team.members_count > 6 && (
                                        <div className="rounded-lg bg-muted/50 p-2 text-center text-sm text-muted-foreground">
                                            +{project.team.members_count - 6}{' '}
                                            anggota lainnya
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
