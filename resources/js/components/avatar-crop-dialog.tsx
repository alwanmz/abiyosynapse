import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { IconPhoto, IconZoomIn, IconZoomOut } from '@tabler/icons-react';
import { useEffect, useRef, useState } from 'react';

interface AvatarCropDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /**
     * The original picked file. The dialog draws this on a canvas, lets
     * the user drag/zoom inside a circular mask, then exports a square
     * cropped JPEG passed back via `onCropped`.
     */
    file: File | null;
    /** Output square size in pixels. Default 512 — Retina friendly. */
    size?: number;
    /**
     * Called once the user clicks Save. The blob is a square JPEG with
     * the same name as the input, but with `.jpg` extension forced.
     */
    onCropped: (blob: File) => void;
}

/**
 * Reusable circular avatar crop dialog. No external library — just a
 * canvas, a zoom slider, and pointer drag-to-pan.
 *
 * Implementation notes:
 *  - We render to a square canvas of `size` and clip to a circle so the
 *    output PNG is genuinely round (transparent corners). We export as
 *    JPEG with a white backdrop because most servers prefer that, but
 *    flip to PNG if you need true transparency.
 *  - State stored as image-pixel translation + zoom. Translation is
 *    clamped so the visible image always fully covers the circle (no
 *    gaps).
 */
