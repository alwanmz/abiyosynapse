import { AttachmentItem, Ticket, TicketDraftData, TicketFormData } from '@/types/ticket';
import { router } from '@inertiajs/react';
import { useEffect, useState, useCallback } from 'react';

const initialFormData: TicketFormData = {
    client_id: '',
    project_id: '',
    timeline_id: '',
    title: '',
    description: '',
    type: 'task',
    task_type_id: '',
    request_type: '',
    priority: 'low',
    status: 'todo',
    assigned_to: '',
    delegated_to: '',
    assignees: [],
    review_notes: '',
    due_date: '',
    estimated_hours: '',
    story_points: '',
    tags: [],
    attachments: [],
};

interface UseTicketFormProps {
    ticket?: Ticket;
    open?: boolean;
}

export function useTicketForm({ ticket, open }: UseTicketFormProps = {}) {
    const [formData, setFormData] = useState<TicketFormData>(initialFormData);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const isEditMode = !!ticket;

    // Re-initialize form only when the dialog opens or when the ticket ID changes.
    // Using ticket?.id (not the full object) prevents spurious resets caused by
    // Inertia recreating the ticket object reference after every page refresh.
    useEffect(() => {
        if (ticket && open) {
            setFormData({
                client_id: ticket.client_id.toString(),
                project_id: ticket.project_id?.toString() || '',
                timeline_id: ticket.timeline_id?.toString() || '',
                title: ticket.title,
                description: ticket.description || '',
                type: ticket.type,
                task_type_id: ticket.task_type_id?.toString() || '',
                request_type: ticket.request_type || '',
                priority: ticket.priority,
                status: ticket.status === 'backlog' ? 'todo' : ticket.status,
                assigned_to: ticket.assigned_to?.toString() || '',
                delegated_to: ticket.delegated_to?.toString() || '',
                assignees: (ticket.assignees ?? []).map((u) => u.id.toString()),
                review_notes: ticket.review_notes || '',
                due_date: ticket.due_date ? ticket.due_date.split('T')[0] : '',
                estimated_hours: ticket.estimated_hours?.toString() || '',
                story_points: ticket.story_points?.toString() || '',
                tags: ticket.tags || [],
                attachments: ticket.attachments || [],
            });
            setErrors({});
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [ticket?.id, open]);

    const handleInputChange = useCallback((field: keyof TicketFormData, value: string | string[] | File[] | AttachmentItem[]) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
        setErrors((prev) => {
            if (prev[field as string]) {
                const newErrors = { ...prev };
                delete newErrors[field as string];
                return newErrors;
            }
            return prev;
        });
    }, []);

    const removeAttachment = (index: number) => {
        setFormData((prev) => ({
            ...prev,
            attachments: prev.attachments.filter((_, i) => i !== index),
        }));
    };

    const resetForm = () => {
        setFormData(initialFormData);
        setErrors({});
    };

    const applyDraft = useCallback((draft: TicketDraftData) => {
        setFormData((prev) => {
            const next = { ...prev };
            const clientChanged = !!draft.client_id && String(draft.client_id) !== prev.client_id;
            const projectChanged = !!draft.project_id && String(draft.project_id) !== prev.project_id;

            if (draft.client_id) next.client_id = String(draft.client_id);
            if (draft.project_id) next.project_id = String(draft.project_id);
            if (draft.title) next.title = draft.title;
            if (draft.description) next.description = draft.description;
            if (draft.type) next.type = draft.type;
            if (draft.task_type_id) next.task_type_id = String(draft.task_type_id);
            if (draft.request_type) next.request_type = draft.request_type;
            if (draft.priority) next.priority = draft.priority;
            if (draft.status) next.status = draft.status;
            if (draft.assigned_to) next.assigned_to = String(draft.assigned_to);
            if (draft.due_date) next.due_date = draft.due_date;
            if (typeof draft.estimated_hours === 'number') next.estimated_hours = String(draft.estimated_hours);
            if (draft.story_points) next.story_points = String(draft.story_points);

            if (clientChanged && !draft.project_id) next.project_id = '';
            if ((clientChanged || projectChanged) && !draft.timeline_id) next.timeline_id = '';
            if ((clientChanged || projectChanged) && !draft.assigned_to) next.assigned_to = '';
            if (draft.timeline_id) next.timeline_id = String(draft.timeline_id);

            if (draft.tags?.length) {
                next.tags = Array.from(new Set([...prev.tags, ...draft.tags]));
            }

            return next;
        });

        setErrors((prev) => {
            const next = { ...prev };
            [
                'client_id',
                'project_id',
                'timeline_id',
                'title',
                'description',
                'type',
                'task_type_id',
                'request_type',
                'priority',
                'status',
                'assigned_to',
                'due_date',
                'estimated_hours',
                'story_points',
                'tags',
            ].forEach((key) => delete next[key]);
            return next;
        });
    }, []);

    const handleSubmit = (e: React.FormEvent, onSuccess?: () => void) => {
        e.preventDefault();
        setIsSubmitting(true);
        setErrors({});

        // Define a loose type for submission to handle dynamic construction
        const submitData: Record<string, any> = {
            client_id: formData.client_id,
            project_id: formData.project_id || null,
            title: formData.title,
            type: formData.type,
            priority: formData.priority,
        };

        if (formData.task_type_id) submitData.task_type_id = formData.task_type_id;
        if (formData.request_type) submitData.request_type = formData.request_type;

        if (isEditMode) {
            submitData.timeline_id = formData.timeline_id || null;
            submitData.description = formData.description;
            submitData.status = formData.status;
            submitData.assigned_to = formData.assigned_to || null;
            submitData.delegated_to = formData.delegated_to || null;
            submitData.sync_assignees = 1;
            submitData.assignees = formData.assignees;
            submitData.review_notes = formData.review_notes || null;
            submitData.due_date = formData.due_date || null;
            submitData.estimated_hours = formData.estimated_hours || null;
            submitData.story_points = formData.story_points || null;
            submitData.tags = formData.tags;
            
            const newAttachments = formData.attachments.filter(
                (a: unknown) => a instanceof File,
            );
            if (newAttachments.length) submitData.attachments = newAttachments;
        } else {
            submitData.timeline_id = formData.timeline_id || null;
            if (formData.description) submitData.description = formData.description;
            if (formData.status) submitData.status = formData.status;
            if (formData.assigned_to) submitData.assigned_to = formData.assigned_to;
            if (formData.delegated_to) submitData.delegated_to = formData.delegated_to;
            if (formData.assignees.length) submitData.assignees = formData.assignees;
            if (formData.review_notes) submitData.review_notes = formData.review_notes;
            if (formData.due_date) submitData.due_date = formData.due_date;
            if (formData.estimated_hours) submitData.estimated_hours = formData.estimated_hours;
            if (formData.story_points) submitData.story_points = formData.story_points;
            if (formData.tags && formData.tags.length > 0) submitData.tags = formData.tags;
            if (formData.attachments && formData.attachments.length > 0) submitData.attachments = formData.attachments;
        }

        // Inertia sends a multipart PUT as a real PUT request, but PHP only parses
        // multipart bodies on POST — so an edit submitted as PUT arrives with an empty
        // body and saves nothing (while the controller still flashes "success"). We POST
        // with a spoofed _method instead; Laravel rewrites it to PUT and the fields and
        // file uploads are parsed correctly.
        const url = isEditMode ? `/tickets/${ticket!.id}` : '/tickets';
        if (isEditMode) {
            submitData._method = 'put';
        }

        router.post(url, submitData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setIsSubmitting(false);
                if (!isEditMode) {
                    resetForm();
                }
                // The controller redirects back, so Inertia refetches the ticket list
                // and the board reflects the saved changes automatically.
                onSuccess?.();
            },
            onError: (errors: Record<string, string>) => {
                setErrors(errors);
                setIsSubmitting(false);
            },
        });
    };

    return {
        formData,
        errors,
        isSubmitting,
        isEditMode,
        handleInputChange,
        handleSubmit,
        resetForm,
        applyDraft,
        removeAttachment,
    };
}
