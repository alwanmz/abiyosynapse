import type { Components } from 'react-markdown';

/**
 * Shared markdown styling for AI-generated content cards on the
 * Analytics page. Keep the styling in one place so the AI Insights
 * card and the Weekly Digest card always look identical.
 */
export const MARKDOWN_COMPONENTS: Components = {
    h1: ({ node: _node, ...props }) => (
        <h1
            className="mt-6 mb-4 text-xl font-bold tracking-tight text-foreground"
            {...props}
        />
    ),
    h2: ({ node: _node, ...props }) => (
        <h2
            className="mt-6 mb-3 flex items-center gap-2 text-lg font-semibold tracking-tight text-foreground"
            {...props}
        />
    ),
    h3: ({ node: _node, ...props }) => (
        <h3
            className="mt-4 mb-2 text-base font-semibold text-foreground"
            {...props}
        />
    ),
    p: ({ node: _node, ...props }) => (
        <p className="leading-7 [&:not(:first-child)]:mt-4" {...props} />
    ),
    ul: ({ node: _node, ...props }) => (
        <ul className="my-4 ml-6 list-disc [&>li]:mt-2" {...props} />
    ),
    li: ({ node: _node, ...props }) => <li {...props} />,
    strong: ({ node: _node, ...props }) => (
        <strong className="font-semibold text-foreground" {...props} />
    ),
    table: ({ node: _node, ...props }) => (
        <div className="my-6 w-full overflow-y-auto rounded-md border">
            <table className="w-full text-sm" {...props} />
        </div>
    ),
    thead: ({ node: _node, ...props }) => (
        <thead className="bg-muted/50 [&_tr]:border-b" {...props} />
    ),
    tr: ({ node: _node, ...props }) => (
        <tr
            className="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted"
            {...props}
        />
    ),
    th: ({ node: _node, ...props }) => (
        <th
            className="h-10 px-4 text-left align-middle font-medium text-muted-foreground [&:has([role=checkbox])]:pr-0"
            {...props}
        />
    ),
    td: ({ node: _node, ...props }) => (
        <td
            className="p-4 align-middle [&:has([role=checkbox])]:pr-0"
            {...props}
        />
    ),
    hr: ({ node: _node, ...props }) => (
        <hr className="my-6 border-border" {...props} />
    ),
};

/**
 * Map raw error messages from /ai/* and /analytics/* endpoints into
 * friendly Indonesian copy. Catches the common 429/quota case so users
 * don't see provider stack traces.
 */
export function humanizeAiError(raw?: string): string {
    if (!raw) {
        return 'AI tidak bisa memproses permintaan ini. Coba lagi sebentar.';
    }
    const m = raw.toLowerCase();
    if (
        m.includes('quota') ||
        m.includes('exceeded') ||
        m.includes('rate') ||
        m.includes('429')
    ) {
        return 'AI sedang sibuk (kuota gratis kena rate limit). Coba lagi 1 menit lagi 😅';
    }
    if (m.includes('api key') || m.includes('belum di-set') || m.includes('belum diaktifkan')) {
        return 'AI belum diaktifkan oleh admin (GEMINI_API_KEY belum di-set).';
    }
    if (m.includes('belum ada catatan')) {
        return raw; // already friendly Indonesian
    }
    return raw;
}
