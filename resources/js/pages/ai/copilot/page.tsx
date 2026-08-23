import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { VoiceRecorderButton } from '@/components/voice-recorder-button';
import { useBreadcrumbs } from '@/hooks/use-breadcrumbs';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Check, LoaderCircle, Send, ShieldCheck, Sparkles, X } from 'lucide-react';
import { type FormEvent, type ReactElement, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface CopilotTool {
    key: string;
    mode: 'read' | 'draft';
}

interface CopilotResponse {
    status: 'answered' | 'confirmation_required' | 'executed' | 'rejected';
    tool?: string | null;
    reply: string;
    data?: unknown;
    action_run_id?: number;
    arguments?: Record<string, unknown>;
}

interface ChatMessage {
    id: number;
    role: 'user' | 'assistant';
    text: string;
    data?: unknown;
    isError?: boolean;
}

interface PendingAction {
    id: number;
    tool: string;
    reply: string;
    arguments: Record<string, unknown>;
}

interface PageProps {
    configured: boolean;
    tools: CopilotTool[];
}

const examples = ['insight', 'inventory', 'mrp', 'draft_pr', 'draft_so', 'draft_mo'] as const;

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

async function postJson<T>(url: string, body?: Record<string, unknown>): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body ?? {}),
    });
    const payload = (await response.json()) as T & { message?: string };

    if (!response.ok) {
        throw new Error(payload.message ?? 'AI request failed.');
    }

    return payload;
}

function pretty(value: unknown): string {
    return JSON.stringify(value, null, 2) ?? '';
}

