import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { UserAvatar } from '@/components/user-avatar';
import { cn } from '@/lib/utils';
import { UserSummary } from '@/types/ticket';
import { IconChevronDown, IconX } from '@tabler/icons-react';
import { useMemo, useState } from 'react';

interface MultiUserSelectProps {
    users: UserSummary[];
    value: string[];
    onChange: (value: string[]) => void;
    placeholder?: string;
    disabled?: boolean;
    id?: string;
}

export function MultiUserSelect({
    users,
    value,
    onChange,
    placeholder = 'Pilih user',
    disabled = false,
    id,
}: MultiUserSelectProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const selectedUsers = useMemo(
        () => users.filter((u) => value.includes(u.id.toString())),
        [users, value],
    );

    const filteredUsers = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return users;
        return users.filter((u) => u.name.toLowerCase().includes(q));
    }, [users, search]);

    const toggle = (userId: string) => {
        if (value.includes(userId)) {
            onChange(value.filter((v) => v !== userId));
        } else {
            onChange([...value, userId]);
        }
    };

    const remove = (userId: string) => onChange(value.filter((v) => v !== userId));

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    disabled={disabled}
                    className={cn(
                        'h-auto min-h-9 w-full justify-between gap-1 px-3 py-1.5 font-normal',
                        selectedUsers.length === 0 && 'text-muted-foreground',
                    )}
                >
                    <span className="flex flex-1 flex-wrap items-center gap-1">
                        {selectedUsers.length === 0 ? (
                            placeholder
                        ) : (
                            selectedUsers.map((u) => (
                                <Badge
                                    key={u.id}
                                    variant="secondary"
                                    className="flex items-center gap-1 py-0.5 pr-1 pl-1"
                                >
                                    <UserAvatar user={u} className="h-4 w-4" fallbackClassName="text-[8px]" />
                                    <span className="text-xs">{u.name}</span>
                                    <span
                                        role="button"
                                        tabIndex={-1}
                                        className="ml-0.5 rounded-sm hover:bg-muted-foreground/20"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            remove(u.id.toString());
                                        }}
                                    >
                                        <IconX className="h-3 w-3" />
                                    </span>
                                </Badge>
                            ))
                        )}
                    </span>
                    <IconChevronDown className="h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-(--radix-popover-trigger-width) p-0" align="start">
                <div className="p-2">
                    <Input
                        placeholder="Cari nama..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="h-8"
                    />
                </div>
                <div className="max-h-60 overflow-y-auto px-1 pb-1">
                    {filteredUsers.length === 0 ? (
                        <p className="px-2 py-3 text-center text-xs text-muted-foreground">
                            Tidak ada user
                        </p>
                    ) : (
                        filteredUsers.map((u) => {
                            const checked = value.includes(u.id.toString());
                            return (
                                <button
                                    key={u.id}
                                    type="button"
                                    onClick={() => toggle(u.id.toString())}
                                    className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-muted"
                                >
                                    <Checkbox checked={checked} className="pointer-events-none" />
                                    <UserAvatar user={u} className="h-6 w-6" fallbackClassName="text-[10px]" />
                                    <span className="truncate">{u.name}</span>
                                </button>
                            );
                        })
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
