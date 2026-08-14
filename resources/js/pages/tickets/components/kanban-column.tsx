import { useDroppable } from '@dnd-kit/core';

interface KanbanColumnProps {
    id: string;
    title: string;
    count: number;
    color: string;
    children: React.ReactNode;
}

export function KanbanColumn({ id, title, count, color, children }: KanbanColumnProps) {
    const { setNodeRef, isOver } = useDroppable({
        id: id,
    });

    return (
        <div 
            ref={setNodeRef}
            className={`flex w-72 shrink-0 flex-col rounded-lg border bg-slate-100/50 p-3 transition-colors dark:bg-slate-900/30 ${
                isOver ? 'ring-2 ring-primary ring-inset' : ''
            }`}
        >
            <div className="mb-4 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <span className={`h-2 w-2 rounded-full ${color}`} />
                    <h3 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                        {title}
                    </h3>
                </div>
                <span className="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                    {count}
                </span>
            </div>
            
            <div className="flex flex-1 flex-col gap-3">
                {children}
            </div>
        </div>
    );
}
