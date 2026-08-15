/**
 * Nexumi mark — a square with a navy crossbar and a cyan upright, matching
 * the `.rail__glyph` mark in docs/design/nexumi-design-system.html. Kept as
 * inline SVG (not a lucide icon) since this is the brand mark, not a
 * generic icon.
 */
export default function AppLogoIcon({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 26 26"
            className={className}
            aria-hidden="true"
            xmlns="http://www.w3.org/2000/svg"
        >
            <rect
                x="1"
                y="1"
                width="24"
                height="24"
                rx="2"
                fill="none"
                stroke="var(--nx-navy-700)"
                strokeWidth="1.5"
            />
            <line
                x1="1"
                y1="13"
                x2="25"
                y2="13"
                stroke="var(--nx-navy-700)"
                strokeWidth="1.5"
            />
            <line
                x1="13"
                y1="1"
                x2="13"
                y2="25"
                stroke="var(--nx-cyan-500)"
                strokeWidth="1.5"
            />
        </svg>
    );
}
