import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { IconX } from '@tabler/icons-react';
import { useState } from 'react';

interface TagInputProps {
    value: string[];
    onChange: (value: string[]) => void;
    suggestions?: string[];
    max?: number;
}

/**
 * Chip-style tag input. Press Enter, comma, or space to commit.
 * Backspace on empty input removes the last chip.
 */
export function TagInput({ value, onChange, suggestions = [], max = 10 }: TagInputProps) {
    const [draft, setDraft] = useState('');

    const commit = () => {
        const t = draft.trim().replace(/^#/, '').toLowerCase();
        if (!t) return;
        if (value.includes(t)) {
            setDraft('');
            return;
        }
        if (value.length >= max) {
            setDraft('');
            return;
        }
        onChange([...value, t]);
        setDraft('');
    };

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <Label htmlFor="tags-input">Tag</Label>
                <span className="text-[10px] text-muted-foreground">
                    {value.length}/{max} tag
                </span>
            </div>

            <div className="flex flex-wrap items-center gap-1.5 rounded-md border bg-background p-2 focus-within:ring-2 focus-within:ring-blue-500/40">
                {value.map((tag) => (
                    <span
                        key={tag}
                        className="group inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-200"
                    >
                        #{tag}
                        <button
                            type="button"
                            className="rounded-full p-0.5 text-blue-500 hover:bg-blue-200 dark:hover:bg-blue-900"
                            onClick={() => onChange(value.filter((t) => t !== tag))}
                        >
                            <IconX size={12} />
                        </button>
                    </span>
                ))}
                <Input
                    id="tags-input"
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ',' || (e.key === ' ' && draft.trim())) {
                            e.preventDefault();
                            commit();
                        } else if (e.key === 'Backspace' && draft === '' && value.length > 0) {
                            onChange(value.slice(0, -1));
                        }
                    }}
                    onBlur={commit}
                    placeholder={value.length === 0 ? 'Mis. urgent, refactor, client-skd' : ''}
                    className="h-7 flex-1 border-0 px-1 text-sm shadow-none focus-visible:ring-0"
                />
            </div>

            {suggestions.length > 0 && value.length < max && (
                <div className="flex flex-wrap gap-1">
                    {suggestions
                        .filter((s) => !value.includes(s))
                        .slice(0, 8)
                        .map((s) => (
                            <button
                                key={s}
                                type="button"
                                className="rounded-full border bg-muted/40 px-2 py-0.5 text-[10px] text-muted-foreground hover:border-blue-400 hover:text-blue-700"
                                onClick={() => {
                                    if (value.length < max) onChange([...value, s]);
                                }}
                            >
                                + {s}
                            </button>
                        ))}
                </div>
            )}
        </div>
    );
}
