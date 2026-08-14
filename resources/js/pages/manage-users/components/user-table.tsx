import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { User } from '@/types/user';
import { Users } from 'lucide-react';
import { UserTableRow } from './user-table-row';

interface UserTableProps {
    users: User[];
    currentUserId?: number;
    onEditUser: (user: User) => void;
    onChangeRole: (user: User) => void;
    onDeleteUser: (user: User) => void;
}

export function UserTable({
    users,
    currentUserId,
    onEditUser,
    onChangeRole,
    onDeleteUser,
}: UserTableProps) {
    if (users.length === 0) {
        return (
            <div className="overflow-hidden rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-[50px]">#</TableHead>
                            <TableHead>Pengguna</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Peran</TableHead>
                            <TableHead>Bergabung</TableHead>
                            <TableHead className="w-[80px]">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow>
                            <TableCell colSpan={6} className="h-32 text-center">
                                <div className="flex flex-col items-center justify-center gap-2 text-muted-foreground">
                                    <Users className="h-10 w-10" />
                                    <p>Pengguna tidak ditemukan</p>
                                    <p className="text-xs">
                                        Coba sesuaikan filter Anda
                                    </p>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-[50px]">#</TableHead>
                        <TableHead>Pengguna</TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead>Peran</TableHead>
                        <TableHead>Bergabung</TableHead>
                        <TableHead className="w-[80px]">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {users.map((user, index) => (
                        <UserTableRow
                            key={user.id}
                            user={user}
                            index={index}
                            currentUserId={currentUserId}
                            onEditUser={onEditUser}
                            onChangeRole={onChangeRole}
                            onDeleteUser={onDeleteUser}
                        />
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
