import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { IconInfoCircle } from '@tabler/icons-react';
import { timelineTypeInfo } from '../constants/constants';

export function TimelineTypeInfo() {
    const handleWheel = (e: React.WheelEvent) => {
        e.stopPropagation();
    };

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-5 w-5 rounded-full p-0 hover:bg-accent"
                    type="button"
                >
                    <IconInfoCircle className="h-4 w-4 text-muted-foreground" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-[500px] p-0"
                align="start"
                side="bottom"
                onWheel={handleWheel}
            >
                <div className="max-h-[70vh] space-y-4 overflow-y-scroll p-4">
                    <div>
                        <h4 className="mb-1 text-sm font-semibold">
                            Timeline Types Guide
                        </h4>
                        <p className="text-xs text-muted-foreground">
                            Panduan untuk memilih tipe timeline yang tepat
                        </p>
                    </div>

                    {timelineTypeInfo.map((info, index) => (
                        <div
                            key={info.type}
                            className="space-y-2 border-b pb-4 last:border-b-0 last:pb-0"
                        >
                            <div className="flex items-center gap-2">
                                <div
                                    className={`h-2 w-2 rounded-full ${info.color}`}
                                />
                                <h5 className="text-sm font-semibold">
                                    {info.type}
                                </h5>
                            </div>

                            <p className="text-xs text-muted-foreground">
                                {info.description}
                            </p>

                            <div className="space-y-2 rounded-md bg-muted/50 p-3">
                                <p className="text-xs font-medium">
                                    ✓ Kapan digunakan:
                                </p>
                                <p className="pl-4 text-xs text-muted-foreground">
                                    {info.usage}
                                </p>
                            </div>

                            <div className="space-y-2 rounded-md bg-accent/50 p-3">
                                <p className="text-xs font-medium">
                                    📋 Contoh input:
                                </p>
                                <div className="space-y-1 pl-4 text-xs">
                                    <p>
                                        <span className="font-medium">
                                            Title:
                                        </span>{' '}
                                        {info.example.title}
                                    </p>
                                    <p>
                                        <span className="font-medium">
                                            Phase:
                                        </span>{' '}
                                        {info.example.phase}
                                    </p>
                                    <p>
                                        <span className="font-medium">
                                            Duration:
                                        </span>{' '}
                                        {info.example.duration}
                                    </p>
                                    <div>
                                        <span className="font-medium">
                                            Deliverables:
                                        </span>
                                        <ul className="mt-1 list-inside list-disc pl-2 text-muted-foreground">
                                            {info.example.deliverables.map(
                                                (item, i) => (
                                                    <li key={i}>{item}</li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}

                    <div className="rounded-md border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/20">
                        <p className="text-xs text-blue-800 dark:text-blue-200">
                            <strong>💡 Tips:</strong> Pilih tipe berdasarkan
                            konteks dan skala aktivitas. Sprint untuk iterasi
                            rutin, Phase untuk tahapan besar, Milestone untuk
                            pencapaian kunci, dan Event untuk kegiatan spesifik.
                        </p>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
