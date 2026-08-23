import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { VoiceRecorderButton } from '@/components/voice-recorder-button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    confirm as confirmAction,
    reject as rejectAction,
    chat as sendChat,
} from '@/routes/ai/copilot';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Check,
    ExternalLink,
    LoaderCircle,
    Send,
    ShieldCheck,
    Sparkles,
    X,
} from 'lucide-react';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

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
}

interface PendingAction {
    id: number;
    reply: string;
    arguments: Record<string, unknown>;
}

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

async function postJson<T>(
    url: string,
    body?: Record<string, unknown>,
): Promise<T> {
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

export function AiCopilotBubble() {
    const { t } = useTranslation('ai');
    const page = usePage<SharedData>();
    const { auth, ai } = page.props;
    const language = typeof document !== 'undefined' ? document.documentElement.lang || 'id' : 'id';
    const inputRef = useRef<HTMLTextAreaElement>(null);
    const [open, setOpen] = useState(false);
    const [message, setMessage] = useState('');
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [pending, setPending] = useState<PendingAction | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const userRole = auth.role?.name;
    const isAdmin = userRole === 'super_admin' || userRole === 'admin';
    const canUseAi =
        isAdmin ||
        auth.role?.permissions?.some(
            (permission) => permission.name === 'ai.use',
        );

    useEffect(() => {
        if (open) {
            inputRef.current?.focus();
        }
    }, [open]);

    if (!canUseAi || page.url.startsWith('/ai/copilot')) {
        return null;
    }

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

        if (!instruction || busy || pending) {
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
            const response = await postJson<CopilotResponse>(sendChat.url(), {
                message: instruction,
            });
            appendAssistant(response);

            if (
                response.status === 'confirmation_required' &&
                response.action_run_id
            ) {
                setPending({
                    id: response.action_run_id,
                    reply: response.reply,
                    arguments: response.arguments ?? {},
                });
            }
        } catch (requestError) {
            setError(
                requestError instanceof Error
                    ? requestError.message
                    : t('error'),
            );
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
            const response = await postJson<CopilotResponse>(
                confirmAction.url(pending.id),
            );
            appendAssistant(response);
            setPending(null);
        } catch (requestError) {
            setError(
                requestError instanceof Error
                    ? requestError.message
                    : t('error'),
            );
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
            const response = await postJson<CopilotResponse>(
                rejectAction.url(pending.id),
            );
            appendAssistant(response);
            setPending(null);
        } catch (requestError) {
            setError(
                requestError instanceof Error
                    ? requestError.message
                    : t('error'),
            );
        } finally {
            setBusy(false);
        }
    };

    const clearConversation = () => {
        if (busy || pending) {
            return;
        }

        setMessages([]);
        setError(null);
    };

    return (
        <div className="fixed right-4 bottom-5 z-40 flex flex-col items-end gap-3 sm:right-6">
            {open && (
                <section
                    className="flex h-[min(38rem,calc(100vh-8rem))] w-[min(25rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-lg border bg-background shadow-[var(--nx-shadow-3)]"
                    aria-label={t('bubble.title')}
                    role="dialog"
                    aria-modal="false"
                >
                    <header className="flex shrink-0 items-center gap-3 border-b bg-primary px-4 py-3 text-primary-foreground">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary-foreground/10">
                            <Sparkles className="size-4" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <h2 className="truncate text-sm font-semibold">
                                {t('bubble.title')}
                            </h2>
                            <div className="mt-0.5 flex items-center gap-2 text-[11px] text-primary-foreground/75">
                                <span>{t('bubble.subtitle')}</span>
                                <span aria-hidden="true">·</span>
                                <span>
                                    {ai.enabled
                                        ? t('configured')
                                        : t('local_mode')}
                                </span>
                            </div>
                        </div>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    className="size-8 shrink-0 text-primary-foreground hover:bg-primary-foreground/10 hover:text-primary-foreground"
                                    onClick={() => setOpen(false)}
                                    aria-label={t('bubble.close')}
                                >
                                    <X />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="left">
                                {t('bubble.close')}
                            </TooltipContent>
                        </Tooltip>
                    </header>

                    <div className="flex min-h-0 flex-1 flex-col gap-3 p-3">
                        <div className="min-h-0 flex-1 space-y-3 overflow-y-auto pr-1">
                            {messages.length === 0 && (
                                <div className="flex min-h-36 items-center justify-center border border-dashed p-5 text-center text-xs leading-relaxed text-muted-foreground">
                                    {t('bubble.empty')}
                                </div>
                            )}
                            {messages.map((item) => (
                                <div
                                    key={item.id}
                                    className={
                                        item.role === 'user'
                                            ? 'ml-auto max-w-[88%]'
                                            : 'max-w-[94%]'
                                    }
                                >
                                    <p className="mb-1 text-[11px] font-medium text-muted-foreground">
                                        {item.role === 'user'
                                            ? t('you')
                                            : t('assistant')}
                                    </p>
                                    <div
                                        className={
                                            item.role === 'user'
                                                ? 'bg-primary px-3 py-2 text-xs leading-relaxed text-primary-foreground'
                                                : 'border bg-muted/30 px-3 py-2 text-xs leading-relaxed'
                                        }
                                    >
                                        <p className="whitespace-pre-wrap">
                                            {item.text}
                                        </p>
                                        {item.data !== null &&
                                            item.data !== undefined && (
                                                <details className="mt-2 border-t pt-2">
                                                    <summary className="cursor-pointer text-[11px] font-medium text-muted-foreground">
                                                        {t('details')}
                                                    </summary>
                                                    <pre className="mt-2 max-h-48 overflow-auto text-[10px] leading-relaxed whitespace-pre-wrap">
                                                        {pretty(item.data)}
                                                    </pre>
                                                </details>
                                            )}
                                    </div>
                                </div>
                            ))}
                            {busy && (
                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                    <LoaderCircle className="size-3.5 animate-spin" />
                                    {t('thinking')}
                                </div>
                            )}
                        </div>

                        {pending && (
                            <div className="shrink-0 border border-primary/40 bg-primary/5 p-3">
                                <div className="flex items-start gap-2">
                                    <ShieldCheck className="mt-0.5 size-4 shrink-0 text-primary" />
                                    <div className="min-w-0 flex-1">
                                        <h3 className="text-xs font-semibold">
                                            {t('confirm_title')}
                                        </h3>
                                        <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
                                            {pending.reply}
                                        </p>
                                        <pre className="mt-2 max-h-20 overflow-auto border bg-background p-2 text-[10px] whitespace-pre-wrap">
                                            {pretty(pending.arguments)}
                                        </pre>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                className="h-8 text-xs"
                                                onClick={confirm}
                                                disabled={busy}
                                            >
                                                <Check />
                                                {t('confirm')}
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="cancel"
                                                className="h-8 text-xs"
                                                onClick={reject}
                                                disabled={busy}
                                            >
                                                <X />
                                                {t('cancel')}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {error && (
                            <p
                                className="shrink-0 text-xs text-destructive"
                                role="alert"
                            >
                                {error}
                            </p>
                        )}

                        <form
                            onSubmit={submit}
                            className="flex shrink-0 items-end gap-2 border-t pt-3"
                        >
                            <Textarea
                                ref={inputRef}
                                value={message}
                                onChange={(event) =>
                                    setMessage(event.target.value)
                                }
                                placeholder={t('bubble.input_placeholder')}
                                rows={2}
                                className="min-h-12 resize-none text-xs"
                                disabled={busy || Boolean(pending)}
                            />
                            <VoiceRecorderButton language={language} disabled={busy || Boolean(pending)} onTranscript={setMessage} />
                            <Button
                                type="submit"
                                size="icon"
                                className="size-9 shrink-0"
                                aria-label={t('send')}
                                disabled={
                                    !message.trim() || busy || Boolean(pending)
                                }
                            >
                                {busy ? (
                                    <LoaderCircle className="animate-spin" />
                                ) : (
                                    <Send />
                                )}
                            </Button>
                        </form>

                        <div className="flex shrink-0 items-center justify-between gap-2 text-[11px] text-muted-foreground">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-7 px-2 text-[11px]"
                                onClick={clearConversation}
                                disabled={busy || Boolean(pending)}
                            >
                                {t('bubble.clear')}
                            </Button>
                            <Link
                                href="/ai/copilot"
                                className="inline-flex items-center gap-1 px-2 py-1 hover:text-foreground"
                            >
                                {t('bubble.open_full')}
                                <ExternalLink className="size-3" />
                            </Link>
                        </div>
                    </div>
                </section>
            )}

            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        size="icon"
                        className="size-12 rounded-full bg-primary shadow-[var(--nx-shadow-3)] hover:bg-primary/90"
                        onClick={() => setOpen((current) => !current)}
                        aria-label={open ? t('bubble.close') : t('bubble.open')}
                        aria-expanded={open}
                    >
                        {open ? (
                            <X className="size-5" />
                        ) : (
                            <Sparkles className="size-5" />
                        )}
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="left">
                    {open ? t('bubble.close') : t('bubble.open')}
                </TooltipContent>
            </Tooltip>
        </div>
    );
}
