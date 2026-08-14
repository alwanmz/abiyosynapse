import { Ticket } from '@/types/ticket';
import { router } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';

export function useTicketFilter(tickets: Ticket[]) {
    const [selectedProject, setSelectedProject] = useState('all');
    const [searchQuery, setSearchQuery] = useState('');
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const stats = useMemo(() => {
        return {
            totalTickets: tickets.length,
            totalStoryPoints: tickets.reduce(
                (sum, t) => sum + (t.story_points || 0),
                0,
            ),
            inProgressTickets: tickets.filter((t) => t.status === 'inprogress')
                .length,
            doneTickets: tickets.filter((t) => t.status === 'done').length,
        };
    }, [tickets]);

    const buildParams = (project: string, search: string) => {
        const params: Record<string, string> = {};
        if (project !== 'all') params.project_id = project;
        if (search) params.search = search;
        return params;
    };

    const handleProjectChange = (value: string) => {
        setSelectedProject(value);
        router.get('/tickets', buildParams(value, searchQuery), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSearchChange = (value: string) => {
        setSearchQuery(value);
        if (debounceTimer.current) clearTimeout(debounceTimer.current);
        debounceTimer.current = setTimeout(() => {
            router.get('/tickets', buildParams(selectedProject, value), {
                preserveState: true,
                preserveScroll: true,
            });
        }, 300);
    };

    return {
        stats,
        handleProjectChange,
        handleSearchChange,
    };
}
