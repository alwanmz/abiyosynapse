import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { IconFileTypePdf, IconPhoto, IconUpload, IconX } from '@tabler/icons-react';
import { useRef, useState } from 'react';

interface AttachmentUploadProps {
    files: File[];
    onChange: (files: File[]) => void;
    maxFiles?: number;
    /** Total combined size in MB (default: 10). */
    maxTotalMb?: number;
    /** Max size per individual file in MB (default: 10). */
    maxFileMb?: number;
}

const ALLOWED_MIME = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/pdf',
];

const formatBytes = (b: number) => {
    if (b < 1024) return `${b} B`;
    if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
    return `${(b / 1024 / 1024).toFixed(1)} MB`;
};

export function AttachmentUpload({
    files,
    onChange,
    maxFiles = 5,
    maxTotalMb = 10,
    maxFileMb = 10,
}: AttachmentUploadProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [fileErrors, setFileErrors] = useState<string[]>([]);

    const totalBytes = files.reduce((s, f) => s + f.size, 0);
    const totalMb = totalBytes / (1024 * 1024);
    const overSize = totalMb > maxTotalMb;
    const overCount = files.length > maxFiles;

    const handleAdd = (incoming: File[]) => {
        const next = [...files];
        const errors: string[] = [];

        for (const f of incoming) {
            if (!ALLOWED_MIME.includes(f.type)) {
                errors.push(`${f.name}: hanya JPG, PNG, GIF, WebP, atau PDF.`);
                continue;
            }
            if (f.size > maxFileMb * 1024 * 1024) {
                errors.push(`${f.name}: ukuran maksimal ${maxFileMb} MB per file.`);
                continue;
            }
            if (next.find((x) => x.name === f.name && x.size === f.size)) continue;
            next.push(f);
            if (next.length >= maxFiles) break;
        }

        const newTotalMb = next.reduce((s, f) => s + f.size, 0) / (1024 * 1024);
        if (newTotalMb > maxTotalMb) {
            errors.push(`Total ukuran lampiran melebihi ${maxTotalMb} MB.`);
            setFileErrors(errors);
            return;
        }

        setFileErrors(errors);
        onChange(next);
    };

    const handlePaste = (e: React.ClipboardEvent) => {
        const items = Array.from(e.clipboardData?.items ?? []);
        const imageFiles = items
            .filter((item) => ALLOWED_MIME.includes(item.type))
            .map((item) => item.getAsFile())
            .filter((f): f is File => f !== null);
        if (imageFiles.length === 0) return;
        e.preventDefault();
        handleAdd(imageFiles);
    };

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <Label>Lampiran (gambar / PDF)</Label>
                <span
                    className={cn(
                        'text-[10px]',
                        overSize || overCount ? 'font-semibold text-rose-600' : 'text-muted-foreground',
                    )}
                >
                    {files.length}/{maxFiles} file · {totalMb.toFixed(1)}/{maxTotalMb} MB
                </span>
            </div>

            <div
                className="rounded-md border border-dashed bg-muted/20 p-3 outline-none"
                tabIndex={0}
                onDragOver={(e) => e.preventDefault()}
                onPaste={handlePaste}
                onDrop={(e) => {
                    e.preventDefault();
                    handleAdd(Array.from(e.dataTransfer.files));
                }}
            >
                {files.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-1.5 py-3 text-center text-xs text-muted-foreground">
                        <IconUpload className="h-5 w-5 opacity-60" />
                        <span>Drag &amp; drop, paste (Ctrl+V), atau klik tombol di bawah</span>
                        <span className="text-[10px]">Maks {maxFileMb} MB per file · Total maks {maxTotalMb} MB</span>
                    </div>
                ) : (
                    <ul className="space-y-1.5">
                        {files.map((f, idx) => {
                            const isImage = f.type.startsWith('image/');
                            return (
                                <li
                                    key={`${f.name}-${idx}`}
                                    className="flex items-center gap-2 rounded border bg-background px-2 py-1.5"
                                >
                                    {isImage ? (
                                        <IconPhoto size={16} className="text-blue-600" />
                                    ) : (
                                        <IconFileTypePdf size={16} className="text-rose-600" />
                                    )}
                                    <span className="flex-1 truncate text-xs">{f.name}</span>
                                    <span className="text-[10px] text-muted-foreground">
                                        {formatBytes(f.size)}
                                    </span>
                                    <button
                                        type="button"
                                        className="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-rose-600"
                                        onClick={() => onChange(files.filter((_, i) => i !== idx))}
                                    >
                                        <IconX size={14} />
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>

            {fileErrors.length > 0 && (
                <div className="space-y-1 rounded-md border border-destructive/30 bg-destructive/5 p-2 text-xs text-destructive">
                    {fileErrors.map((err) => (
                        <p key={err}>{err}</p>
                    ))}
                </div>
            )}

            <div className="flex items-center justify-between gap-2">
                <input
                    ref={inputRef}
                    type="file"
                    accept={ALLOWED_MIME.join(',')}
                    multiple
                    className="hidden"
                    onChange={(e) => { handleAdd(Array.from(e.target.files ?? [])); e.target.value = ''; }}
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-7 text-xs"
                    disabled={files.length >= maxFiles}
                    onClick={() => inputRef.current?.click()}
                >
                    <IconUpload className="mr-1 h-3.5 w-3.5" /> Pilih file
                </Button>
                {(overSize || overCount) && (
                    <span className="text-[10px] font-medium text-rose-600">
                        {overSize ? `Total > ${maxTotalMb} MB` : `Maks ${maxFiles} file`}
                    </span>
                )}
            </div>
        </div>
    );
}
