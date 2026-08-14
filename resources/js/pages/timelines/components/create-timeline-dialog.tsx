import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Project, Timeline } from '@/types/timeline';
import { IconPlus, IconTrash } from '@tabler/icons-react';
import { useState } from 'react';
import { useDeliverables } from '../hooks/use-deliverables';
import { useExistingSprints } from '../hooks/use-existing-sprints';
import { useTimelineForm } from '../hooks/use-timeline-form';
import { TimelineTypeInfo } from './timeline-type-info';

interface CreateTimelineDialogProps {
    projects: Project[];
    timelines: Timeline[];
}

export function CreateTimelineDialog({
    projects,
    timelines,
}: CreateTimelineDialogProps) {
    const [open, setOpen] = useState(false);

    const {
        formData,
        errors,
        isSubmitting,
        handleInputChange,
        handleSubmit: submitForm,
        resetForm,
        setFormData,
    } = useTimelineForm();

    const existingSprints = useExistingSprints(timelines, formData.project_id);

    const { handleDeliverableChange, addDeliverable, removeDeliverable } =
        useDeliverables(formData.deliverables, setFormData);

    const handleSubmit = (e: React.FormEvent) => {
        submitForm(e, () => setOpen(false));
    };

    const handleClose = () => {
        if (!isSubmitting) {
            setOpen(false);
            resetForm();
        }
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <IconPlus className="mr-2 h-4 w-4" />
                    Timeline Proyek Baru
                </Button>
            </DialogTrigger>
            <DialogContent
                className="max-h-[90vh] max-w-xl"
                onPointerDownOutside={(e) => isSubmitting && e.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle>Buat Timeline Proyek</DialogTitle>
                    <DialogDescription>
                        Siapkan proyek baru dengan fase SDLC dan timeline
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit}>
                    <div className="max-h-[calc(90vh-180px)] -mx-4 overflow-y-auto px-4">
                        <div className="grid gap-4 py-4">
                            <div className="grid gap-2">
                                <Label htmlFor="project">
                                    Proyek{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={formData.project_id}
                                    onValueChange={(value) =>
                                        handleInputChange('project_id', value)
                                    }
                                    disabled={isSubmitting}
                                >
                                    <SelectTrigger
                                        id="project"
                                        className={
                                            errors.project_id
                                                ? 'border-destructive'
                                                : ''
                                        }
                                    >
                                        <SelectValue placeholder="Pilih proyek" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {projects.map((project) => (
                                            <SelectItem
                                                key={project.id}
                                                value={project.id.toString()}
                                            >
                                                {project.name} (
                                                {project.team.name})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.project_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.project_id}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <div className="flex items-center gap-1">
                                        <Label htmlFor="type">
                                            Tipe{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>

                                        <TimelineTypeInfo />
                                    </div>
                                    <Select
                                        value={formData.type}
                                        onValueChange={(value: any) =>
                                            handleInputChange('type', value)
                                        }
                                        disabled={isSubmitting}
                                    >
                                        <SelectTrigger
                                            id="type"
                                            className={
                                                errors.type
                                                    ? 'border-destructive'
                                                    : ''
                                            }
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="sprint">
                                                Sprint
                                            </SelectItem>
                                            <SelectItem value="phase">
                                                Fase
                                            </SelectItem>
                                            <SelectItem value="milestone">
                                                Milestone
                                            </SelectItem>
                                            <SelectItem value="event">
                                                Event
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.type && (
                                        <p className="text-sm text-destructive">
                                            {errors.type}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phase">Fase SDLC</Label>
                                    <Select
                                        value={formData.phase}
                                        onValueChange={(value) =>
                                            handleInputChange('phase', value)
                                        }
                                        disabled={isSubmitting}
                                    >
                                        <SelectTrigger
                                            id="phase"
                                            className={
                                                errors.phase
                                                    ? 'border-destructive'
                                                    : ''
                                            }
                                        >
                                            <SelectValue placeholder="Pilih fase (opsional)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="planning">
                                                Perencanaan
                                            </SelectItem>
                                            <SelectItem value="backlog_refinement">
                                                Backlog Refinement
                                            </SelectItem>
                                            <SelectItem value="analysis">
                                                Analisis
                                            </SelectItem>
                                            <SelectItem value="design">
                                                Desain
                                            </SelectItem>
                                            <SelectItem value="development">
                                                Pengembangan
                                            </SelectItem>
                                            <SelectItem value="testing">
                                                Pengujian
                                            </SelectItem>
                                            <SelectItem value="code_review">
                                                Code Review
                                            </SelectItem>
                                            <SelectItem value="deployment">
                                                Deployment
                                            </SelectItem>
                                            <SelectItem value="release">
                                                Rilis
                                            </SelectItem>
                                            <SelectItem value="retrospective">
                                                Retrospektif
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.phase && (
                                        <p className="text-sm text-destructive">
                                            {errors.phase}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="title">
                                    Judul{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="title"
                                    value={formData.title}
                                    onChange={(e) =>
                                        handleInputChange(
                                            'title',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="contoh: Autentikasi & Otorisasi Pengguna"
                                    className={
                                        errors.title ? 'border-destructive' : ''
                                    }
                                    disabled={isSubmitting}
                                    maxLength={255}
                                />
                                {errors.title && (
                                    <p className="text-sm text-destructive">
                                        {errors.title}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Deskripsi</Label>
                                <Textarea
                                    id="description"
                                    value={formData.description}
                                    onChange={(e) =>
                                        handleInputChange(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Deskripsikan tujuan dan sasaran timeline..."
                                    className={
                                        errors.description
                                            ? 'border-destructive'
                                            : ''
                                    }
                                    disabled={isSubmitting}
                                    rows={3}
                                    maxLength={1000}
                                />
                                {errors.description && (
                                    <p className="text-sm text-destructive">
                                        {errors.description}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="start-date">
                                        Tanggal Mulai
                                    </Label>
                                    <Input
                                        id="start-date"
                                        type="date"
                                        value={formData.start_date}
                                        onChange={(e) =>
                                            handleInputChange(
                                                'start_date',
                                                e.target.value,
                                            )
                                        }
                                        className={
                                            errors.start_date
                                                ? 'border-destructive'
                                                : ''
                                        }
                                        disabled={isSubmitting}
                                    />
                                    {errors.start_date && (
                                        <p className="text-sm text-destructive">
                                            {errors.start_date}
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="end-date">Tanggal Selesai</Label>
                                    <Input
                                        id="end-date"
                                        type="date"
                                        value={formData.end_date}
                                        onChange={(e) =>
                                            handleInputChange(
                                                'end_date',
                                                e.target.value,
                                            )
                                        }
                                        className={
                                            errors.end_date
                                                ? 'border-destructive'
                                                : ''
                                        }
                                        disabled={isSubmitting}
                                    />
                                    {errors.end_date && (
                                        <p className="text-sm text-destructive">
                                            {errors.end_date}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="status">
                                    Status{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={formData.status}
                                    onValueChange={(value: any) =>
                                        handleInputChange('status', value)
                                    }
                                    disabled={isSubmitting}
                                >
                                    <SelectTrigger
                                        id="status"
                                        className={
                                            errors.status
                                                ? 'border-destructive'
                                                : ''
                                        }
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="pending">
                                            Menunggu
                                        </SelectItem>
                                        <SelectItem value="in_progress">
                                            Sedang Berjalan
                                        </SelectItem>
                                        <SelectItem value="completed">
                                            Selesai
                                        </SelectItem>
                                        <SelectItem value="delayed">
                                            Tertunda
                                        </SelectItem>
                                        <SelectItem value="cancelled">
                                            Dibatalkan
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.status && (
                                    <p className="text-sm text-destructive">
                                        {errors.status}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="sprint-number">
                                        Nomor Sprint
                                    </Label>
                                    <Input
                                        id="sprint-number"
                                        type="number"
                                        min="1"
                                        value={formData.sprint_number}
                                        onChange={(e) =>
                                            handleInputChange(
                                                'sprint_number',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="contoh: 1"
                                        className={
                                            errors.sprint_number
                                                ? 'border-destructive'
                                                : ''
                                        }
                                        disabled={isSubmitting}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {existingSprints.length > 0
                                            ? `Sprint yang ada untuk proyek ini: ${existingSprints.join(', ')}`
                                            : 'Beberapa timeline dapat berbagi nomor sprint yang sama'}
                                    </p>
                                    {errors.sprint_number && (
                                        <p className="text-sm text-destructive">
                                            {errors.sprint_number}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center justify-between">
                                    <Label>Deliverables</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addDeliverable}
                                        disabled={isSubmitting}
                                    >
                                        <IconPlus className="mr-1 h-3 w-3" />
                                        Tambah
                                    </Button>
                                </div>
                                {formData.deliverables.map(
                                    (deliverable, index) => (
                                        <div key={index} className="flex gap-2">
                                            <Input
                                                value={deliverable}
                                                onChange={(e) =>
                                                    handleDeliverableChange(
                                                        index,
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="contoh: Modul autentikasi pengguna"
                                                disabled={isSubmitting}
                                            />
                                            {formData.deliverables.length >
                                                1 && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        onClick={() =>
                                                            removeDeliverable(index)
                                                        }
                                                        disabled={isSubmitting}
                                                    >
                                                        <IconTrash className="h-4 w-4" />
                                                    </Button>
                                                )}
                                        </div>
                                    ),
                                )}
                                {errors.deliverables && (
                                    <p className="text-sm text-destructive">
                                        {errors.deliverables}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={isSubmitting}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            {isSubmitting ? 'Membuat...' : 'Buat Timeline'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
