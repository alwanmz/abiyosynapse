/**
 * Convert a user-pasted YouTube / Google Drive link into the URL that can be
 * loaded inside an <iframe>. Returns null when the link is not recognised so
 * the viewer can fall back to a plain "open in new tab" link.
 */
export function toEmbedUrl(rawUrl: string | null | undefined): string | null {
    if (!rawUrl) return null;

    let url: URL;
    try {
        url = new URL(rawUrl);
    } catch {
        return null;
    }

    const host = url.hostname.replace(/^www\./, '');

    // youtu.be/<id>
    if (host === 'youtu.be') {
        const id = url.pathname.slice(1);
        return id ? `https://www.youtube.com/embed/${id}` : null;
    }

    if (host === 'youtube.com' || host === 'm.youtube.com') {
        // /watch?v=<id>
        const watchId = url.searchParams.get('v');
        if (watchId) return `https://www.youtube.com/embed/${watchId}`;

        // /embed/<id> dan /shorts/<id> sudah/hampir siap pakai.
        const match = url.pathname.match(/^\/(embed|shorts|live)\/([^/?]+)/);
        if (match) return `https://www.youtube.com/embed/${match[2]}`;

        return null;
    }

    if (host === 'drive.google.com') {
        // /file/d/<id>/view -> /file/d/<id>/preview
        const match = url.pathname.match(/\/file\/d\/([^/]+)/);
        if (match) return `https://drive.google.com/file/d/${match[1]}/preview`;

        // ?id=<id> pada beberapa bentuk tautan lama.
        const id = url.searchParams.get('id');
        if (id) return `https://drive.google.com/file/d/${id}/preview`;

        return null;
    }

    return null;
}

/**
 * Google Sites and most documentation hosts can be embedded as-is; we only
 * guard against non-http schemes (javascript:, data:) reaching the iframe.
 */
export function toSafeEmbedUrl(rawUrl: string | null | undefined): string | null {
    if (!rawUrl) return null;

    try {
        const url = new URL(rawUrl);
        return url.protocol === 'https:' || url.protocol === 'http:' ? url.toString() : null;
    } catch {
        return null;
    }
}
