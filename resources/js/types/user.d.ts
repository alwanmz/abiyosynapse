export interface User {
    id: number;
    name: string;
    username: string | null;
    email: string;
    /** Public URL for the avatar, or null when no photo is set. */
    avatar_url?: string | null;
    /**
     * Legacy alias kept for the existing <UserInfo> component, which
     * passes `user.avatar` to <AvatarImage>. New code should prefer
     * `avatar_url`.
     */
    avatar?: string | null;
    email_verified_at?: string | null;
    role: {
        id: number;
        name: string;
        display_name: string;
        permissions?: { id: number; name: string }[];
    } | null;
    created_at: string;
}
