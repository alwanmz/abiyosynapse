import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { ai, type AiChatMessage } from '@/lib/ai';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import {
    IconCheck,
    IconCopy,
    IconLoader2,
    IconMessageCircle,
    IconRobot,
    IconSend,
    IconSparkles,
    IconX,
} from '@tabler/icons-react';
import {
    type FormEvent,
    type KeyboardEvent,
    useCallback,
    useEffect,
    useRef,
    useState,
} from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

type ChatMessage = AiChatMessage & { id: string };

const welcome: ChatMessage = {
    id: 'welcome',
    role: 'assistant',
    content:
        'Halo! Saya AI PM yang bisa bantu jawab soal status & progres tiket Anda. Tanyakan saja, misalnya "tiket saya yang belum selesai apa saja?"',
};

function makeMessage(
    role: AiChatMessage['role'],
    content: string,
): ChatMessage {
    return {
        id: `${role}-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        role,
        content,
    };
}

function humanizeAiError(raw?: string): string {
    if (!raw) return 'Maaf, ada gangguan sementara. Coba ulang sebentar ya.';
    const lower = raw.toLowerCase();
    if (lower.includes('kuota') || lower.includes('habis')) return raw;
    if (lower.includes('belum aktif'))
        return 'Asisten AI belum aktif. Hubungi tim kami ya.';
    if (lower.includes('rate') || lower.includes('429'))
        return 'Terlalu cepat mengirim pertanyaan. Tunggu sebentar lalu coba lagi.';
    if (lower.includes('timeout') || lower.includes('connection'))
        return 'Koneksi ke AI timeout. Coba ulangi pertanyaannya.';
    return 'Maaf, ada gangguan teknis sementara. Coba ulangi pertanyaannya.';
}

export function FloatingClientChat() {
    const { portalAi } = usePage<SharedData>().props;
    const enabled = !!portalAi?.enabled;
    const limit = portalAi?.limit ?? 5;

    const [open, setOpen] = useState(false);
    const [renderPanel, setRenderPanel] = useState(false);
    const [input, setInput] = useState('');
    const [messages, setMessages] = useState<ChatMessage[]>([welcome]);
    const [loading, setLoading] = useState(false);
    const [copiedId, setCopiedId] = useState<string | null>(null);
    const [remaining, setRemaining] = useState<number>(
        portalAi?.remaining ?? limit,
    );
    const scrollRef = useRef<HTMLDivElement>(null);
    const closeTimerRef = useRef<number | null>(null);

    const copyMessage = useCallback((id: string, content: string) => {
        void navigator.clipboard.writeText(content).then(() => {
            setCopiedId(id);
            window.setTimeout(() => setCopiedId(null), 1500);
        });
    }, []);

    useEffect(() => {
        if (!open) return;
        scrollRef.current?.scrollTo({
            top: scrollRef.current.scrollHeight,
            behavior: 'smooth',
        });
    }, [messages, open]);

    useEffect(() => {
        return () => {
            if (closeTimerRef.current)
                window.clearTimeout(closeTimerRef.current);
        };
    }, []);

    if (!enabled) return null;

    const outOfQuota = remaining <= 0;

    const openChat = () => {
        if (closeTimerRef.current) {
            window.clearTimeout(closeTimerRef.current);
            closeTimerRef.current = null;
        }
        setRenderPanel(true);
        window.requestAnimationFrame(() => setOpen(true));
    };

    const closeChat = () => {
        setOpen(false);
        if (closeTimerRef.current) window.clearTimeout(closeTimerRef.current);
        closeTimerRef.current = window.setTimeout(() => {
            setRenderPanel(false);
            closeTimerRef.current = null;
        }, 180);
    };

    const toggleChat = () => (open ? closeChat() : openChat());

    const ask = async (question: string) => {
        const clean = question.trim();
        if (!clean || loading || outOfQuota) return;

        const userMessage = makeMessage('user', clean);
        const nextMessages = [...messages, userMessage];
        setMessages(nextMessages);
        setInput('');
        setLoading(true);

        try {
            const { answer, remaining: left } = await ai.portalChat(
                clean,
                nextMessages
                    .filter((m) => m.id !== 'welcome')
                    .slice(-8)
                    .map(({ role, content }) => ({ role, content })),
            );
            setRemaining(left);
            setMessages((current) => [
                ...current,
                makeMessage('assistant', answer),
            ]);
        } catch (err: unknown) {
            const msg = err instanceof Error ? err.message : undefined;
            if (msg && /kuota|habis/i.test(msg)) setRemaining(0);
            setMessages((current) => [
                ...current,
                makeMessage('assistant', humanizeAiError(msg)),
            ]);
        } finally {
            setLoading(false);
        }
    };

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();
        void ask(input);
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            void ask(input);
        }
    };

    return (
        <div className="fixed right-5 bottom-5 z-40">
            {renderPanel && (
                <div
                    className={`mb-3 flex h-[32rem] max-h-[calc(100vh-6rem)] w-[min(calc(100vw-2rem),24rem)] origin-bottom-right flex-col overflow-hidden rounded-lg border bg-background shadow-xl transition-all duration-200 ease-out motion-reduce:transition-none ${open
                        ? 'translate-y-0 scale-100 opacity-100'
                        : 'pointer-events-none translate-y-3 scale-95 opacity-0'
                        }`}
                >
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <div className="flex min-w-0 items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary/10 text-primary">
                                <IconSparkles className="h-4 w-4" />
                            </div>
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold">
                                    AI PM
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    Tanya seputar tiket Anda
                                </p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <span
                                className="rounded-full border bg-muted/50 px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
                                title="Sisa kuota tanya hari ini"
                            >
                                Sisa {remaining}/{limit}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 transition-all duration-200 ease-out hover:rotate-90"
                                onClick={closeChat}
                                title="Tutup chat"
                            >
                                <IconX className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <div
                        ref={scrollRef}
                        className="flex-1 space-y-3 overflow-y-auto px-4 py-3"
                    >
                        {messages.map((message) => (
                            <div
                                key={message.id}
                                className={`flex gap-2 ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
                            >
                                {message.role === 'assistant' && (
                                    <div className="mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                        <IconRobot className="h-4 w-4" />
                                    </div>
                                )}
                                <div
                                    className={`group relative max-w-[82%] rounded-lg px-3 py-2 text-sm leading-relaxed ${message.role === 'user'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'border bg-muted/40 text-foreground'
                                        }`}
                                >
                                    {message.role === 'user' ? (
                                        <span className="whitespace-pre-wrap">
                                            {message.content}
                                        </span>
                                    ) : (
                                        <>
                                            <div className="prose prose-sm max-w-none text-foreground [&_h1]:mt-2 [&_h1]:mb-1 [&_h1]:text-base [&_h1]:font-bold [&_h2]:mt-2 [&_h2]:mb-1 [&_h2]:text-sm [&_h2]:font-semibold [&_h3]:mt-1 [&_h3]:mb-0.5 [&_h3]:text-sm [&_h3]:font-semibold [&_hr]:my-2 [&_hr]:border-border [&_li]:my-0.5 [&_ol]:my-1 [&_ol]:ml-4 [&_ol]:list-decimal [&_p]:my-1 [&_p:first-child]:mt-0 [&_p:last-child]:mb-0 [&_strong]:font-semibold [&_strong]:text-foreground [&_table]:my-2 [&_table]:w-full [&_table]:text-xs [&_td]:border [&_td]:px-2 [&_td]:py-1 [&_th]:border [&_th]:px-2 [&_th]:py-1 [&_th]:text-left [&_th]:font-medium [&_ul]:my-1 [&_ul]:ml-4 [&_ul]:list-disc">
                                                <ReactMarkdown
                                                    remarkPlugins={[remarkGfm]}
                                                >
                                                    {message.content}
                                                </ReactMarkdown>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    copyMessage(
                                                        message.id,
                                                        message.content,
                                                    )
                                                }
                                                className="absolute right-1.5 bottom-1.5 hidden rounded p-0.5 text-muted-foreground/60 transition-colors group-hover:flex hover:text-muted-foreground"
                                                title="Salin pesan"
                                            >
                                                {copiedId === message.id ? (
                                                    <IconCheck className="h-3 w-3 text-green-500" />
                                                ) : (
                                                    <IconCopy className="h-3 w-3" />
                                                )}
                                            </button>
                                        </>
                                    )}
                                </div>
                            </div>
                        ))}
                        {loading && (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <IconLoader2 className="h-4 w-4 animate-spin" />
                                Sedang memeriksa tiket Anda...
                            </div>
                        )}
                    </div>

                    <form onSubmit={handleSubmit} className="border-t p-3">
                        {outOfQuota && (
                            <p className="mb-2 text-xs text-muted-foreground">
                                Kuota tanya hari ini sudah habis. Silakan coba
                                lagi besok ya.
                            </p>
                        )}
                        <div className="flex items-end gap-2">
                            <Textarea
                                value={input}
                                onChange={(event) =>
                                    setInput(event.target.value)
                                }
                                onKeyDown={handleKeyDown}
                                placeholder={
                                    outOfQuota
                                        ? 'Kuota harian habis'
                                        : 'Tanya status tiket, progres, dll...'
                                }
                                className="max-h-28 min-h-10 resize-none"
                                disabled={loading || outOfQuota}
                            />
                            <Button
                                type="submit"
                                size="icon"
                                className="h-10 w-10 shrink-0 transition-all duration-200 ease-out active:scale-95"
                                disabled={
                                    loading || !input.trim() || outOfQuota
                                }
                                title="Kirim"
                            >
                                {loading ? (
                                    <IconLoader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <IconSend className="h-4 w-4" />
                                )}
                            </Button>
                        </div>
                    </form>
                </div>
            )}

            <Button
                type="button"
                size="icon"
                className="h-12 w-12 rounded-full shadow-lg transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-xl active:scale-95"
                onClick={toggleChat}
                title={open ? 'Tutup asisten' : 'Buka asisten tiket'}
            >
                <span className="relative h-5 w-5">
                    <IconMessageCircle
                        className={`absolute inset-0 h-5 w-5 transition-all duration-200 ease-out ${open
                            ? 'scale-50 rotate-90 opacity-0'
                            : 'scale-100 rotate-0 opacity-100'
                            }`}
                    />
                    <IconX
                        className={`absolute inset-0 h-5 w-5 transition-all duration-200 ease-out ${open
                            ? 'scale-100 rotate-0 opacity-100'
                            : 'scale-50 -rotate-90 opacity-0'
                            }`}
                    />
                </span>
            </Button>
        </div>
    );
}
