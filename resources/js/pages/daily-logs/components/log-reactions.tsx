import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { router } from '@inertiajs/react';
import { IconMoodSmile, IconPlus } from '@tabler/icons-react';
import { useState } from 'react';

export interface ReactionUser {
    id: number;
    name: string;
}

export interface ReactionItem {
    id: number;
    daily_log_id: number;
    user_id: number;
    emoji: string;
    user?: ReactionUser | null;
}

interface LogReactionsProps {
    logId: number;
    reactions?: ReactionItem[];
    currentUserId?: number;
}

const QUICK_EMOJIS = ['👍', '❤️', '🔥', '👏', '🎉', '💡', '🚀', '😂'];

export function LogReactions({ logId, reactions = [], currentUserId }: LogReactionsProps) {
    const [popoverOpen, setPopoverOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Group reactions by emoji
    const grouped = reactions.reduce<
        Record<
            string,
            {
                emoji: string;
                count: number;
                users: string[];
                hasReacted: boolean;
            }
        >
    >((acc, item) => {
        if (!acc[item.emoji]) {
            acc[item.emoji] = {
                emoji: item.emoji,
                count: 0,
                users: [],
                hasReacted: false,
            };
        }
        acc[item.emoji].count += 1;
        if (item.user?.name) {
            acc[item.emoji].users.push(item.user.name);
        }
        if (currentUserId && item.user_id === currentUserId) {
            acc[item.emoji].hasReacted = true;
        }
        return acc;
    }, {});

    const reactionGroups = Object.values(grouped);

    const handleToggle = (emoji: string) => {
        if (isSubmitting) return;
        setIsSubmitting(true);
        setPopoverOpen(false);

        router.post(
            `/daily-logs/${logId}/react`,
            { emoji },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setIsSubmitting(false),
            },
        );
    };

    return (
        <TooltipProvider delayDuration={200}>
            <div className="flex flex-wrap items-center gap-1.5 pt-1.5">
                {reactionGroups.map((group) => (
                    <Tooltip key={group.emoji}>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                onClick={() => handleToggle(group.emoji)}
                                disabled={isSubmitting}
                                className={`inline-flex cursor-pointer items-center gap-1 rounded-full border px-2 py-0.5 text-xs transition-all ${
                                    group.hasReacted
                                        ? 'border-blue-300 bg-blue-50 font-medium text-blue-700 shadow-sm hover:bg-blue-100 dark:border-blue-700 dark:bg-blue-950/60 dark:text-blue-200'
                                        : 'border-border bg-background text-muted-foreground hover:border-slate-300 hover:bg-muted dark:hover:border-slate-700'
                                }`}
                            >
                                <span>{group.emoji}</span>
                                <span className="text-[11px] font-semibold">{group.count}</span>
                            </button>
                        </TooltipTrigger>
                        {group.users.length > 0 && (
                            <TooltipContent side="top" className="text-xs">
                                {group.users.slice(0, 5).join(', ')}
                                {group.users.length > 5 ? ` +${group.users.length - 5} lainnya` : ''}
                            </TooltipContent>
                        )}
                    </Tooltip>
                ))}

                <Popover open={popoverOpen} onOpenChange={setPopoverOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="h-6 w-6 rounded-full border border-dashed border-muted-foreground/30 text-muted-foreground hover:border-primary hover:text-primary"
                            title="Tambah Reaksi"
                        >
                            <IconMoodSmile size={14} />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent side="top" align="start" className="w-auto p-1.5 shadow-md">
                        <div className="flex items-center gap-1">
                            {QUICK_EMOJIS.map((emoji) => (
                                <button
                                    key={emoji}
                                    type="button"
                                    onClick={() => handleToggle(emoji)}
                                    className="flex h-8 w-8 cursor-pointer items-center justify-center rounded-md text-base transition-transform hover:scale-125 hover:bg-muted"
                                >
                                    {emoji}
                                </button>
                            ))}
                        </div>
                    </PopoverContent>
                </Popover>
            </div>
        </TooltipProvider>
    );
}
