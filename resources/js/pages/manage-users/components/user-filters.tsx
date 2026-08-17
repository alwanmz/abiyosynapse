import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { User } from '@/types/user';
import { IconSearch } from '@tabler/icons-react';
import { useTranslation } from 'react-i18next';

interface UserFiltersProps {
    searchQuery: string;
    onSearchChange: (value: string) => void;
    selectedRole: string;
    onRoleChange: (value: string) => void;
    uniqueRoles: (string | undefined)[];
    users: User[];
}

export function UserFilters({
    searchQuery,
    onSearchChange,
    selectedRole,
    onRoleChange,
    uniqueRoles,
    users,
}: UserFiltersProps) {
    const { t } = useTranslation('manage-users');

    return (
        <div className="flex flex-wrap items-center gap-2">
            {/* Search */}
            <div className="relative">
                <IconSearch className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    placeholder={t('search_placeholder')}
                    className="w-[250px] pl-9"
                    value={searchQuery}
                    onChange={(e) => onSearchChange(e.target.value)}
                />
            </div>

            {/* Role Filter */}
            <Select value={selectedRole} onValueChange={onRoleChange}>
                <SelectTrigger className="w-[180px]">
                    <SelectValue placeholder={t('all_roles')} />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{t('all_roles')}</SelectItem>
                    {uniqueRoles.map((role) => (
                        <SelectItem key={role} value={role!}>
                            {users
                                .find((u) => u.role?.name === role)
                                ?.role?.display_name?.replace(/ /g, ' ')}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
