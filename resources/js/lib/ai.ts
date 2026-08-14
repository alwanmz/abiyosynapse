/**
 * Tiny client wrapper around the server-side /ai/* endpoints
 * (DeepSeek for text, Groq for voice transcription).
 *
 * Modules should import from here instead of fetching the URLs directly so
 * we can swap the provider, add caching, or add error toasts in one place.
 *
 * All endpoints are auth-only and the API key never leaves the server.
 */

import type { TicketDraftData } from '@/types/ticket';

interface AiOk<T> {
    ok: true;
    [k: string]: unknown;
}
interface AiErr {
    ok: false;
    message: string;
}
type AiResponse<T> = (AiOk<T> & T) | AiErr;

export type AiChatMessage = {
    role: 'user' | 'assistant';
    content: string;
};

export function csrfHeaders(): Record<string, string> {
    // XSRF-TOKEN cookie is refreshed by Laravel on every response, so it's always
    // in sync with the session — more reliable than the meta tag in an Inertia SPA.
    const cookie = document.cookie
        .split('; ')
        .find((c) => c.startsWith('XSRF-TOKEN='))
        ?.split('=')
        .slice(1)
        .join('=');
    if (cookie) return { 'X-XSRF-TOKEN': decodeURIComponent(cookie) };

    const meta =
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') || '';
    return { 'X-CSRF-TOKEN': meta };
}

async function postJson<T>(url: string, body: unknown): Promise<T> {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
            ...csrfHeaders(),
        },
        body: JSON.stringify(body),
    });

    const json = (await res.json()) as AiResponse<T>;
    if (!json.ok) throw new Error((json as AiErr).message || 'AI error');
    return json as T;
}

async function postForm<T>(url: string, form: FormData): Promise<T> {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
            ...csrfHeaders(),
        },
        body: form,
    });

    const json = (await res.json()) as AiResponse<T>;
    if (!json.ok) throw new Error((json as AiErr).message || 'AI error');
    return json as T;
}

export const ai = {
    /** Transcribe a recorded audio Blob via Groq Whisper. */
    async transcribe(audio: Blob, language = 'id-ID'): Promise<string> {
        const fd = new FormData();
        const ext = audio.type.includes('ogg')
            ? 'ogg'
            : audio.type.includes('mp4')
              ? 'm4a'
              : 'webm';
        fd.append('audio', audio, `recording.${ext}`);
        fd.append('language', language);
        const r = await postForm<{ text: string }>('/ai/transcribe', fd);
        return r.text;
    },

    /** Polish raw notes into clean professional prose. */
    async polish(text: string, context?: string): Promise<string> {
        const r = await postJson<{ text: string }>('/ai/polish', {
            text,
            context,
        });
        return r.text;
    },

    /** Summarize a longer block of text. */
    async summarize(text: string, maxSentences = 3): Promise<string> {
        const r = await postJson<{ text: string }>('/ai/summarize', {
            text,
            max_sentences: maxSentences,
        });
        return r.text;
    },

    /** Ask the floating AI PM chat using current app context. */
    async chat(
        question: string,
        history: AiChatMessage[] = [],
    ): Promise<string> {
        const r = await postJson<{ answer: string; generated_at: string }>(
            '/ai/chat-context',
            {
                question,
                history,
            },
        );
        return r.answer;
    },

    /**
     * Client-portal chat, scoped to the logged-in client's own tickets.
     * Returns the answer plus remaining daily quota.
     */
    async portalChat(
        question: string,
        history: AiChatMessage[] = [],
    ): Promise<{ answer: string; remaining: number }> {
        const r = await postJson<{
            answer: string;
            remaining: number;
            generated_at: string;
        }>('/portal/ai/chat', { question, history });
        return { answer: r.answer, remaining: r.remaining };
    },

    /** Turn a transcript into a safe ticket draft for user review. */
    async draftTicket(
        transcript: string,
        selectedProjectId?: number | null,
        selectedClientId?: number | null,
    ): Promise<TicketDraftData> {
        const r = await postJson<{ draft: TicketDraftData }>(
            '/ai/draft-ticket',
            {
                transcript,
                selected_project_id: selectedProjectId || null,
                selected_client_id: selectedClientId || null,
            },
        );
        return r.draft;
    },

    /** Extract decisions and action items from a meeting transcript or summary. */
    async extractDecisions(
        text: string,
    ): Promise<
        Array<{
            text: string;
            owner_name: string | null;
            due_date: string | null;
        }>
    > {
        const r = await postJson<{
            decisions: Array<{
                text: string;
                owner_name: string | null;
                due_date: string | null;
            }>;
        }>('/ai/extract-decisions', { text });
        return r.decisions;
    },

    /**
     * Draft a maintenance report summary + work items from tickets marked
     * "done" for the given client/project within the period.
     */
    async draftMaintenanceReport(
        clientId: number,
        projectId: number | null,
        periodStart: string,
        periodEnd: string,
    ): Promise<{
        summary: string;
        items: Array<{
            ticket_id: number;
            category: string;
            found_at: string | null;
            description: string;
            resolution: string;
            status_result: string;
            resolved_at: string | null;
        }>;
    }> {
        const r = await postJson<{
            draft: {
                summary: string;
                items: Array<{
                    ticket_id: number;
                    category: string;
                    found_at: string | null;
                    description: string;
                    resolution: string;
                    status_result: string;
                    resolved_at: string | null;
                }>;
            };
        }>('/ai/maintenance-report-draft', {
            client_id: clientId,
            project_id: projectId,
            period_start: periodStart,
            period_end: periodEnd,
        });
        return r.draft;
    },

    /** Suggest a category slug from `allowed`. May return null. */
    async suggestCategory(
        description: string,
        allowed: string[],
    ): Promise<string | null> {
        const r = await postJson<{ category: string | null }>(
            '/ai/suggest-category',
            { description, allowed },
        );
        return r.category;
    },
};
