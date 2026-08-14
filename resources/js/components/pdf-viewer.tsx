import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { ChevronLeft, ChevronRight, Download, ZoomIn, ZoomOut } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Document, Page, pdfjs } from 'react-pdf';

import 'react-pdf/dist/Page/AnnotationLayer.css';
import 'react-pdf/dist/Page/TextLayer.css';

// Worker di-bundle lokal lewat Vite; menghindari permintaan ke CDN eksternal.
pdfjs.GlobalWorkerOptions.workerSrc = new URL(
    'pdfjs-dist/build/pdf.worker.min.mjs',
    import.meta.url,
).toString();

const MIN_SCALE = 0.6;
const MAX_SCALE = 2.4;

export function PdfViewer({ url, downloadName }: { url: string; downloadName?: string }) {
    const [numPages, setNumPages] = useState(0);
    const [pageNumber, setPageNumber] = useState(1);
    const [scale, setScale] = useState(1);
    const [error, setError] = useState<string | null>(null);
    const containerRef = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState<number>();

    // Lebar halaman mengikuti kontainer agar responsif di layar kecil.
    useEffect(() => {
        const element = containerRef.current;
        if (!element) return;

        const observer = new ResizeObserver(([entry]) => {
            setWidth(Math.max(entry.contentRect.width - 24, 0));
        });
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    const goToPage = useCallback(
        (next: number) => setPageNumber(Math.min(Math.max(next, 1), numPages || 1)),
        [numPages],
    );

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border bg-muted/40 p-2">
                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        className="h-8 w-8"
                        disabled={pageNumber <= 1}
                        onClick={() => goToPage(pageNumber - 1)}
                        aria-label="Halaman sebelumnya"
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </Button>
                    <span className="px-2 text-sm text-muted-foreground">
                        {numPages ? `${pageNumber} / ${numPages}` : '—'}
                    </span>
                    <Button
                        variant="outline"
                        size="icon"
                        className="h-8 w-8"
                        disabled={!numPages || pageNumber >= numPages}
                        onClick={() => goToPage(pageNumber + 1)}
                        aria-label="Halaman berikutnya"
                    >
                        <ChevronRight className="h-4 w-4" />
                    </Button>
                </div>

                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        className="h-8 w-8"
                        disabled={scale <= MIN_SCALE}
                        onClick={() => setScale((s) => Math.max(MIN_SCALE, +(s - 0.2).toFixed(2)))}
                        aria-label="Perkecil"
                    >
                        <ZoomOut className="h-4 w-4" />
                    </Button>
                    <span className="w-12 text-center text-sm text-muted-foreground">
                        {Math.round(scale * 100)}%
                    </span>
                    <Button
                        variant="outline"
                        size="icon"
                        className="h-8 w-8"
                        disabled={scale >= MAX_SCALE}
                        onClick={() => setScale((s) => Math.min(MAX_SCALE, +(s + 0.2).toFixed(2)))}
                        aria-label="Perbesar"
                    >
                        <ZoomIn className="h-4 w-4" />
                    </Button>
                    <Button variant="outline" size="sm" className="ml-1 h-8" asChild>
                        <a href={url} download={downloadName} target="_blank" rel="noreferrer">
                            <Download className="mr-1.5 h-4 w-4" />
                            Unduh
                        </a>
                    </Button>
                </div>
            </div>

            <div
                ref={containerRef}
                className="flex max-h-[75vh] justify-center overflow-auto rounded-md border bg-muted/30 p-3"
            >
                {error ? (
                    <div className="flex flex-col items-center gap-2 py-16 text-center text-muted-foreground">
                        <p className="text-sm">{error}</p>
                        <Button variant="outline" size="sm" asChild>
                            <a href={url} target="_blank" rel="noreferrer">
                                Buka di tab baru
                            </a>
                        </Button>
                    </div>
                ) : (
                    <Document
                        file={url}
                        onLoadSuccess={({ numPages }) => {
                            setNumPages(numPages);
                            setError(null);
                        }}
                        onLoadError={() => setError('Dokumen PDF gagal dimuat.')}
                        loading={
                            <div className="flex items-center gap-2 py-16 text-muted-foreground">
                                <Spinner className="h-4 w-4" />
                                <span className="text-sm">Memuat dokumen…</span>
                            </div>
                        }
                    >
                        <Page
                            pageNumber={pageNumber}
                            scale={scale}
                            width={width}
                            renderAnnotationLayer
                            renderTextLayer
                            className="shadow-sm"
                        />
                    </Document>
                )}
            </div>
        </div>
    );
}
