import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { IconDownload, IconExternalLink, IconFileText, IconLoader2, IconX } from '@tabler/icons-react';
import { useEffect, useMemo, useState } from 'react';

interface FileAttachment {
    name: string;
    path: string;
    disk?: string;
    url?: string;
    size: number;
    mime_type: string;
}

interface FilePreviewDialogProps {
    file: FileAttachment | null;
    ticketId?: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function FilePreviewDialog({ file, ticketId, open, onOpenChange }: FilePreviewDialogProps) {
    const [loading, setLoading] = useState(true);
    const [failed, setFailed] = useState(false);

    const fileUrl = useMemo(() => {
        if (!file) return '';
        if (ticketId) {
            const filename = file.path.split('/').pop();
            if (filename) return `/attachments/${ticketId}/preview?filename=${encodeURIComponent(filename)}`;
        }
        return file.url ?? file.path;
    }, [file, ticketId]);

    useEffect(() => {
        if (open && file) {
            setLoading(true);
            setFailed(false);
        }
    }, [open, file?.path]);

    if (!file) return null;

    const isImage = file.mime_type.startsWith('image/');
    const isPDF = file.mime_type === 'application/pdf';
    const stopLoading = () => setLoading(false);
    const markFailed = () => {
        setLoading(false);
        setFailed(true);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-5xl h-[85vh] p-0 overflow-hidden flex flex-col gap-0 border-none bg-black/5 dark:bg-zinc-950" onPointerDown={(e) => e.stopPropagation()}>
                <DialogHeader className="p-4 bg-background border-b z-10 flex flex-row items-center justify-between space-y-0">
                    <div className="flex items-center gap-3">
                        <div className="p-2 bg-primary/10 rounded-lg">
                            <IconFileText className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <DialogTitle className="text-sm font-semibold truncate max-w-[300px]">
                                {file.name}
                            </DialogTitle>
                            <p className="text-[10px] text-muted-foreground">
                                {(file.size / 1024).toFixed(1)} KB - {file.mime_type}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <a href={fileUrl} download={file.name}>
                                <IconDownload className="mr-2 h-4 w-4" />
                                Download
                            </a>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <a href={fileUrl} target="_blank" rel="noopener noreferrer">
                                <IconExternalLink className="mr-2 h-4 w-4" />
                                Open in Tab
                            </a>
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 hover:bg-destructive/10 hover:text-destructive"
                            onClick={() => onOpenChange(false)}
                        >
                            <IconX className="h-5 w-5" />
                        </Button>
                    </div>
                </DialogHeader>

                <div className="flex-1 relative bg-zinc-100 dark:bg-zinc-900 overflow-hidden flex items-center justify-center">
                    {loading && (
                        <div className="absolute inset-0 flex items-center justify-center bg-background/50 z-20">
                            <IconLoader2 className="h-8 w-8 animate-spin text-primary" />
                        </div>
                    )}

                    {failed ? (
                        <div className="flex flex-col items-center gap-4 text-center p-8">
                            <IconFileText className="h-10 w-10 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">Preview gagal dimuat. Buka file di tab baru atau download.</p>
                        </div>
                    ) : isImage ? (
                        <img
                            src={fileUrl}
                            alt={file.name}
                            className="max-h-full max-w-full object-contain shadow-2xl transition-all duration-300 transform scale-100"
                            onLoad={stopLoading}
                            onError={markFailed}
                        />
                    ) : isPDF ? (
                        <iframe
                            src={`${fileUrl}#toolbar=0`}
                            className="w-full h-full border-none"
                            onLoad={stopLoading}
                        />
                    ) : (
                        <div className="flex flex-col items-center gap-4 text-center p-8">
                            <div className="h-20 w-20 rounded-2xl bg-muted flex items-center justify-center">
                                <IconFileText className="h-10 w-10 text-muted-foreground" />
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold">Preview Unavailable</h3>
                                <p className="text-sm text-muted-foreground max-w-xs">
                                    File ini tidak bisa dipreview langsung. Download untuk melihat isinya.
                                </p>
                            </div>
                            <Button asChild>
                                <a href={fileUrl} download={file.name}>
                                    <IconDownload className="mr-2 h-4 w-4" />
                                    Download File
                                </a>
                            </Button>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
