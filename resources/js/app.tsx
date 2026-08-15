import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import i18n, { type SupportedLocale, SUPPORTED_LOCALES } from './i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// After a deploy, previously loaded tabs still reference old chunk hashes
// that no longer exist on the server. Vite's dynamic import then rejects
// (e.g. "Failed to fetch dynamically imported module"), which otherwise
// leaves Inertia's page resolution silently unresolved and the UI inert.
// A single forced reload picks up the current build instead.
const RELOAD_GUARD_KEY = 'inertia-stale-chunk-reload';

function reloadOnStaleChunk(error: unknown): never {
    const message = error instanceof Error ? error.message : String(error);
    const isStaleChunkError =
        /dynamically imported module|Importing a module script failed|Failed to fetch/i.test(
            message,
        );

    if (isStaleChunkError && !sessionStorage.getItem(RELOAD_GUARD_KEY)) {
        sessionStorage.setItem(RELOAD_GUARD_KEY, '1');
        window.location.reload();
    }

    throw error instanceof Error ? error : new Error(message);
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ).catch(reloadOnStaleChunk),
    setup({ el, App, props }) {
        sessionStorage.removeItem(RELOAD_GUARD_KEY);

        // Backend (SetLocale middleware + `locale` cookie) is the source of
        // truth for which language is active — keep i18next in sync with it
        // on every fresh page load instead of trusting browser detection.
        const backendLocale = (props.initialPage.props as { locale?: string })
            .locale;
        if (
            backendLocale &&
            SUPPORTED_LOCALES.includes(backendLocale as SupportedLocale) &&
            i18n.language !== backendLocale
        ) {
            i18n.changeLanguage(backendLocale);
        }

        const root = createRoot(el);

        root.render(
            <StrictMode>
                <App {...props} />
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
