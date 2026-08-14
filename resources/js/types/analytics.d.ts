export interface OverviewStats {
    totalTickets: number;
    activeProjects: number;
    totalTeams: number;
    totalUsers: number;
    completedThisMonth: number;
    avgCompletionTime: number;
}

export interface StatusCount {
    status: string;
    count: number;
}

export interface PriorityCount {
    priority: string;
    count: number;
}

export interface TypeCount {
    type: string;
    count: number;
}

export interface AssigneeCount {
    user: string;
    count: number;
}

export interface CompletionTrend {
    date: string;
    count: number;
}

export interface TicketAnalytics {
    byStatus: StatusCount[];
    byPriority: PriorityCount[];
    byType: TypeCount[];
    byAssignee: AssigneeCount[];
    completionTrend: CompletionTrend[];
}

export interface TeamWorkload {
    team: string;
    tickets: number;
    members: number;
}

export interface TeamCompletionRate {
    team: string;
    rate: number;
    completed: number;
    total: number;
}

export interface TeamPerformance {
    workload: TeamWorkload[];
    completionRate: TeamCompletionRate[];
}

export interface TimelineStatus {
    status: string;
    count: number;
}

export interface TimelineProgress {
    timeline: string;
    progress: number;
    completed: number;
    total: number;
}

export interface TimelineAnalytics {
    byStatus: TimelineStatus[];
    progress: TimelineProgress[];
}

export interface TopContributor {
    user: string;
    completed: number;
}

export interface RoleCount {
    role: string;
    count: number;
}

export interface UserActivity {
    user: string;
    role: string;
    roleName?: string | null;
    assigned: number;
    completed: number;
    inProgress: number;
}

export interface UserProductivity {
    topContributors: TopContributor[];
    byRole: RoleCount[];
    userActivity: UserActivity[];
}

export interface AnalyticsData {
    overview: OverviewStats;
    tickets: TicketAnalytics;
    teams: TeamPerformance;
    timelines: TimelineAnalytics;
    users: UserProductivity;
}

export interface AnalyticsPageProps {
    analytics: AnalyticsData;
}