export function AvatarCropDialog({
    open,
    onOpenChange,
    file,
    size = 512,
    onCropped,
}: AvatarCropDialogProps) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const imageRef = useRef<HTMLImageElement | null>(null);
    const [zoom, setZoom] = useState(1);
    const [tx, setTx] = useState(0);
    const [ty, setTy] = useState(0);
    const [loaded, setLoaded] = useState(false);
    const [imageSize, setImageSize] = useState<{ width: number; height: number } | null>(null);
    const dragStartRef = useRef<{ x: number; y: number; tx: number; ty: number } | null>(null);

    // Load the picked file into an HTMLImageElement.
    useEffect(() => {
        if (!file || !open) {
            // Reset local image state when the dialog closes or its input is cleared.
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setLoaded(false);
            setImageSize(null);
            imageRef.current = null;
            return;
        }
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            imageRef.current = img;
            setImageSize({ width: img.width, height: img.height });

            // Initial fit: scale so the image's *short* side covers the
            // crop circle, then center it.
            const minSide = Math.min(img.width, img.height);
            const initialZoom = size / minSide;
            setZoom(initialZoom);
            setTx((size - img.width * initialZoom) / 2);
            setTy((size - img.height * initialZoom) / 2);
            setLoaded(true);
        };
        img.src = url;
        return () => URL.revokeObjectURL(url);
    }, [file, open, size]);

    // Repaint whenever the user drags or zooms.
    useEffect(() => {
        const canvas = canvasRef.current;
        const img = imageRef.current;
        if (!canvas || !img) return;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        ctx.save();
        ctx.fillStyle = '#f3f4f6'; // neutral background while panning
        ctx.fillRect(0, 0, size, size);

        // Clip to a circle so the preview shows the round mask.
        ctx.beginPath();
        ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
        ctx.closePath();
        ctx.clip();

        ctx.drawImage(img, tx, ty, img.width * zoom, img.height * zoom);
        ctx.restore();
    }, [tx, ty, zoom, size, loaded]);

    /** Clamp pan so we don't expose blank gaps inside the circle. */
    const clampPan = (nextTx: number, nextTy: number) => {
        const img = imageRef.current;
        if (!img) return { tx: nextTx, ty: nextTy };
        const w = img.width * zoom;
        const h = img.height * zoom;
        const minX = size - w; // image right edge >= canvas right edge
        const minY = size - h;
        return {
            tx: Math.min(0, Math.max(minX, nextTx)),
            ty: Math.min(0, Math.max(minY, nextTy)),
        };
    };

    const handlePointerDown = (e: React.PointerEvent<HTMLCanvasElement>) => {
        e.currentTarget.setPointerCapture(e.pointerId);
        dragStartRef.current = { x: e.clientX, y: e.clientY, tx, ty };
    };
    const handlePointerMove = (e: React.PointerEvent<HTMLCanvasElement>) => {
        const start = dragStartRef.current;
        if (!start) return;
        const canvas = canvasRef.current;
        if (!canvas) return;
        // The canvas is rendered at `size` px but might be displayed
        // smaller. Scale pointer delta accordingly.
        const rect = canvas.getBoundingClientRect();
        const scale = size / rect.width;
        const next = clampPan(
            start.tx + (e.clientX - start.x) * scale,
            start.ty + (e.clientY - start.y) * scale,
        );
        setTx(next.tx);
        setTy(next.ty);
    };
    const handlePointerUp = (e: React.PointerEvent<HTMLCanvasElement>) => {
        e.currentTarget.releasePointerCapture(e.pointerId);
        dragStartRef.current = null;
    };

    const handleZoomChange = (next: number) => {
        const img = imageRef.current;
        if (!img) return;
        // Keep the center of the visible circle fixed while zooming.
        const cx = size / 2;
        const cy = size / 2;
        // Image-space coordinate currently at the canvas center.
        const ix = (cx - tx) / zoom;
        const iy = (cy - ty) / zoom;
        // Recompute translation so that the same image-space point
        // stays at the canvas center after the zoom change.
        const nextTx = cx - ix * next;
        const nextTy = cy - iy * next;
        setZoom(next);
        const clamped = clampPanFor(next, nextTx, nextTy);
        setTx(clamped.tx);
        setTy(clamped.ty);
    };

    /** Clamp helper that uses an explicit zoom (since `zoom` state may not be flushed yet). */
    const clampPanFor = (z: number, nextTx: number, nextTy: number) => {
        const img = imageRef.current;
        if (!img) return { tx: nextTx, ty: nextTy };
        const w = img.width * z;
        const h = img.height * z;
        const minX = size - w;
        const minY = size - h;
        return {
            tx: Math.min(0, Math.max(minX, nextTx)),
            ty: Math.min(0, Math.max(minY, nextTy)),
        };
    };

    const handleSave = async () => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        canvas.toBlob(
            (blob) => {
                if (!blob || !file) return;
                const baseName = file.name.replace(/\.[^.]+$/, '') || 'avatar';
                const out = new File([blob], `${baseName}.jpg`, {
                    type: 'image/jpeg',
                    lastModified: Date.now(),
                });
                onCropped(out);
                onOpenChange(false);
            },
            'image/jpeg',
            0.92,
        );
    };

    /** Min/max zoom — never let the image become smaller than the circle. */
    const minZoom = imageSize ? size / Math.min(imageSize.width, imageSize.height) : 1;
    const maxZoom = minZoom * 4;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <IconPhoto className="h-5 w-5" />
                        Atur Foto Profil
                    </DialogTitle>
                    <DialogDescription>
                        Geser untuk memposisikan, lalu atur zoom hingga foto pas di lingkaran.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    <div className="flex items-center justify-center">
                        <canvas
                            ref={canvasRef}
                            width={size}
                            height={size}
                            className="aspect-square w-full max-w-[280px] cursor-grab touch-none rounded-full border-2 border-nx-navy-200 bg-muted shadow-inner active:cursor-grabbing"
                            onPointerDown={handlePointerDown}
                            onPointerMove={handlePointerMove}
                            onPointerUp={handlePointerUp}
                            onPointerCancel={handlePointerUp}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label
                            htmlFor="avatar-zoom"
                            className="flex items-center justify-between text-xs"
                        >
                            <span className="flex items-center gap-1">
                                <IconZoomOut className="h-3.5 w-3.5" /> Zoom
                            </span>
                            <span className="font-mono text-muted-foreground">
                                {(zoom / minZoom).toFixed(1)}x
                            </span>
                            <IconZoomIn className="h-3.5 w-3.5" />
                        </Label>
                        <input
                            id="avatar-zoom"
                            type="range"
                            min={minZoom}
                            max={maxZoom}
                            step={(maxZoom - minZoom) / 100 || 0.01}
                            value={zoom}
                            disabled={!loaded}
                            onChange={(e) => handleZoomChange(parseFloat(e.target.value))}
                            className="h-2 w-full cursor-pointer appearance-none rounded-full bg-muted accent-nx-cyan-600"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Batal
                    </Button>
                    <Button onClick={handleSave} disabled={!loaded}>
                        Pakai Foto
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
