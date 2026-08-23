<?php

namespace App\Http\Controllers;

use App\Services\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AiVoiceController extends Controller
{
    public function transcribe(Request $request, AiService $ai): JsonResponse
    {
        $validated = $request->validate([
            // WebM audio is commonly identified by PHP's fileinfo as `weba`.
            'audio' => ['required', 'file', 'mimes:webm,weba,ogg,mp4,wav,m4a', 'max:10240'],
            'language' => ['nullable', 'string', 'in:id,en,zh,ja,ko'],
        ]);

        try {
            $language = (string) ($validated['language'] ?? 'id');
            $transcript = $ai->transcribeAudio($request->file('audio'), $language . '-' . strtoupper($language));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'transcript' => $transcript,
            'language' => $language,
            'next_step' => 'review_and_send_to_copilot',
        ]);
    }
}
