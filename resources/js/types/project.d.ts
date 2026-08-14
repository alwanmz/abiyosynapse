export interface Project {
    id: number;
    name: string;
    description: string;
    status: 'planning' | 'in_progress' | 'on_hold' | 'completed' | 'cancelled';
    start_date: string | null;
    end_date: string | null;
    team: {
        id: number;
        name: string;
        color: string;
        members_count: number;
        members: Array<{ id: number; name: string; avatar_url?: string | null; avatar_path?: string | null }>;
    };
    project_manager: {
        id: number;
        name: string;
        avatar_url?: string | null;
        avatar_path?: string | null;
    };
    creator: string;
    created_at: string;
    file_path: string | null;
    file_name: string | null;
    image_path: string | null;
    image_name: string | null;
}

export interface Team {
    id: number;
    name: string;
    color: string;
    project_manager_id: number;
    projectManager: {
        id: number;
        name: string;
        avatar_url?: string | null;
        avatar_path?: string | null;
    };
}

export interface ProjectsProps {
    projects: Project[];
    teams: Team[];
}
