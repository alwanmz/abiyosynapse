import { User } from '@/types/user';
import { useMemo, useState } from 'react';

export function useUserFilters(users: User[]) {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedRole, setSelectedRole] = useState<string>('all');

    const filteredUsers = useMemo(() => {
        return users.filter((user) => {
            const matchSearch =
                searchQuery === '' ||
                user.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                user.email.toLowerCase().includes(searchQuery.toLowerCase());
            const matchRole =
                selectedRole === 'all' || user.role?.name === selectedRole;
            return matchSearch && matchRole;
        });
    }, [users, searchQuery, selectedRole]);

    const uniqueRoles = useMemo(() => {
        const roles = new Set(users.map((u) => u.role?.name).filter(Boolean));
        return Array.from(roles);
    }, [users]);

    return {
        searchQuery,
        setSearchQuery,
        selectedRole,
        setSelectedRole,
        filteredUsers,
        uniqueRoles,
    };
}
