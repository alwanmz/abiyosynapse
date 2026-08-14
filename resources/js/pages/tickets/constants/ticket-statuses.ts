import { IconEdit } from '@tabler/icons-react';
import {
    AlertCircle,
    CheckCircle2,
    Circle,
    Clock,
    ListTodo,
    Target,
} from 'lucide-react';

export const TICKET_STATUSES = [
    { id: 'todo',       label: 'To Do',              icon: ListTodo,     color: 'bg-slate-500',  badgeClassName: 'bg-slate-100  text-slate-700  border-slate-300' },
    { id: 'pending',    label: 'Menunggu Approval',            icon: Clock,        color: 'bg-yellow-500', badgeClassName: 'bg-yellow-100 text-yellow-700 border-yellow-300' },
    { id: 'inprogress', label: 'Sedang Dikerjakan',  icon: Circle,       color: 'bg-blue-500',   badgeClassName: 'bg-blue-100   text-blue-700   border-blue-300' },
    { id: 'qa-ready',   label: 'Siap QA',            icon: Target,       color: 'bg-purple-500', badgeClassName: 'bg-purple-100 text-purple-700 border-purple-300' },
    { id: 'qa-test',    label: 'QA Test',            icon: AlertCircle,  color: 'bg-orange-500', badgeClassName: 'bg-orange-100 text-orange-700 border-orange-300' },
    { id: 'review',     label: 'Review',             icon: IconEdit,     color: 'bg-indigo-500', badgeClassName: 'bg-indigo-100 text-indigo-700 border-indigo-300' },
    { id: 'not-appropriate', label: 'Belum Sesuai',  icon: AlertCircle,  color: 'bg-rose-500',   badgeClassName: 'bg-rose-100   text-rose-700   border-rose-300' },
    { id: 'done',       label: 'Selesai',            icon: CheckCircle2, color: 'bg-green-500',  badgeClassName: 'bg-green-100  text-green-700  border-green-300' },
] as const;

export type TicketStatusId = (typeof TICKET_STATUSES)[number]['id'];
