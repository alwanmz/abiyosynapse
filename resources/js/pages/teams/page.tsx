import { ConfirmDialog } from '@/components/confirm-dialog';
import { ListHeader } from '@/components/list-header';
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
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { userHasPermission } from '@/lib/permissions';
import { BreadcrumbItem } from '@/types';
import { Team } from '@/types/team';
import { User } from '@/types/user';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    IconChevronDown,
    IconChevronUp,
    IconDotsVertical,
    IconEdit,
    IconTrash,
} from '@tabler/icons-react';
import { UserCheck, Users } from 'lucide-react';
import { useState } from 'react';
import AddMemberDialog from './components/add-member-dialog';
import CreateTeamDialog from './components/create-team-dialog';
import EditTeamDialog from './components/edit-team-dialog';
import RemoveMemberDialog from './components/remove-member-dialog';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tim',
        href: '#',
    },
];

interface TeamsProps {
    teams: Team[];
    allUsers: User[];
}

function TeamCard({
    team,
    allUsers,
    canUpdateTeam,
    canDeleteTeam,
    canManageMembers,
}: {
    team: Team;
    allUsers: User[];
    canUpdateTeam: boolean;
    canDeleteTeam: boolean;
    canManageMembers: boolean;
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const { delete: destroy, processing: deleting } = useForm({});

    const handleDeleteTeam = () => {
        destroy(`/teams/${team.id}`, {
            preserveScroll: true,
            onSuccess: () => setDeleteOpen(false),
        });
    };

    const availableUsers = allUsers.filter(
        (user) => !team.members.some((member) => member.id === user.id),
    );

    return (
        <>
        <Card className="overflow-hidden">
            <CardHeader className="bg-muted/30 pb-3">
                <div className="flex items-start justify-between">
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <CardTitle className="text-lg">
                                {team.name}
                            </CardTitle>
                            <Badge
                                variant="outline"
                                className="border-green-500/50 bg-green-500/10 text-green-700 dark:text-green-400"
                            >
                                Aktif
                            </Badge>
                        </div>
                        <CardDescription className="mt-1">
                            {team.description}
                        </CardDescription>
                    </div>

                    {(canUpdateTeam || canDeleteTeam) && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-8 w-8"
                                >
                                    <IconDotsVertical className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuLabel>
                                    Aksi Tim
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                {canUpdateTeam && (
                                    <EditTeamDialog
                                        team={team}
                                        trigger={
                                            <DropdownMenuItem
                                                onSelect={(e) => e.preventDefault()}
                                            >
                                                <IconEdit className="mr-2 h-4 w-4" />
                                                Edit Detail
                                            </DropdownMenuItem>
                                        }
                                    />
                                )}
                                {canUpdateTeam && canDeleteTeam && <DropdownMenuSeparator />}
                                {canDeleteTeam && (
                                    <DropdownMenuItem
                                        variant="destructive"
                                        className="text-destructive"
                                        onSelect={(e) => {
                                            e.preventDefault();
                                            setDeleteOpen(true);
                                        }}
                                    >
                                        <IconTrash className="mr-2 h-4 w-4" />
                                        Hapus Tim
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>
            </CardHeader>

            <CardContent className="p-4">
                <div className="mb-4 grid grid-cols-2 gap-4 text-center">
                    <div className="rounded-lg bg-muted/50 p-3">
                        <div className="text-2xl font-bold">
                            {team.members_count}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            Anggota
                        </div>
                    </div>
                    <div className="rounded-lg bg-muted/50 p-3">
                        <div className="text-xs text-muted-foreground">
                            Manajer Produk
                        </div>
                        <div className="text-sm font-medium">
                            {team.project_manager?.name || 'Belum ditugaskan'}
                        </div>
                    </div>
                </div>

                <Separator className="my-4" />

                <Collapsible open={isOpen} onOpenChange={setIsOpen}>
                    <div className="flex items-center justify-between">
                        <div className="text-sm font-medium">Anggota Tim</div>
                        <CollapsibleTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 px-2"
                            >
                                {isOpen ? 'Tampilkan Sedikit' : 'Tampilkan Semua'}
                                {isOpen ? (
                                    <IconChevronUp className="ml-1 h-4 w-4" />
                                ) : (
                                    <IconChevronDown className="ml-1 h-4 w-4" />
                                )}
                            </Button>
                        </CollapsibleTrigger>
                    </div>

                    {!isOpen && (
                        <div className="mt-3 flex -space-x-3">
                            {team.members.slice(0, 5).map((member) => (
                                <UserAvatar
                                    key={member.id}
                                    user={member}
                                    className="h-10 w-10 border-2 border-background"
                                    fallbackStyle={{
                                        backgroundColor: `${team.color}20`,
                                        color: team.color,
                                    }}
                                />
                            ))}
                            {team.members_count > 5 && (
                                <div
                                    className="flex h-10 w-10 items-center justify-center rounded-full border-2 border-background text-xs font-semibold"
                                    style={{
                                        backgroundColor: `${team.color}20`,
                                        color: team.color,
                                    }}
                                >
                                    +{team.members_count - 5}
                                </div>
                            )}
                        </div>
                    )}

                    <CollapsibleContent className="mt-3 space-y-2">
                        {team.members.map((member) => (
                            <div
                                key={member.id}
                                className="flex items-center gap-3 rounded-lg border p-2 transition-colors hover:bg-muted/50"
                            >
                                <UserAvatar
                                    user={member}
                                    className="h-9 w-9"
                                    fallbackClassName="text-xs"
                                />
                                <div className="flex-1">
                                    <div className="text-sm font-medium">
                                        {member.name}
                                    </div>
                                    <div className="text-xs">{member.role}</div>
                                    <div className="text-xs text-muted-foreground">
                                        {member.email}
                                    </div>
                                </div>

                                {canManageMembers && (
                                    <RemoveMemberDialog
                                        teamId={team.id}
                                        userId={member.id}
                                        userName={member.name}
                                    />
                                )}
                            </div>
                        ))}
                    </CollapsibleContent>
                </Collapsible>

                <div className="mt-4">
                    {canManageMembers && (
                        <AddMemberDialog
                            teamId={team.id}
                            availableUsers={availableUsers}
                        />
                    )}
                </div>
            </CardContent>
        </Card>

        <ConfirmDialog
            open={deleteOpen}
            onOpenChange={setDeleteOpen}
            title="Hapus tim?"
            description={
                <span>
                    Tim <strong>{team.name}</strong> akan dihapus. Tim yang masih dipakai proyek tidak akan bisa dihapus.
                </span>
            }
            confirmLabel="Ya, Hapus Tim"
            loading={deleting}
            onConfirm={handleDeleteTeam}
        />
        </>
    );
}

export default function TeamsPage({ teams, allUsers }: TeamsProps) {
    const { auth } = usePage().props as any;
    const totalMembers = teams.reduce(
        (sum, team) => sum + team.members_count,
        0,
    );
    const totalTeams = teams.length;

    const canCreateTeam = userHasPermission(auth, 'teams.create');
    const canUpdateTeam = userHasPermission(auth, 'teams.update');
    const canDeleteTeam = userHasPermission(auth, 'teams.delete');
    const canManageMembers = userHasPermission(auth, 'teams.manage-members');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tim" />

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Manajemen Tim
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Organisasi dan kelola tim proyek Anda
                        </p>
                    </div>

                    {canCreateTeam && <CreateTeamDialog />}
                </div>

                {/* Stats Cards */}
                <div className="mb-6 grid gap-4 md:grid-cols-2">
                    <Card className="border-blue-200 bg-blue-50/50 dark:border-blue-900/50 dark:bg-blue-950/20">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Tim
                            </CardTitle>
                            <Users className="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                {totalTeams}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Tim aktif di organisasi
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-purple-200 bg-purple-50/50 dark:border-purple-900/50 dark:bg-purple-950/20">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Anggota
                            </CardTitle>
                            <UserCheck className="h-4 w-4 text-purple-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                {totalMembers}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Di semua tim
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card className="overflow-hidden p-0">
                    <ListHeader
                        title="Daftar Tim"
                        action={
                            <span className="rounded bg-white/20 px-2 py-0.5 font-mono text-xs">
                                {teams.length} Tim
                            </span>
                        }
                    />
                    <CardContent className="p-5">
                        {teams.length > 0 ? (
                            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                {teams.map((team) => (
                                    <TeamCard
                                        key={team.id}
                                        team={team}
                                        allUsers={allUsers}
                                        canUpdateTeam={canUpdateTeam}
                                        canDeleteTeam={canDeleteTeam}
                                        canManageMembers={canManageMembers}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="py-12 text-center text-muted-foreground">
                                Belum ada tim.
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