function AiCopilotPage({ configured, tools }: PageProps) {
    const { t } = useTranslation('ai');
    const [message, setMessage] = useState('');
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [pending, setPending] = useState<PendingAction | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const language = typeof document !== 'undefined' ? document.documentElement.lang || 'id' : 'id';

    useBreadcrumbs([{ title: t('title'), href: '/ai/copilot' }]);

    const appendAssistant = (response: CopilotResponse) => {
        setMessages((current) => [
            ...current,
            {
                id: Date.now() + Math.random(),
                role: 'assistant',
                text: response.reply,
                data: response.data,
            },
        ]);
    };

    const submit = async (event: FormEvent) => {
        event.preventDefault();
        const instruction = message.trim();

        if (!instruction || busy) {
            return;
        }

        setError(null);
        setMessage('');
        setMessages((current) => [
            ...current,
            { id: Date.now(), role: 'user', text: instruction },
        ]);
        setBusy(true);

        try {
            const response = await postJson<CopilotResponse>('/ai/copilot/chat', { message: instruction });
            appendAssistant(response);

            if (response.status === 'confirmation_required' && response.action_run_id) {
                setPending({
                    id: response.action_run_id,
                    tool: response.tool ?? '',
                    reply: response.reply,
                    arguments: response.arguments ?? {},
                });
            }
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : t('error'));
        } finally {
            setBusy(false);
        }
    };

    const confirm = async () => {
        if (!pending || busy) {
            return;
        }

        setError(null);
        setBusy(true);
        try {
            const response = await postJson<CopilotResponse>(`/ai/copilot/actions/${pending.id}/confirm`);
            appendAssistant(response);
            setPending(null);
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : t('error'));
        } finally {
            setBusy(false);
        }
    };

    const reject = async () => {
        if (!pending || busy) {
            return;
        }

        setError(null);
        setBusy(true);
        try {
            const response = await postJson<CopilotResponse>(`/ai/copilot/actions/${pending.id}/reject`);
            appendAssistant(response);
            setPending(null);
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : t('error'));
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Head title={t('title')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 border-b pb-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div className="mb-2 flex items-center gap-2">
                            <Sparkles className="size-5 text-primary" />
                            <Badge variant={configured ? 'default' : 'outline'}>
                                {configured ? t('configured') : t('local_mode')}
                            </Badge>
                        </div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">{t('title')}</h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">{t('description')}</p>
                    </div>
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        <ShieldCheck className="size-4 text-nx-andon-run" />
                        <span>{t('confirm_description')}</span>
                    </div>
                </div>

                <div className="grid min-h-0 flex-1 gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.5fr)]">
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle>{t('tools_title')}</CardTitle>
                            <CardDescription>{t('tools_description')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
                                {tools.map((tool) => (
                                    <div key={tool.key} className="flex min-h-10 items-center justify-between gap-3 border-b py-2 last:border-b-0">
                                        <span className="text-sm">{t(`tools.${tool.key}`)}</span>
                                        <Badge variant={tool.mode === 'draft' ? 'secondary' : 'outline'}>
                                            {tool.mode === 'draft' ? t('mode_draft') : t('mode_read')}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                            <div className="border-t pt-4">
                                <p className="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">{t('examples_title')}</p>
                                <div className="flex flex-wrap gap-2">
                                    {examples.map((example) => (
                                        <Button key={example} type="button" variant="outline" size="sm" onClick={() => setMessage(t(`examples.${example}`))}>
                                            {t(`examples.${example}`)}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="flex min-h-[34rem] flex-col">
                        <CardHeader className="border-b">
                            <CardTitle>{t('conversation_title')}</CardTitle>
                        </CardHeader>
                        <CardContent className="flex min-h-0 flex-1 flex-col gap-4 p-4 md:p-6">
                            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
                                {messages.length === 0 && (
                                    <div className="flex min-h-56 items-center justify-center border border-dashed p-6 text-center text-sm text-muted-foreground">
                                        {t('empty')}
                                    </div>
                                )}
                                {messages.map((item) => (
                                    <div key={item.id} className={item.role === 'user' ? 'ml-auto max-w-[85%]' : 'max-w-[92%]'}>
                                        <p className="mb-1 text-xs font-medium text-muted-foreground">
                                            {item.role === 'user' ? t('you') : t('assistant')}
                                        </p>
                                        <div className={item.isError ? 'border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive' : item.role === 'user' ? 'bg-primary px-3 py-2 text-sm text-primary-foreground' : 'border bg-muted/30 px-3 py-2 text-sm'}>
                                            <p className="whitespace-pre-wrap leading-relaxed">{item.text}</p>
                                            {item.data !== null && item.data !== undefined && (
                                                <details className="mt-3 border-t pt-2">
                                                    <summary className="cursor-pointer text-xs font-medium text-muted-foreground">{t('details')}</summary>
                                                    <pre className="mt-2 max-h-72 overflow-auto whitespace-pre-wrap text-xs leading-relaxed">{pretty(item.data)}</pre>
                                                </details>
                                            )}
                                        </div>
                                    </div>
                                ))}
                                {busy && (
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <LoaderCircle className="size-4 animate-spin" />
                                        {t('thinking')}
                                    </div>
                                )}
                            </div>

                            {pending && (
                                <div className="border border-primary/40 bg-primary/5 p-4">
                                    <div className="flex items-start gap-3">
                                        <ShieldCheck className="mt-0.5 size-5 shrink-0 text-primary" />
                                        <div className="min-w-0 flex-1">
                                            <h3 className="font-medium">{t('confirm_title')}</h3>
                                            <p className="mt-1 text-sm text-muted-foreground">{pending.reply}</p>
                                            <pre className="mt-3 max-h-32 overflow-auto whitespace-pre-wrap border bg-background p-2 text-xs">{pretty(pending.arguments)}</pre>
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                <Button type="button" size="sm" onClick={confirm} disabled={busy}>
                                                    <Check />
                                                    {t('confirm')}
                                                </Button>
                                                <Button type="button" size="sm" variant="cancel" onClick={reject} disabled={busy}>
                                                    <X />
                                                    {t('cancel')}
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {error && <p className="text-sm text-destructive" role="alert">{error}</p>}

                            <form onSubmit={submit} className="flex items-end gap-2 border-t pt-4">
                                <Textarea
                                    value={message}
                                    onChange={(event) => setMessage(event.target.value)}
                                    placeholder={t('input_placeholder')}
                                    rows={2}
                                    className="min-h-14 resize-none"
                                    disabled={busy}
                                />
                                <VoiceRecorderButton language={language} disabled={busy} onTranscript={setMessage} />
                                <Button type="submit" size="icon" aria-label={t('send')} disabled={!message.trim() || busy}>
                                    {busy ? <LoaderCircle className="animate-spin" /> : <Send />}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

AiCopilotPage.layout = (page: ReactElement) => <AppLayout>{page}</AppLayout>;

export default AiCopilotPage;
