import { ConfirmDialog } from '@/components/confirm-dialog';
import { ListHeader } from '@/components/list-header';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { userHasPermission } from '@/lib/permissions';
import { BreadcrumbItem, SharedData } from '@/types';
import { Project, ProjectsProps } from '@/types/project';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CreateProjectDialog } from './components/create-project-dialog';
import { EditProjectDialog } from './components/edit-project-dialog';
import { EmptyState } from './components/empty-state';
import { ProjectCard } from './components/project-card';
import { ProjectFilters } from './components/project-filters';
import { ProjectStats } from './components/project-stats';
import { ProjectStatusLegend } from './components/project-status-legend';
import { ViewProjectDialog } from './components/view-project-dialog';
import { useProjectFilters, useProjectStats } from './hooks/use-project-data';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Proyek',
        href: '#',
    },
];

export default function ProjectsPage({ projects, teams }: ProjectsProps) {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedStatus, setSelectedStatus] = useState('all');
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [viewDialogOpen, setViewDialogOpen] = useState(false);
    const [selectedProject, setSelectedProject] = useState<Project | null>(
        null,
    );
    const [deletingProject, setDeletingProject] = useState<Project | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const { auth } = usePage<SharedData>().props;
    const filteredProjects = useProjectFilters(
        projects,
        searchQuery,
        selectedStatus,
    );
    const stats = useProjectStats(filteredProjects);

    const canCreateProject = userHasPermission(auth, 'projects.create');
    const canManageProject =
        userHasPermission(auth, 'projects.edit') ||
        userHasPermission(auth, 'projects.update-any') ||
        userHasPermission(auth, 'projects.delete');

    const handleCreateClick = () => {
        setCreateDialogOpen(true);
    };

    const handleEditClick = (project: Project) => {
        setSelectedProject(project);
        setEditDialogOpen(true);
    };

    const handleViewClick = (project: Project) => {
        setSelectedProject(project);
        setViewDialogOpen(true);
    };

    const handleDeleteClick = (project: Project) => {
        setDeletingProject(project);
    };

    const handleDeleteConfirm = () => {
        if (!deletingProject) return;

        setIsDeleting(true);
        router.delete(`/projects/${deletingProject.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeletingProject(null),
            onFinish: () => setIsDeleting(false),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Proyek" />

            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Manajemen Proyek
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola dan lacak semua proyek Anda
                        </p>
                    </div>

                    <ProjectFilters
                        searchQuery={searchQuery}
                        selectedStatus={selectedStatus}
                        onSearchChange={setSearchQuery}
                        onStatusChange={setSelectedStatus}
                        onCreateClick={handleCreateClick}
                        canCreate={canCreateProject}
                    />
                </div>

                {/* Stats Cards */}
                <ProjectStats {...stats} />

                {/* Projects Grid */}
                <Card className="overflow-hidden p-0">
                    <ListHeader
                        title="Daftar Proyek"
                        action={
                            <span className="rounded bg-white/20 px-2 py-0.5 font-mono text-xs">
                                {filteredProjects.length} Proyek
                            </span>
                        }
                    />
                    <CardContent className="p-5">
                        {filteredProjects.length > 0 ? (
                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                {filteredProjects.map((project) => (
                                    <ProjectCard
                                        key={project.id}
                                        project={project}
                                        canActions={canManageProject}
                                        onEdit={handleEditClick}
                                        onView={handleViewClick}
                                        onDelete={handleDeleteClick}
                                    />
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                onCreateClick={handleCreateClick}
                                canCreate={canCreateProject}
                            />
                        )}
                    </CardContent>
                </Card>

                {/* Project Status Legend */}
                <ProjectStatusLegend />

                {/* Create Project Dialog */}
                {canCreateProject && (
                    <CreateProjectDialog
                        open={createDialogOpen}
                        onOpenChange={setCreateDialogOpen}
                        teams={teams}
                    />
                )}

                {/* Edit Project Dialog */}
                {canManageProject && (
                    <EditProjectDialog
                        open={editDialogOpen}
                        onOpenChange={setEditDialogOpen}
                        project={selectedProject}
                        teams={teams}
                    />
                )}

                {/* View Project Dialog */}
                <ViewProjectDialog
                    open={viewDialogOpen}
                    onOpenChange={setViewDialogOpen}
                    project={selectedProject}
                />

                <ConfirmDialog
                    open={!!deletingProject}
                    onOpenChange={(open) => !open && setDeletingProject(null)}
                    title="Hapus proyek?"
                    description={
                        <>
                            Proyek <strong className="text-foreground">{deletingProject?.name}</strong> akan dihapus.
                            Tiket yang masih terkait akan dilepas dari proyek, tetapi tetap terikat ke client.
                        </>
                    }
                    confirmLabel="Ya, Hapus"
                    loading={isDeleting}
                    onConfirm={handleDeleteConfirm}
                />
            </div>
        </AppLayout>
    );
}
