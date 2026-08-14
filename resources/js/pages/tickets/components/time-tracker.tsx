import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/react';
import { IconPlayerPause, IconPlayerPlay } from '@tabler/icons-react';
import { Clock, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface TimeLog {
    id: number;
    start_time: string;
}

interface TimeTrackerProps {
    ticketId: number;
    activeLog: TimeLog | null;
}

export function TimeTracker({ ticketId, activeLog }: TimeTrackerProps) {
    const [elapsed, setElapsed] = useState<string>('00:00:00');
    const [loading, setLoading] = useState(false);
    const [showStopDialog, setShowStopDialog] = useState(false);
    const [note, setNote] = useState('');

    useEffect(() => {
        let interval: ReturnType<typeof setInterval>;

        if (activeLog) {
            const startTime = new Date(activeLog.start_time).getTime();

            const updateTimer = () => {
                const now = new Date().getTime();
                const diff = Math.max(0, now - startTime);

                const hours = Math.floor(diff / 3600000);
                const minutes = Math.floor((diff % 3600000) / 60000);
                const seconds = Math.floor((diff % 60000) / 1000);

                setElapsed(
                    `${hours.toString().padStart(2, '0')}:${minutes
                        .toString()
                        .padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
                );
            };

            updateTimer();
            interval = setInterval(updateTimer, 1000);
        } else {
            setElapsed('00:00:00');
        }

        return () => clearInterval(interval);
    }, [activeLog]);

    const handleStart = () => {
        setLoading(true);
        router.post(
            `/tickets/${ticketId}/timer/start`,
            {},
            {
                onFinish: () => setLoading(false),
            }
        );
    };

    const handleStop = () => {
        setLoading(true);
        router.post(
            `/tickets/${ticketId}/timer/stop`,
            { note },
            {
                onSuccess: () => {
                    setShowStopDialog(false);
                    setNote('');
                },
                onFinish: () => setLoading(false),
            }
        );
    };

    return (
        <div className="flex items-center gap-3 rounded-md border bg-slate-50 p-2 dark:bg-slate-900/50">
            <div className="flex items-center gap-2 px-1">
                <Clock className={`h-4 w-4 ${activeLog ? 'animate-pulse text-primary' : 'text-muted-foreground'}`} />
                <span className="font-mono text-sm font-medium">{elapsed}</span>
            </div>

            {activeLog ? (
                <Button
                    size="sm"
                    variant="destructive"
                    className="h-8 px-2"
                    onClick={() => setShowStopDialog(true)}
                    disabled={loading}
                >
                    {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <IconPlayerPause className="h-4 w-4" />}
                    <span className="ml-1 hidden sm:inline">Berhenti</span>
                </Button>
            ) : (
                <Button
                    size="sm"
                    variant="outline"
                    className="h-8 px-2"
                    onClick={handleStart}
                    disabled={loading}
                >
                    {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <IconPlayerPlay className="h-4 w-4" />}
                    <span className="ml-1 hidden sm:inline">Mulai</span>
                </Button>
            )}

            <Dialog open={showStopDialog} onOpenChange={setShowStopDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hentikan Timer</DialogTitle>
                        <DialogDescription>
                            Durasi sesi: <span className="font-mono font-bold text-foreground">{elapsed}</span>
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="note">Apa yang sudah dikerjakan? (Opsional)</Label>
                            <Input
                                id="note"
                                placeholder="Contoh: Perbaiki CSS, Refaktor API..."
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowStopDialog(false)}>
                            Batal
                        </Button>
                        <Button onClick={handleStop} disabled={loading}>
                            {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Simpan Sesi Kerja
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
