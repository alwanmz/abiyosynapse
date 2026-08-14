import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { router } from '@inertiajs/react';
import { IconMoodPlus } from '@tabler/icons-react';
import { useState } from 'react';

const COMMON_EMOJIS = ['👍', '❤️', '🎉', '😄', '🚀', '👀', '💡', '🙏'];

export interface ReactionItem {
    emoji: string;
    count: number;
    reacted_by_me: boolean;
}

interface CommentReactionsProps {
    commentId: number;
    reactions?: ReactionItem[];
    reactUrl?: string; // default '/tickets/comments/{id}/react' or '/portal/tickets/comments/{id}/react'
}

export function CommentReactions({
    commentId,
    reactions = [],
    reactUrl,
}: CommentReactionsProps) {
    const [pickerOpen, setPickerOpen] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const targetUrl =
        reactUrl || `/tickets/comments/${commentId}/react`;

    const handleToggle = (emoji: string) => {
        if (isSubmitting) return;
        setIsSubmitting(true);
        setPickerOpen(false);

        router.post(
            targetUrl,
            { emoji },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setIsSubmitting(false),
            }
        );
    };

    return (
        <div className="flex flex-wrap items-center gap-1.5 pt-1">
            {reactions.map((r) => (
                <button
                    key={r.emoji}
                    type="button"
                    onClick={() => handleToggle(r.emoji)}
                    disabled={isSubmitting}
                    className={`inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium transition-all ${
                        r.reacted_by_me
                            ? 'border-primary/50 bg-primary/10 text-primary font-bold shadow-xs'
                            : 'border-border bg-muted/30 text-muted-foreground hover:bg-muted/60'
                    }`}
                >
                    <span>{r.emoji}</span>
                    <span>{r.count}</span>
                </button>
            ))}

            <Popover open={pickerOpen} onOpenChange={setPickerOpen}>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-6 w-6 rounded-full text-muted-foreground hover:text-foreground"
                        title="Tambah reaksi"
                    >
                        <IconMoodPlus className="h-3.5 w-3.5" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    align="start"
                    className="flex flex-wrap max-w-[280px] sm:max-w-none w-auto items-center gap-1 p-1.5 shadow-md"
                >
                    {COMMON_EMOJIS.map((emoji) => (
                        <button
                            key={emoji}
                            type="button"
                            onClick={() => handleToggle(emoji)}
                            className="flex h-7 w-7 items-center justify-center rounded text-base hover:bg-muted hover:scale-125 transition-transform"
                        >
                            {emoji}
                        </button>
                    ))}
                </PopoverContent>
            </Popover>
        </div>
    );
}
