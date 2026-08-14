import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { IconX } from '@tabler/icons-react';

export interface Mood {
    value: string;
    emoji: string;
    label: string;
}

interface MoodPickerProps {
    moods: Mood[];
    value: string | null;
    onChange: (value: string | null) => void;
}

/**
 * Compact dropdown mood picker. Each option shows the emoji + label inline,
 * and a small "x" button next to the trigger clears the selection.
 */
export function MoodPicker({ moods, value, onChange }: MoodPickerProps) {
    return (
        <div className="space-y-2">
            <Label htmlFor="mood-select">Mood Hari Ini</Label>

            <div className="flex items-center gap-2">
                <Select
                    value={value ?? undefined}
                    onValueChange={(v) => onChange(v || null)}
                >
                    <SelectTrigger id="mood-select" className="flex-1">
                        <SelectValue placeholder="Pilih mood…" />
                    </SelectTrigger>
                    <SelectContent>
                        {moods.map((m) => (
                            <SelectItem key={m.value} value={m.value}>
                                <span className="mr-2 text-base leading-none">{m.emoji}</span>
                                {m.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {value && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-9 w-9 shrink-0 text-muted-foreground hover:text-destructive"
                        onClick={() => onChange(null)}
                        title="Hapus pilihan mood"
                    >
                        <IconX size={16} />
                    </Button>
                )}
            </div>
        </div>
    );
}
