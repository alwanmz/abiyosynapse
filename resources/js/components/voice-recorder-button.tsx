import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { Mic, Square, LoaderCircle } from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface VoiceRecorderButtonProps {
    language?: string;
    disabled?: boolean;
    onTranscript: (transcript: string) => void;
    className?: string;
}

function xsrfToken(): string {
    const cookie = document.cookie.split('; ').find((item) => item.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

export function VoiceRecorderButton({ language = 'id', disabled = false, onTranscript, className }: VoiceRecorderButtonProps) {
    const { t } = useTranslation('ai');
    const [recording, setRecording] = useState(false);
    const [transcribing, setTranscribing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const recorderRef = useRef<MediaRecorder | null>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const chunksRef = useRef<Blob[]>([]);

    const upload = async (blob: Blob) => {
        setTranscribing(true);
        setError(null);
        const form = new FormData();
        form.append('audio', blob, 'voice-command.webm');
        form.append('language', language.slice(0, 2));
        try {
            const response = await fetch('/ai/voice/transcribe', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
                credentials: 'same-origin',
                body: form,
            });
            const payload = (await response.json()) as { transcript?: string; message?: string };
            if (!response.ok) throw new Error(payload.message ?? t('voice.error'));
            if (!payload.transcript?.trim()) throw new Error(t('voice.empty'));
            onTranscript(payload.transcript.trim());
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : t('voice.error'));
        } finally {
            setTranscribing(false);
        }
    };

    const stop = () => {
        recorderRef.current?.stop();
        streamRef.current?.getTracks().forEach((track) => track.stop());
        setRecording(false);
    };

    const start = async () => {
        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
            setError(t('voice.unsupported'));
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const recorder = new MediaRecorder(stream);
            chunksRef.current = [];
            streamRef.current = stream;
            recorderRef.current = recorder;
            recorder.ondataavailable = (event) => { if (event.data.size > 0) chunksRef.current.push(event.data); };
            recorder.onstop = () => void upload(new Blob(chunksRef.current, { type: recorder.mimeType || 'audio/webm' }));
            recorder.start();
            setRecording(true);
            setError(null);
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : t('voice.error'));
        }
    };

    return (
        <div className="flex items-center gap-1">
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button type="button" size="icon" variant={recording ? 'destructive' : 'outline'} className={cn('size-9 shrink-0', className)} onClick={recording ? stop : start} disabled={disabled || transcribing} aria-label={recording ? t('voice.stop') : t('voice.start')}>
                        {transcribing ? <LoaderCircle className="animate-spin" /> : recording ? <Square /> : <Mic />}
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="top">{transcribing ? t('voice.transcribing') : recording ? t('voice.stop') : t('voice.start')}</TooltipContent>
            </Tooltip>
            {error && <span className="max-w-40 truncate text-xs text-destructive" role="alert">{error}</span>}
        </div>
    );
}
