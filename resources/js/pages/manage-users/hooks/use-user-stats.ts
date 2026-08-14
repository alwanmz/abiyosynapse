import { User } from '@/types/user';
import { useMemo } from 'react';

/**
 * Aggregate stats for the User Management header cards.
 *
 * - `total`     — registered users on this page (or filtered set).
 * - `admins`    — users whose role is super_admin (legacy 'admin' kept
 *                 as an alias so older databases still report correctly).
 * - `roles`     — distinct roles present across `users`.
 * - `verified`  — users with a non-null email_verified_at (= account
 *                 sudah diverifikasi / siap login).
 */
export function useUserStats(
    users: User[],
    uniqueRoles: (string | undefined)[],
) {
    return useMemo(() => {
        const adminSlugs = ['super_admin', 'admin'];
        return {
            total: users.length,
            admins: users.filter((u) =>
                adminSlugs.includes(u.role?.name ?? ''),
            ).length,
            roles: uniqueRoles.length,
            verified: users.filter((u) => Boolean(u.email_verified_at)).length,
        };
    }, [users, uniqueRoles]);
}
