import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/hooks/use-initials';
import { IconCamera, IconTrash } from '@tabler/icons-react';
import { useEffect, useRef, useState } from 'react';
import { AvatarCropDialog } from './avatar-crop-dialog';

interface AvatarUploadFieldProps {
    /** Display name for initials fallback. */
    name: string;
    /** Current saved avatar URL (e.g. user.avatar_url). */
    currentUrl?: string | null;
    /**
     * Called whenever the user picks + crops a new photo (newFile != null)
     * or asks to remove the current one (remove = true). Both are also
     * possible at the same time when the form was opened with a saved
     * photo and the user clicks "Hapus".
     */
    onChange: (state: { file: File | null; remove: boolean }) => void;
    /** Optional descriptive label — defaults to "Foto Profil". */
    label?: string;
    /** Optional helper text under the buttons. */
    hint?: string;
    /** Show as a smaller (h-16) avatar instead of the default h-24. */
    compact?: boolean;
}

const ACCEPTED = 'image/jpeg,image/png,image/gif,image/webp';

/**
 * "Pick file -> crop in a circle -> preview" composite, designed to drop
 * straight into Inertia useForm() flows. The parent owns the actual form
 * state (`avatar` File and `remove_avatar` bool); this component just
 * notifies via onChange.
 */
export function AvatarUploadField({
    name,
    currentUrl,
    onChange,
    label = 'Foto Profil',
    hint = 'JPG, PNG, GIF, atau WebP. Maks 2 MB. Foto akan dipotong jadi bulat.',
    compact = false,
}: AvatarUploadFieldProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const getInitials = useInitials();

    const [pickedFile, setPickedFile] = useState<File | null>(null);
    const [cropOpen, setCropOpen] = useState(false);
    const [cropped, setCropped] = useState<File | null>(null);
    const [removed, setRemoved] = useState(false);

    // Local preview URL for the cropped photo.
    const [croppedPreview, setCroppedPreview] = useState<string | null>(null);
    useEffect(() => {
        if (!cropped) {
            setCroppedPreview(null);
            return;
        }
        const url = URL.createObjectURL(cropped);
        setCroppedPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [cropped]);

    const previewUrl = croppedPreview ?? (removed ? null : currentUrl ?? null);

    const handlePick = () => inputRef.current?.click();

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const f = e.target.files?.[0];
        // Reset the input so the user can pick the same file twice in a row.
        e.target.value = '';
        if (!f) return;
        setPickedFile(f);
        setCropOpen(true);
    };

    const handleCropped = (file: File) => {
        setCropped(file);
        setRemoved(false);
        onChange({ file, remove: false });
    };

    const handleRemove = () => {
        setCropped(null);
        setRemoved(true);
        onChange({ file: null, remove: true });
    };

    const sizeClass = compact ? 'h-16 w-16' : 'h-24 w-24';

    return (
        <div className="space-y-2">
            <Label>{label}</Label>
            <div className="flex items-start gap-4">
                <Avatar className={`${sizeClass} ring-2 ring-blue-100 dark:ring-blue-900`}>
                    {previewUrl ? (
                        <AvatarImage src={previewUrl} alt={name} />
                    ) : null}
                    <AvatarFallback className="text-lg">
                        {getInitials(name) || '?'}
                    </AvatarFallback>
                </Avatar>

                <div className="flex-1 space-y-2">
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={handlePick}
                        >
                            <IconCamera className="mr-1 h-4 w-4" />
                            {previewUrl ? 'Ganti Foto' : 'Unggah Foto'}
                        </Button>
                        {previewUrl && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                onClick={handleRemove}
                            >
                                <IconTrash className="mr-1 h-4 w-4" />
                                Hapus
                            </Button>
                        )}
                    </div>
                    {hint && (
                        <p className="text-xs text-muted-foreground">{hint}</p>
                    )}
                </div>

                <input
                    ref={inputRef}
                    type="file"
                    accept={ACCEPTED}
                    className="hidden"
                    onChange={handleFileChange}
                />
            </div>

            <AvatarCropDialog
                open={cropOpen}
                onOpenChange={setCropOpen}
                file={pickedFile}
                onCropped={handleCropped}
            />
        </div>
    );
}
