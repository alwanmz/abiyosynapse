interface TeamMember {
    id: number;
    name: string;
    email: string;
    role?: string;
    avatar_url?: string | null;
    avatar_path?: string | null;
}

export interface Team {
    id: number;
    name: string;
    description: string;
    color: string;
    members_count: number;
    creator: string;
    project_manager: {
        id: number;
        name: string;
        avatar_url?: string | null;
        avatar_path?: string | null;
    } | null;
    members: TeamMember[];
    created_at: string;
}
