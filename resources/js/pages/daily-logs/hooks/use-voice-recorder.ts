import { useEffect, useRef, useState } from 'react';

type RecorderStatus = 'idle' | 'recording' | 'processing' | 'error';

interface UseVoiceRecorderOptions {
    /** Called with the recorded audio Blob once recording stops. */
    onStop: (blob: Blob) => void | Promise<void>;
    /** Hard cap so users don't accidentally record forever. */
    maxSeconds?: number;
}

/**
 * Thin wrapper around MediaRecorder. Records mic audio and hands the final
 * Blob back to the caller, who is responsible for actually transcribing it
 * (we send it to /ai/transcribe via the AI helper).
 */
export function useVoiceRecorder({ onStop, maxSeconds = 120 }: UseVoiceRecorderOptions) {
    const [status, setStatus] = useState<RecorderStatus>('idle');
    const [error, setError] = useState<string | null>(null);
    const [seconds, setSeconds] = useState(0);

    /** Reset error state so user can try again. */
    const dismissError = () => {
        if (status === 'error') {
            setStatus('idle');
            setError(null);
        }
    };

    const mediaRecorderRef = useRef<MediaRecorder | null>(null);
    const chunksRef = useRef<Blob[]>([]);
    const tickRef = useRef<number | null>(null);
    const stopTimeoutRef = useRef<number | null>(null);

    const supported =
        typeof window !== 'undefined' &&
        typeof navigator !== 'undefined' &&
        !!navigator.mediaDevices?.getUserMedia &&
        typeof MediaRecorder !== 'undefined';

    const reset = () => {
        chunksRef.current = [];
        setSeconds(0);
        if (tickRef.current) {
            window.clearInterval(tickRef.current);
            tickRef.current = null;
        }
        if (stopTimeoutRef.current) {
            window.clearTimeout(stopTimeoutRef.current);
            stopTimeoutRef.current = null;
        }
    };

    const start = async () => {
        if (!supported) {
            setError('Browser tidak mendukung perekaman suara.');
            setStatus('error');
            return;
        }
        // Reset any previous error state so user can try again.
        if (status === 'error') {
            setStatus('idle');
            setError(null);
        }
        try {
            setError(null);
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

            // Pick the first MIME the browser supports. Groq Whisper accepts all of these.
            const candidates = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/ogg;codecs=opus',
                'audio/mp4',
            ];
            const mimeType = candidates.find((m) => MediaRecorder.isTypeSupported(m));

            const rec = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
            mediaRecorderRef.current = rec;
            chunksRef.current = [];

            rec.ondataavailable = (e) => {
                if (e.data.size > 0) chunksRef.current.push(e.data);
            };

            rec.onstop = async () => {
                stream.getTracks().forEach((t) => t.stop());
                const blob = new Blob(chunksRef.current, { type: rec.mimeType || 'audio/webm' });
                reset();
                setStatus('processing');
                try {
                    await onStop(blob);
                    setStatus('idle');
                } catch (e: any) {
                    setError(e?.message || 'Gagal memproses audio.');
                    setStatus('error');
                }
            };

            rec.start();
            setStatus('recording');

            tickRef.current = window.setInterval(() => {
                setSeconds((s) => s + 1);
            }, 1000);

            stopTimeoutRef.current = window.setTimeout(() => {
                if (mediaRecorderRef.current?.state === 'recording') {
                    mediaRecorderRef.current.stop();
                }
            }, maxSeconds * 1000);
        } catch (e: any) {
            setError(
                e?.name === 'NotAllowedError'
                    ? 'Izin mikrofon ditolak. Aktifkan dari setting browser.'
                    : e?.message || 'Tidak bisa mengakses mikrofon.',
            );
            setStatus('error');
        }
    };

    const stop = () => {
        if (mediaRecorderRef.current?.state === 'recording') {
            mediaRecorderRef.current.stop();
        }
    };

    // Defensive cleanup on unmount.
    useEffect(() => {
        return () => {
            if (mediaRecorderRef.current?.state === 'recording') {
                mediaRecorderRef.current.stop();
            }
            reset();
        };
         
    }, []);

    return { status, error, seconds, supported, start, stop, dismissError };
}
