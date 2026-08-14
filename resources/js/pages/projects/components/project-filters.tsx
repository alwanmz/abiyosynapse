import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { IconPlus, IconSearch } from '@tabler/icons-react';

interface ProjectFiltersProps {
    searchQuery: string;
    selectedStatus: string;
    onSearchChange: (value: string) => void;
    onStatusChange: (value: string) => void;
    onCreateClick: () => void;
    canCreate?: boolean;
}

export function ProjectFilters({
    searchQuery,
    selectedStatus,
    onSearchChange,
    onStatusChange,
    onCreateClick,
    canCreate = true,
}: ProjectFiltersProps) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            {/* Status Filter */}
            <Select value={selectedStatus} onValueChange={onStatusChange}>
                <SelectTrigger className="w-[150px]">
                    <SelectValue placeholder="Semua Status" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Semua Status</SelectItem>
                    <SelectItem value="planning">Perencanaan</SelectItem>
                    <SelectItem value="in_progress">Sedang Berjalan</SelectItem>
                    <SelectItem value="on_hold">Ditunda</SelectItem>
                    <SelectItem value="completed">Selesai</SelectItem>
                    <SelectItem value="cancelled">Dibatalkan</SelectItem>
                </SelectContent>
            </Select>

            {/* Search */}
            <div className="relative">
                <IconSearch className="absolute top-1/2 left-2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    placeholder="Cari proyek..."
                    className="w-[200px] pl-8"
                    value={searchQuery}
                    onChange={(e) => onSearchChange(e.target.value)}
                />
            </div>

            {canCreate && (
                <Button onClick={onCreateClick}>
                    <IconPlus className="mr-2 h-4 w-4" />
                    Buat Proyek
                </Button>
            )}
        </div>
    );
}
