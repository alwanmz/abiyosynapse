import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { userHasPermission } from '@/lib/permissions';
import { BreadcrumbItem, type SharedData } from '@/types';
import { Ticket, TicketPageProps } from '@/types/ticket';
import { Head, router } from '@inertiajs/react';
import { IconLayoutKanban, IconList, IconSearch } from '@tabler/icons-react';
import { useEffect, useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { BurndownChart } from './components/burndown-chart';
import { CreateTicketDialog } from './components/create-ticket-dialog';
import { KanbanBoard } from './components/kanban-board';
import { setAllExpanded, useCollapsedCount } from './hooks/use-card-expansion';
import { TicketLegends } from './components/ticket-legends';
import { TicketStats } from './components/ticket-stats';
import { TicketTable } from './components/ticket-table';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tiket',
        href: '/tickets',
    },
];

export default function TicketsPage({
    tickets,
    projects,
    clients,
    timelines,
    allUsers = [],
    filters,
}: TicketPageProps & { auth: { user: { id: number } } }) {
    const [search, setSearch] = useState(filters?.search || '');
    const [selectedClient, setSelectedClient] = useState(filters?.client_id || 'all');
    const [selectedProject, setSelectedProject] = useState(filters?.project_id || 'all');
    const [ticketView, setTicketView] = useState<'active' | 'archive'>(filters?.view || 'active');
    const [selectedTimeline, setSelectedTimeline] = useState<string | null>(null);
    const [activeTab, setActiveTab] = useState<string>('kanban');
    const collapsedCount = useCollapsedCount();
    const allExpanded = collapsedCount === 0;

    // Default: tiap halaman Tiket dibuka/di-mount, kartu kembali expanded semua.
    useEffect(() => {
        setAllExpanded([], true);
    }, []);

    // Filter tickets based on selection and search
    const { auth } = usePage<SharedData>().props;
    const canCreateTicket = userHasPermission(auth, 'tickets.create');

    const filteredProjects = useMemo(() => {
        return projects.filter(
            (project) => selectedClient === 'all' || String(project.client_id ?? '') === selectedClient,
        );
    }, [projects, selectedClient]);

    const reloadTickets = (next: Partial<{ client_id: string; project_id: string; view: 'active' | 'archive'; search: string }>) => {
        const payload = {
            client_id: next.client_id ?? selectedClient,
            project_id: next.project_id ?? selectedProject,
            view: next.view ?? ticketView,
            search: next.search ?? search,
        };

        router.get('/tickets', payload, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const filteredTickets = useMemo(() => {
        const query = search.trim().toLowerCase();

        return tickets.filter((ticket) => {
            const searchable = [
                ticket.title,
                ticket.ticket_number,
                ticket.description,
                ticket.client?.nama,
                ticket.client?.kode,
                ticket.project?.name,
                ticket.reporter?.name,
                ticket.assigned_user?.name,
                ticket.delegated_user?.name,
                ...(ticket.assignees ?? []).map((u) => u.name),
                ticket.approver?.name,
                ticket.rejecter?.name,
            ]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            const matchesSearch = !query || searchable.includes(query);
            const matchesClient = selectedClient === 'all' || String(ticket.client_id) === selectedClient;
            const matchesProject = selectedProject === 'all' || String(ticket.project_id ?? '') === selectedProject;

            return matchesSearch && matchesClient && matchesProject;
        });
    }, [tickets, search, selectedClient, selectedProject]);

    // Stats calculation
    const stats = useMemo(() => {
        return {
            total: filteredTickets.length,
            inProgress: filteredTickets.filter(t => t.status === 'inprogress').length,
            done: filteredTickets.filter(t => t.status === 'done').length,
            storyPoints: filteredTickets.reduce((acc, t) => acc + (t.story_points ?? 0), 0),
        };
    }, [filteredTickets]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tiket" />

            <div className="p-6">
                {/* Header */}
                <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Manajemen Tiket
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola tugas, bug, dan fitur di semua proyek Anda
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Select
                            value={selectedClient}
                            onValueChange={(value) => {
                                setSelectedClient(value);
                                setSelectedProject('all');
                                reloadTickets({ client_id: value, project_id: 'all' });
                            }}
                        >
                            <SelectTrigger className="w-[190px]">
                                <SelectValue placeholder="Pilih client" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua Client</SelectItem>
                                {clients.map((client) => (
                                    <SelectItem key={client.id} value={client.id.toString()}>
                                        {client.kode} - {client.nama}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                         <Select
                            value={selectedProject}
                            onValueChange={(value) => {
                                setSelectedProject(value);
                                reloadTickets({ project_id: value });
                            }}
                         >
                            <SelectTrigger className="w-[180px]">
                                <SelectValue placeholder="Pilih proyek" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua Proyek</SelectItem>
                                {filteredProjects.map((p) => (
                                    <SelectItem key={p.id} value={p.id.toString()}>
                                        {p.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <div className="relative">
                            <IconSearch className="absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder="Cari tiket/user/client..."
                                className="w-[220px] pl-8"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') reloadTickets({ search });
                                }}
                            />
                        </div>

                        {canCreateTicket && (
                            <CreateTicketDialog
                                projects={projects}
                                clients={clients}
                                timelines={timelines}
                                allUsers={allUsers}
                                selectedProjectId={selectedProject}
                            />
                        )}
                    </div>
                </div>

                {/* Burndown Chart */}
                <div className="mb-4 flex items-center justify-between gap-2">
                    <Select value={selectedTimeline ?? 'none'} onValueChange={(v) => setSelectedTimeline(v === 'none' ? null : v)}>
                        <SelectTrigger className="w-[220px]">
                            <SelectValue placeholder="Pilih timeline untuk burndown" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">-- Tanpa Burndown --</SelectItem>
                            {timelines.map((t) => (
                                <SelectItem key={t.id} value={t.id.toString()}>
                                    {t.title}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {activeTab === 'kanban' && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setAllExpanded(filteredTickets.map((t) => t.id), !allExpanded)}
                        >
                            {allExpanded ? 'Ringkas Semua' : 'Perluas Semua'}
                        </Button>
                    )}
                </div>
                <BurndownChart timelineId={selectedTimeline} />

                {/* Stats */}
                <TicketStats
                    totalTickets={stats.total}
                    totalStoryPoints={stats.storyPoints}
                    inProgressTickets={stats.inProgress}
                    doneTickets={stats.done}
                />

                <div className="mt-6 flex items-center gap-2">
                    <Button
                        variant={ticketView === 'active' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => {
                            setTicketView('active');
                            reloadTickets({ view: 'active' });
                        }}
                    >
                        Aktif
                    </Button>
                    <Button
                        variant={ticketView === 'archive' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => {
                            setTicketView('archive');
                            reloadTickets({ view: 'archive' });
                        }}
                    >
                        Arsip
                    </Button>
                </div>

                <Tabs value={activeTab} onValueChange={setActiveTab} className="mt-4">
                    <div className="flex items-center justify-between mb-4">
                        <TabsList>
                            <TabsTrigger value="kanban" className="flex items-center gap-2">
                                <IconLayoutKanban className="h-4 w-4" />
                                Kanban
                            </TabsTrigger>
                            <TabsTrigger value="table" className="flex items-center gap-2">
                                <IconList className="h-4 w-4" />
                                Tabel
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <TabsContent value="kanban" className="mt-0 border-none p-0 outline-none">
                        <KanbanBoard
                            tickets={filteredTickets}
                            projects={projects}
                            timelines={timelines}
                            clients={clients}
                            allUsers={allUsers}
                            view={ticketView}
                        />
                    </TabsContent>

                    <TabsContent value="table" className="mt-0">
                        <TicketTable 
                            tickets={filteredTickets} 
                            projects={projects} 
                            timelines={timelines} 
                        />
                    </TabsContent>
                </Tabs>

                <div className="mt-8 border-t pt-8">
                    <TicketLegends />
                </div>
            </div>
        </AppLayout>
    );
}

// Add to props interface in components/kanban-board.tsx or types
interface TicketWithTimer extends Ticket {
    active_time_log?: any[];
}
