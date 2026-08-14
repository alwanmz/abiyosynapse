import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface EnergySliderProps {
    value: number | null;
    onChange: (value: number | null) => void;
}

/**
 * 1..10 horizontal energy slider with a colorized fill (red -> green) and
 * a small numeric badge. Click the "Hapus" link to clear the value.
 */
export function EnergySlider({ value, onChange }: EnergySliderProps) {
    const v = value ?? 5;
    const pct = ((v - 1) / 9) * 100;

    // Color shifts from rose (low energy) through amber to emerald (high energy).
    const fillColor =
        v <= 3 ? '#f43f5e' : v <= 6 ? '#f59e0b' : '#10b981';

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <Label htmlFor="energy_level">Level Energi</Label>
                <div className="flex items-center gap-2">
                    {value !== null && (
                        <span
                            className="rounded-full px-2 py-0.5 text-xs font-bold text-white"
                            style={{ backgroundColor: fillColor }}
                        >
                            {value}/10
                        </span>
                    )}
                    {value !== null ? (
                        <button
                            type="button"
                            className="text-xs text-muted-foreground underline-offset-2 hover:underline"
                            onClick={() => onChange(null)}
                        >
                            Hapus
                        </button>
                    ) : (
                        <span className="text-xs text-muted-foreground">Belum diset</span>
                    )}
                </div>
            </div>

            <div className="relative">
                <input
                    id="energy_level"
                    type="range"
                    min={1}
                    max={10}
                    step={1}
                    value={v}
                    onChange={(e) => onChange(parseInt(e.target.value, 10))}
                    className={cn(
                        'h-2 w-full cursor-pointer appearance-none rounded-full bg-muted',
                        // WebKit thumb is styled via the linear-gradient track below.
                        '[&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:w-4',
                        '[&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full',
                        '[&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-white',
                        '[&::-webkit-slider-thumb]:shadow-md',
                        '[&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:w-4',
                        '[&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-white',
                    )}
                    style={{
                        background: `linear-gradient(to right, ${fillColor} 0%, ${fillColor} ${pct}%, #e5e7eb ${pct}%, #e5e7eb 100%)`,
                    }}
                />
                <div className="mt-1 flex justify-between text-[10px] text-muted-foreground">
                    <span>Rendah</span>
                    <span>Sedang</span>
                    <span>Tinggi</span>
                </div>
            </div>
        </div>
    );
}
