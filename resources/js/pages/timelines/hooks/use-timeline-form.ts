import { router } from '@inertiajs/react';
import { useEffect, useState, useCallback } from 'react';
import { Timeline, TimelineFormData } from '@/types/timeline';

const initialFormData: TimelineFormData = {
    project_id: '',
    type: 'sprint',
    phase: '',
    title: '',
    description: '',
    start_date: '',
    end_date: '',
    status: 'pending',
    sprint_number: '',
    deliverables: [''],
};

interface UseTimelineFormProps {
    timeline?: Timeline;
    isOpen?: boolean;
}

export function useTimelineForm({ timeline, isOpen }: UseTimelineFormProps = {}) {
    const [formData, setFormData] = useState<TimelineFormData>(initialFormData);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [isSubmitting, setIsSubmitting] = useState(false);
    const isEditMode = !!timeline;

    useEffect(() => {
        if (isEditMode && isOpen && timeline) {
            setFormData({
                project_id: timeline.project.id.toString(),
                type: timeline.type,
                phase: timeline.phase || '',
                title: timeline.title,
                description: timeline.description || '',
                start_date: timeline.start_date || '',
                end_date: timeline.end_date || '',
                status: timeline.status,
                sprint_number: timeline.sprint_number?.toString() || '',
                deliverables:
                    timeline.deliverables && timeline.deliverables.length > 0
                        ? [...timeline.deliverables]
                        : [''],
            });
            setErrors({});
        }
    }, [isOpen, timeline, isEditMode]);

    const handleInputChange = useCallback((field: keyof TimelineFormData | string, value: any) => {
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

    const resetForm = () => {
        setFormData(initialFormData);
        setErrors({});
    };

    const handleSubmit = (e: React.FormEvent, onSuccess?: () => void) => {
        e.preventDefault();
        setIsSubmitting(true);
        setErrors({});

        const filteredDeliverables = formData.deliverables.filter(
            (d) => d.trim() !== ''
        );

        const submitData: Record<string, any> = {
            project_id: formData.project_id,
            type: formData.type,
            title: formData.title,
            status: formData.status,
        };

        if (formData.phase) submitData.phase = formData.phase;
        if (formData.description) submitData.description = formData.description;
        if (formData.start_date) submitData.start_date = formData.start_date;
        if (formData.end_date) submitData.end_date = formData.end_date;
        if (formData.sprint_number)
            submitData.sprint_number = parseInt(formData.sprint_number);
        if (filteredDeliverables.length > 0)
            submitData.deliverables = filteredDeliverables;

        const url = isEditMode ? `/timelines/${timeline!.id}` : '/timelines';
        const method = (isEditMode ? 'put' : 'post') as 'put' | 'post';

        router[method](url, submitData, {
            preserveScroll: true,
            onSuccess: () => {
                if (!isEditMode) {
                    resetForm();
                }
                setIsSubmitting(false);
                onSuccess?.();
            },
            onError: (err: any) => {
                setErrors(err as Record<string, string>);
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
        setFormData,
    };
}
