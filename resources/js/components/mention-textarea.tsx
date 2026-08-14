import { Textarea } from '@/components/ui/textarea';
import { UserAvatar } from '@/components/user-avatar';
import { useEffect, useRef, useState } from 'react';

export interface MentionUser {
    id: number;
    name: string;
    avatar_path?: string | null;
}

interface MentionTextareaProps
    extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
    value: string;
    onValueChange: (val: string) => void;
    users?: MentionUser[];
}

export function MentionTextarea({
    value,
    onValueChange,
    users = [],
    className = '',
    placeholder = 'Tulis komentar... (ketik @ untuk mention)',
    ...props
}: MentionTextareaProps) {
    const [mentionQuery, setMentionQuery] = useState<string | null>(null);
    const [mentionIndex, setMentionIndex] = useState(0);
    const [dropdownPos, setDropdownPos] = useState({ top: 0, left: 0 });
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    const filteredUsers = mentionQuery !== null
        ? users.filter((u) =>
              u.name.toLowerCase().includes(mentionQuery.toLowerCase())
          )
        : [];

    const handleTextChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
        const newValue = e.target.value;
        const cursorPos = e.target.selectionStart;
        onValueChange(newValue);

        // Check if cursor is right after an '@' or typing a mention
        const textBeforeCursor = newValue.slice(0, cursorPos);
        const match = textBeforeCursor.match(/@([a-zA-Z0-9_\s]{0,25})$/);

        if (match) {
            setMentionQuery(match[1]);
            setMentionIndex(0);
        } else {
            setMentionQuery(null);
        }
    };

    const insertMention = (user: MentionUser) => {
        if (!textareaRef.current) return;
        const cursorPos = textareaRef.current.selectionStart;
        const textBeforeCursor = value.slice(0, cursorPos);
        const textAfterCursor = value.slice(cursorPos);

        const lastAtIndex = textBeforeCursor.lastIndexOf('@');
        if (lastAtIndex !== -1) {
            const newText =
                textBeforeCursor.slice(0, lastAtIndex) +
                `@${user.name} ` +
                textAfterCursor;
            onValueChange(newText);
            setMentionQuery(null);

            // Reset focus to textarea
            setTimeout(() => {
                if (textareaRef.current) {
                    textareaRef.current.focus();
                    const newPos = lastAtIndex + user.name.length + 2;
                    textareaRef.current.setSelectionRange(newPos, newPos);
                }
            }, 50);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
        if (mentionQuery !== null && filteredUsers.length > 0) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setMentionIndex((prev) => (prev + 1) % filteredUsers.length);
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                setMentionIndex((prev) =>
                    prev === 0 ? filteredUsers.length - 1 : prev - 1
                );
                return;
            }
            if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                insertMention(filteredUsers[mentionIndex]);
                return;
            }
            if (e.key === 'Escape') {
                setMentionQuery(null);
                return;
            }
        }
        if (props.onKeyDown) {
            props.onKeyDown(e);
        }
    };

    return (
        <div className="relative w-full">
            <Textarea
                ref={textareaRef}
                value={value}
                onChange={handleTextChange}
                onKeyDown={handleKeyDown}
                placeholder={placeholder}
                className={className}
                {...props}
            />

            {mentionQuery !== null && filteredUsers.length > 0 && (
                <div className="absolute left-2 top-full z-50 mt-1 max-h-48 w-64 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-lg border bg-popover p-1 shadow-lg border-border">
                    <div className="px-2 py-1 text-[10px] font-semibold text-muted-foreground uppercase">
                        Mention Anggota Tim
                    </div>
                    {filteredUsers.map((u, idx) => (
                        <button
                            key={u.id}
                            type="button"
                            onClick={() => insertMention(u)}
                            className={`flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs transition-colors ${
                                idx === mentionIndex
                                    ? 'bg-accent text-accent-foreground font-medium'
                                    : 'hover:bg-muted'
                            }`}
                        >
                            <UserAvatar user={u as any} className="h-5 w-5" />
                            <span className="truncate">{u.name}</span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
