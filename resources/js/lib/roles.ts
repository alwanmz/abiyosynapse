/**
 * Shared role badge colors, used across the app (Pengguna list, Analitik, etc.)
 * so a given role always looks the same.
 *
 * Known role slugs get a fixed brand color. Any other role gets an automatic
 * color from FALLBACK_PALETTE, derived from a stable hash of the role name —
 * so every new role shows up with its own consistent color instead of a blank
 * badge.
 */
// Red/Hanko is deliberately excluded from every role badge, known or
// fallback — Nexumi reserves red for stop/destructive/approval-seal
// contexts only ("one red element per screen"), never a decorative role tag.
export const ROLE_COLORS: Record<string, { bg: string; text: string }> = {
    super_admin: { bg: 'bg-nx-navy-100', text: 'text-nx-navy-700' },
    project_manager: { bg: 'bg-violet-100', text: 'text-violet-700' },
    implementator: { bg: 'bg-amber-100', text: 'text-amber-700' },
    programmer: { bg: 'bg-nx-andon-run-bg', text: 'text-nx-andon-run' },
};

/**
 * Colors auto-assigned to roles not present in ROLE_COLORS. Class names are
 * written out in full so Tailwind picks them up during the build.
 */
const FALLBACK_PALETTE: { bg: string; text: string }[] = [
    { bg: 'bg-sky-100', text: 'text-sky-700' },
    { bg: 'bg-blue-100', text: 'text-blue-700' },
    { bg: 'bg-indigo-100', text: 'text-indigo-700' },
    { bg: 'bg-violet-100', text: 'text-violet-700' },
    { bg: 'bg-fuchsia-100', text: 'text-fuchsia-700' },
    { bg: 'bg-pink-100', text: 'text-pink-700' },
    { bg: 'bg-orange-100', text: 'text-orange-700' },
    { bg: 'bg-nx-cyan-100', text: 'text-nx-cyan-700' },
    { bg: 'bg-lime-100', text: 'text-lime-700' },
    { bg: 'bg-nx-andon-run-bg', text: 'text-nx-andon-run' },
];

/** Stable 32-bit hash so a given role name always maps to the same color. */
function hashRoleName(value: string): number {
    let hash = 0;
    for (let i = 0; i < value.length; i++) {
        hash = (hash << 5) - hash + value.charCodeAt(i);
        hash |= 0;
    }
    return Math.abs(hash);
}

export function getRoleColor(roleName: string | null | undefined) {
    if (!roleName) {
        return { bg: 'bg-slate-100', text: 'text-slate-700' };
    }

    if (ROLE_COLORS[roleName]) {
        return ROLE_COLORS[roleName];
    }

    return FALLBACK_PALETTE[hashRoleName(roleName) % FALLBACK_PALETTE.length];
}
