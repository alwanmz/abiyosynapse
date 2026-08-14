import { UserSummary } from '@/types/ticket';

/**
 * Build the assignee/delegate option list: project team members first (so
 * they're easy to find), followed by every other user in the org, so anyone
 * can still be selected as a delegate even if they're not formally on the
 * project's team (e.g. a PM delegating across teams). Deduped by id.
 */
export function withRequiredAssignees(
    teamMembers: UserSummary[],
    allUsers: UserSummary[],
): UserSummary[] {
    const merged = [...teamMembers];
    const ids = new Set(merged.map((u) => u.id));

    for (const user of allUsers) {
        if (user && !ids.has(user.id)) {
            merged.push(user);
            ids.add(user.id);
        }
    }

    return merged;
}
