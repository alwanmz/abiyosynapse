import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { CalendarDays, Loader2, RefreshCw, Sparkles } from 'lucide-react';
import { useState } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { csrfHeaders } from '@/lib/ai';
import { humanizeAiError, MARKDOWN_COMPONENTS } from './ai-shared';

interface DigestStats {
    window: { start: string; end: string; days: number };
    totals: {
        logs: number;
        unique_authors: number;
        total_minutes: number;
        avg_energy_level: number | null;
    };
}

interface DigestResponse {
    ok: boolean;
    digest?: string;
    stats?: DigestStats;
    message?: string;
    generated_at?: string;
}

/**
 * AI weekly digest of Daily Logs (last 7 days).
 *
 * Shows a quick stats strip (entries, authors, total minutes, avg energy)
 * plus a Gemini-generated narrative the PM can paste into a status email.
 * Only the loading + error UX differs from <AIInsights>; the markdown
 * styling is intentionally identical so both AI cards feel cohesive.
 */
export function WeeklyDigest() {
    const [digest, setDigest] = useState<string | null>(null);
    const [stats, setStats] = useState<DigestStats | null>(null);
    const [generatedAt, setGeneratedAt] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const generate = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await fetch('/analytics/weekly-digest', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    ...csrfHeaders(),
                },
            });

            const data = (await response.json()) as DigestResponse;
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'Gagal menyusun digest mingguan.');
            }

            setDigest(data.digest ?? null);
            setStats(data.stats ?? null);
            setGeneratedAt(data.generated_at ?? null);
        } catch (err) {
            setError(humanizeAiError((err as Error)?.message));
        } finally {
            setLoading(false);
        }
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
                <div className="space-y-1">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <CalendarDays className="h-4 w-4 text-primary" />
                        Digest Mingguan — Catatan Harian
                    </CardTitle>
                    <CardDescription>
                        {generatedAt
                            ? `Diperbarui ${new Date(generatedAt).toLocaleString('id-ID')}`
                            : 'Ringkasan mood, energi, dan aktivitas tim 7 hari terakhir'}
                    </CardDescription>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={generate}
                    disabled={loading}
                    className="h-8 shadow-sm"
                >
                    {loading ? (
                        <>
                            <Loader2 className="mr-2 h-3.5 w-3.5 animate-spin" />
                            Menyusun...
                        </>
                    ) : (
                        <>
                            <RefreshCw className="mr-2 h-3.5 w-3.5" />
                            {digest ? 'Generate Ulang' : 'Generate Digest'}
                        </>
                    )}
                </Button>
            </CardHeader>

            {!digest && !error && !loading && (
                <CardContent className="py-10 text-center text-muted-foreground">
                    <div className="flex flex-col items-center justify-center gap-2">
                        <Sparkles className="h-8 w-8 text-muted-foreground/30" />
                        <p className="text-sm">
                            Klik untuk menyusun digest dari Catatan Harian tim.
                        </p>
                    </div>
                </CardContent>
            )}

            {(digest || error) && (
                <CardContent className="space-y-4">
                    {error ? (
                        <div className="rounded-md border border-destructive/20 bg-destructive/10 p-4 text-sm text-destructive">
                            {error}
                        </div>
                    ) : (
                        <>
                            {stats && (
                                <div className="grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                                    <StatPill
                                        label="Total catatan"
                                        value={stats.totals.logs.toLocaleString('id-ID')}
                                    />
                                    <StatPill
                                        label="Kontributor"
                                        value={stats.totals.unique_authors.toLocaleString('id-ID')}
                                    />
                                    <StatPill
                                        label="Menit dicatat"
                                        value={stats.totals.total_minutes.toLocaleString('id-ID')}
                                    />
                                    <StatPill
                                        label="Rata-rata energi"
                                        value={
                                            stats.totals.avg_energy_level !== null
                                                ? `${stats.totals.avg_energy_level}/10`
                                                : '—'
                                        }
                                    />
                                </div>
                            )}

                            {stats && (
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <Badge variant="secondary" className="font-mono">
                                        {stats.window.start} → {stats.window.end}
                                    </Badge>
                                    <span>{stats.window.days} hari</span>
                                </div>
                            )}

                            <div className="prose prose-sm dark:prose-invert max-w-none text-muted-foreground">
                                <ReactMarkdown
                                    remarkPlugins={[remarkGfm]}
                                    components={MARKDOWN_COMPONENTS}
                                >
                                    {digest || ''}
                                </ReactMarkdown>
                            </div>
                        </>
                    )}
                </CardContent>
            )}
        </Card>
    );
}

function StatPill({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border bg-muted/30 px-3 py-2">
            <div className="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">
                {label}
            </div>
            <div className="mt-0.5 text-sm font-semibold text-foreground">
                {value}
            </div>
        </div>
    );
}
