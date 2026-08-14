export interface Timeline {
    id: number;
    project: {
        id: number;
        name: string;
        status: string;
        team: {
            name: string;
            color: string;
        };
    };
    type: 'sprint' | 'phase' | 'milestone' | 'event';
    phase:
        | 'planning'
        | 'backlog_refinement'
        | 'analysis'
        | 'design'
        | 'development'
        | 'testing'
        | 'code_review'
        | 'deployment'
        | 'release'
        | 'retrospective'
        | null;
    title: string;
    description: string | null;
    start_date: string | null;
    end_date: string | null;
    status: 'pending' | 'in_progress' | 'completed' | 'delayed' | 'cancelled';
    sprint_number: number | null;
    deliverables: string[] | null;
    creator: string | null;
    created_at: string;
}

export interface Project {
    id: number;
    name: string;
    status: string;
    team_id: number;
    team: {
        id: number;
        name: string;
        color: string;
    };
}

export interface TimelinePageProps {
    timelines: Timeline[];
    projects: Project[];
}

export interface TimelineFormData {
    project_id: string;
    type: 'sprint' | 'phase' | 'milestone' | 'event';
    phase: string;
    title: string;
    description: string;
    start_date: string;
    end_date: string;
    status: 'pending' | 'in_progress' | 'completed' | 'delayed' | 'cancelled';
    sprint_number: string;
    deliverables: string[];
}
