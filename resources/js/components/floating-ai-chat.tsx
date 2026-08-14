import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { ai, type AiChatMessage } from '@/lib/ai';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { IconCheck, IconCopy, IconLoader2, IconMessageCircle, IconMicrophone, IconPlayerStopFilled, IconRobot, IconSend, IconSparkles, IconX } from '@tabler/icons-react';
import { type FormEvent, type KeyboardEvent, useCallback, useEffect, useRef, useState } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { useVoiceRecorder } from '@/pages/daily-logs/hooks/use-voice-recorder';

type ChatMessage = AiChatMessage & { id: string };

const welcome: ChatMessage = {
    id: 'welcome',
    role: 'assistant',
    content: 'Siap, om. Aku baca konteks modul PM: klien, tim, user, proyek, timeline, tiket, daily log, dan aktivitas terbaru.',
};

function makeMessage(role: AiChatMessage['role'], content: string): ChatMessage {
    return {
        id: `${role}-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        role,
        content,
    };
}

function humanizeAiError(raw?: string): string {
    if (!raw) return 'Maaf, ada gangguan sementara. Coba ulang sebentar ya.';

    const lower = raw.toLowerCase();
    if (lower.includes('api key') || lower.includes('belum di-set')) {
        return 'AI belum aktif di server ini. Hubungi admin untuk mengaktifkannya.';
    }

    if (lower.includes('tokens') || lower.includes('too large') || lower.includes('request too large')) {
        return 'Konteks terlalu besar untuk dikirim ke AI. Coba lagi sebentar.';
    }

    if (lower.includes('rate') || lower.includes('quota') || lower.includes('429')) {
        return 'AI lagi kelebihan permintaan. Tunggu sebentar lalu coba lagi ya.';
    }

    if (lower.includes('timeout') || lower.includes('timed out') || lower.includes('connection')) {
        return 'Koneksi ke AI timeout. Coba ulangi pertanyaannya.';
    }

    return 'Maaf, ada gangguan teknis sementara. Coba ulangi pertanyaannya.';
}

export function FloatingAiChat() {
    const { ai: aiCfg } = usePage<SharedData>().props;
    const aiEnabled = !!aiCfg?.enabled;
    const [open, setOpen] = useState(false);
    const [renderPanel, setRenderPanel] = useState(false);
    const [input, setInput] = useState('');
    const [messages, setMessages] = useState<ChatMessage[]>([welcome]);
    const [loading, setLoading] = useState(false);
    const [copiedId, setCopiedId] = useState<string | null>(null);
    const scrollRef = useRef<HTMLDivElement>(null);
    const closeTimerRef = useRef<number | null>(null);

    const recorder = useVoiceRecorder({
        onStop: async (blob) => {
            try {
                const text = await ai.transcribe(blob, 'id-ID');
                setInput((prev) => (prev.trim() ? `${prev.trim()} ${text.trim()}` : text.trim()));
            } catch (e: any) {
                setMessages((current) => [...current, makeMessage('assistant', humanizeAiError(e?.message))]);
            }
        },
        maxSeconds: 60,
    });

    const handleVoiceClick = () => {
        if (recorder.status === 'recording') {
            recorder.stop();
        } else {
            void recorder.start();
        }
    };

    const copyMessage = useCallback((id: string, content: string) => {
        void navigator.clipboard.writeText(content).then(() => {
            setCopiedId(id);
            window.setTimeout(() => setCopiedId(null), 1500);
        });
    }, []);

    useEffect(() => {
        if (!open) return;
        scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
    }, [messages, open]);

    useEffect(() => {
        return () => {
            if (closeTimerRef.current) {
                window.clearTimeout(closeTimerRef.current);
            }
        };
    }, []);

    if (!aiEnabled) return null;

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
        if (closeTimerRef.current) {
            window.clearTimeout(closeTimerRef.current);
        }

        closeTimerRef.current = window.setTimeout(() => {
            setRenderPanel(false);
            closeTimerRef.current = null;
        }, 180);
    };

    const toggleChat = () => {
        if (open) {
            closeChat();
        } else {
            openChat();
        }
    };

    const ask = async (question: string) => {
        const clean = question.trim();
        if (!clean || loading) return;

        const userMessage = makeMessage('user', clean);
        const nextMessages = [...messages, userMessage];
        setMessages(nextMessages);
        setInput('');
        setLoading(true);

        try {
            const answer = await ai.chat(
                clean,
                nextMessages
                    .filter((message) => message.id !== 'welcome')
                    .slice(-8)
                    .map(({ role, content }) => ({ role, content })),
            );
            setMessages((current) => [...current, makeMessage('assistant', answer)]);
        } catch (err: any) {
            setMessages((current) => [...current, makeMessage('assistant', humanizeAiError(err?.message))]);
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
        <div className="fixed bottom-5 right-5 z-40">
            {renderPanel && (
                <div
                    className={`mb-3 flex h-[32rem] max-h-[calc(100vh-6rem)] w-[min(calc(100vw-2rem),24rem)] origin-bottom-right flex-col overflow-hidden rounded-lg border bg-background shadow-xl transition-all duration-200 ease-out motion-reduce:transition-none ${
                        open ? 'translate-y-0 scale-100 opacity-100' : 'pointer-events-none translate-y-3 scale-95 opacity-0'
                    }`}
                >
                    <div className="flex items-center justify-between border-b px-4 py-3">
                        <div className="flex min-w-0 items-center gap-2">
                            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary/10 text-primary transition-transform duration-200 ease-out group-hover:scale-105">
                                <IconSparkles className="h-4 w-4" />
                            </div>
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold">AI PM</p>
                                <p className="truncate text-xs text-muted-foreground">Konteks semua modul PM</p>
                            </div>
                        </div>
                        <Button type="button" variant="ghost" size="icon" className="h-8 w-8 transition-all duration-200 ease-out hover:rotate-90" onClick={closeChat} title="Tutup chat">
                            <IconX className="h-4 w-4" />
                        </Button>
                    </div>

                    <div ref={scrollRef} className="flex-1 space-y-3 overflow-y-auto px-4 py-3">
                        {messages.map((message) => (
                            <div key={message.id} className={`flex gap-2 transition-all duration-200 ease-out ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                                {message.role === 'assistant' && (
                                    <div className="mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground transition-transform duration-200 ease-out">
                                        <IconRobot className="h-4 w-4" />
                                    </div>
                                )}
                                <div className={`group relative max-w-[82%] rounded-lg px-3 py-2 text-sm leading-relaxed ${
                                    message.role === 'user'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'border bg-muted/40 text-foreground'
                                }`}>
                                    {message.role === 'user' ? (
                                        <span className="whitespace-pre-wrap">{message.content}</span>
                                    ) : (
                                        <>
                                            <div className="prose prose-sm max-w-none text-foreground
                                                [&_p]:my-1 [&_p:first-child]:mt-0 [&_p:last-child]:mb-0
                                                [&_ul]:my-1 [&_ul]:ml-4 [&_ul]:list-disc
                                                [&_ol]:my-1 [&_ol]:ml-4 [&_ol]:list-decimal
                                                [&_li]:my-0.5
                                                [&_strong]:font-semibold [&_strong]:text-foreground
                                                [&_table]:my-2 [&_table]:w-full [&_table]:text-xs
                                                [&_th]:border [&_th]:px-2 [&_th]:py-1 [&_th]:text-left [&_th]:font-medium
                                                [&_td]:border [&_td]:px-2 [&_td]:py-1
                                                [&_hr]:my-2 [&_hr]:border-border
                                                [&_h1]:text-base [&_h1]:font-bold [&_h1]:mt-2 [&_h1]:mb-1
                                                [&_h2]:text-sm [&_h2]:font-semibold [&_h2]:mt-2 [&_h2]:mb-1
                                                [&_h3]:text-sm [&_h3]:font-semibold [&_h3]:mt-1 [&_h3]:mb-0.5">
                                                <ReactMarkdown remarkPlugins={[remarkGfm]}>
                                                    {message.content}
                                                </ReactMarkdown>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => copyMessage(message.id, message.content)}
                                                className="absolute right-1.5 bottom-1.5 hidden rounded p-0.5 text-muted-foreground/60 transition-colors hover:text-muted-foreground group-hover:flex"
                                                title="Salin pesan"
                                            >
                                                {copiedId === message.id
                                                    ? <IconCheck className="h-3 w-3 text-green-500" />
                                                    : <IconCopy className="h-3 w-3" />
                                                }
                                            </button>
                                        </>
                                    )}
                                </div>
                            </div>
                        ))}
                        {loading && (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <IconLoader2 className="h-4 w-4 animate-spin" />
                                Membaca konteks...
                            </div>
                        )}
                    </div>

                    <form onSubmit={handleSubmit} className="border-t p-3">
                        {recorder.status === 'recording' && (
                            <p className="mb-2 flex items-center gap-1.5 text-xs text-blue-600">
                                <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-rose-500" />
                                Merekam... ({recorder.seconds}s) bicara dalam Bahasa Indonesia.
                            </p>
                        )}
                        {recorder.error && (
                            <p className="mb-2 text-xs text-destructive">{recorder.error}</p>
                        )}
                        <div className="flex items-end gap-2">
                            <Textarea
                                value={input}
                                onChange={(event) => setInput(event.target.value)}
                                onKeyDown={handleKeyDown}
                                placeholder="Tanya klien, proyek, tiket, user..."
                                className="max-h-28 min-h-10 resize-none"
                                disabled={loading || recorder.status === 'processing'}
                            />
                            {recorder.supported && (
                                <Button
                                    type="button"
                                    size="icon"
                                    variant={recorder.status === 'recording' ? 'destructive' : 'outline'}
                                    className="h-10 w-10 shrink-0 transition-all duration-200 ease-out active:scale-95"
                                    onClick={handleVoiceClick}
                                    disabled={loading || recorder.status === 'processing'}
                                    title={recorder.status === 'recording' ? 'Stop rekaman' : 'Rekam pertanyaan pakai suara'}
                                >
                                    {recorder.status === 'processing'
                                        ? <IconLoader2 className="h-4 w-4 animate-spin" />
                                        : recorder.status === 'recording'
                                            ? <IconPlayerStopFilled className="h-4 w-4" />
                                            : <IconMicrophone className="h-4 w-4" />
                                    }
                                </Button>
                            )}
                            <Button type="submit" size="icon" className="h-10 w-10 shrink-0 transition-all duration-200 ease-out active:scale-95" disabled={loading || !input.trim() || recorder.status === 'recording'} title="Kirim">
                                {loading ? <IconLoader2 className="h-4 w-4 animate-spin" /> : <IconSend className="h-4 w-4" />}
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
                title={open ? 'Tutup AI chat' : 'Buka AI chat'}
            >
                <span className="relative h-5 w-5">
                    <IconMessageCircle
                        className={`absolute inset-0 h-5 w-5 transition-all duration-200 ease-out ${
                            open ? 'rotate-90 scale-50 opacity-0' : 'rotate-0 scale-100 opacity-100'
                        }`}
                    />
                    <IconX
                        className={`absolute inset-0 h-5 w-5 transition-all duration-200 ease-out ${
                            open ? 'rotate-0 scale-100 opacity-100' : '-rotate-90 scale-50 opacity-0'
                        }`}
                    />
                </span>
            </Button>
        </div>
    );
}
