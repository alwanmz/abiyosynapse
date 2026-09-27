import { ListHeader } from '@/components/list-header';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import { type SharedData } from '@/types';
import { User } from '@/types/user';
import { Head, usePage } from '@inertiajs/react';
import { type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AddUserDialog } from './components/add-user-dialog';
import { ChangeRoleDialog } from './components/change-role-dialog';
import { DeleteUserDialog } from './components/delete-user-dialog';
import { EditUserDialog } from './components/edit-user-dialog';
import { PageHeader } from './components/page-header';
import { Pagination } from './components/pagination';
import { UserFilters } from './components/user-filters';
import { UserStatsCards } from './components/user-stats-cards';
import { UserTable } from './components/user-table';
import { useUserFilters } from './hooks/use-user-filters';
import { useUserStats } from './hooks/use-user-stats';

interface PaginatedUsers {
    data: User[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface ManageUsersProps {
    users: PaginatedUsers;
    roles: Array<{ id: number; name: string; display_name: string }>;
}

function ManageUsersPage({ users, roles }: ManageUsersProps) {
    const { t } = useTranslation('manage-users');
    const { auth } = usePage<SharedData>().props;
    const currentUserId = auth?.user?.id;

    useBreadcrumbs([
        {
            title: t('breadcrumb'),
            href: '#',
        },
    ]);

    const [selectedUser, setSelectedUser] = useState<User | null>(null);
    const [addUserOpen, setAddUserOpen] = useState(false);
    const [changeRoleOpen, setChangeRoleOpen] = useState(false);
    const [deleteUserOpen, setDeleteUserOpen] = useState(false);
    const [editUserOpen, setEditUserOpen] = useState(false);

    const {
        searchQuery,
        setSearchQuery,
        selectedRole,
        setSelectedRole,
        filteredUsers,
        uniqueRoles,
    } = useUserFilters(users.data);

    const stats = useUserStats(users.data, uniqueRoles);

    const handleAddUser = () => {
        setAddUserOpen(true);
    };

    const handleEditUser = (user: User) => {
        setSelectedUser(user);
        setEditUserOpen(true);
    };

    const handleChangeRole = (user: User) => {
        setSelectedUser(user);
        setChangeRoleOpen(true);
    };

    const handleDeleteUser = (user: User) => {
        setSelectedUser(user);
        setDeleteUserOpen(true);
    };

    return (
        <>
            <Head title={t('head_title')} />

            <div className="p-6">
                <PageHeader onAddUser={handleAddUser} />

                <UserStatsCards stats={stats} />

                <Card className="mb-6 overflow-hidden p-0">
                    <ListHeader title={t('directory_title')} />

                    <CardContent className="p-5">
                        <div className="mb-4">
                            <UserFilters
                                searchQuery={searchQuery}
                                onSearchChange={setSearchQuery}
                                selectedRole={selectedRole}
                                onRoleChange={setSelectedRole}
                                uniqueRoles={uniqueRoles}
                                users={users.data}
                            />
                        </div>

                        <UserTable
                            users={filteredUsers}
                            currentUserId={currentUserId}
                            onEditUser={handleEditUser}
                            onChangeRole={handleChangeRole}
                            onDeleteUser={handleDeleteUser}
                        />

                        <div className="mt-4">
                            <Pagination pagination={users} />
                        </div>
                    </CardContent>
                </Card>

                <AddUserDialog
                    open={addUserOpen}
                    onOpenChange={setAddUserOpen}
                    availableRoles={roles}
                />

                <EditUserDialog
                    user={selectedUser}
                    open={editUserOpen}
                    onOpenChange={setEditUserOpen}
                />

                <ChangeRoleDialog
                    user={selectedUser}
                    open={changeRoleOpen}
                    onOpenChange={setChangeRoleOpen}
                    availableRoles={roles}
                />

                <DeleteUserDialog
                    user={selectedUser}
                    open={deleteUserOpen}
                    onOpenChange={setDeleteUserOpen}
                />
            </div>
        </>
    );
}

ManageUsersPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default ManageUsersPage;
