import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import type { GuidebookChecklistItem } from '@/types/guidebook';
import { RotateCcw } from 'lucide-react';
import { useCallback, useState } from 'react';

/**
 * Interactive step checklist. Progress is per-browser (localStorage) — the
 * checklist is a working aid, not a tracked submission, so it deliberately
 * does not hit the server.
 */
export function ChecklistViewer({
    guidebookId,
    items,
}: {
    guidebookId: number;
    items: GuidebookChecklistItem[];
}) {
    const storageKey = `guidebook:${guidebookId}:checklist`;

    // Progres tersimpan dibaca sekali saat inisialisasi; bila jumlah langkah
    // berubah (guidebook diedit) data lama diabaikan agar tidak salah pasang.
    const [checked, setChecked] = useState<boolean[]>(() => {
        const blank = items.map(() => false);

        try {
            const saved = window.localStorage.getItem(storageKey);
            if (!saved) return blank;

            const parsed = JSON.parse(saved);
            return Array.isArray(parsed) && parsed.length === items.length
                ? parsed.map(Boolean)
                : blank;
        } catch {
            // Penyimpanan lokal tidak tersedia / rusak — mulai dari kosong.
            return blank;
        }
    });

    const persist = useCallback(
        (next: boolean[]) => {
            setChecked(next);
            try {
                window.localStorage.setItem(storageKey, JSON.stringify(next));
            } catch {
                // Abaikan bila localStorage penuh atau diblokir.
            }
        },
        [storageKey],
    );

    const doneCount = checked.filter(Boolean).length;
    const percent = items.length ? Math.round((doneCount / items.length) * 100) : 0;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex flex-col gap-2 rounded-md border bg-muted/40 p-3">
                <div className="flex items-center justify-between text-sm">
                    <span className="font-medium">
                        {doneCount} dari {items.length} langkah selesai
                    </span>
                    <span className="text-muted-foreground">{percent}%</span>
                </div>
                <Progress value={percent} className="h-2" />
                {doneCount > 0 && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="w-fit px-2"
                        onClick={() => persist(items.map(() => false))}
                    >
                        <RotateCcw className="mr-1.5 h-3.5 w-3.5" />
                        Reset progres
                    </Button>
                )}
            </div>

            <ol className="flex flex-col gap-2">
                {items.map((item, index) => (
                    <li
                        key={index}
                        className={cn(
                            'flex items-start gap-3 rounded-md border p-3 transition-colors',
                            checked[index] && 'bg-muted/50',
                        )}
                    >
                        <Checkbox
                            id={`step-${index}`}
                            checked={checked[index] ?? false}
                            onCheckedChange={(value) =>
                                persist(
                                    checked.map((state, i) => (i === index ? value === true : state)),
                                )
                            }
                            className="mt-0.5"
                        />
                        <div className="grid gap-0.5">
                            <label
                                htmlFor={`step-${index}`}
                                className={cn(
                                    'cursor-pointer text-sm font-medium leading-snug',
                                    checked[index] && 'text-muted-foreground line-through',
                                )}
                            >
                                {index + 1}. {item.text}
                            </label>
                            {item.note && (
                                <p className="text-xs text-muted-foreground">{item.note}</p>
                            )}
                        </div>
                    </li>
                ))}
            </ol>
        </div>
    );
}
