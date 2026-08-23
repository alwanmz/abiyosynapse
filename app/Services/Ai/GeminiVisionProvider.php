<?php

namespace App\Services\Ai;

use App\Contracts\Ai\VisionProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiVisionProvider implements VisionProvider
{
    public function extractStructured(
        string $filePath,
        string $mimeType,
        array $schema,
        string $instruction,
    ): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException('GEMINI_API_KEY belum dikonfigurasi. Dokumen dapat tetap direview dan diisi manual.');
        }

        if (app()->environment('production') && ! config('services.gemini.allow_production')) {
            throw new RuntimeException('OCR cloud free tier hanya diizinkan untuk development/demo. Konfigurasikan provider private atau berbayar sebelum production.');
        }

        $contents = [
            [
                'role' => 'user',
                'parts' => [
                    [
                        'text' => $instruction . "\n\nKembalikan JSON object saja sesuai schema berikut. Jangan menebak data yang tidak terbaca; gunakan null dan confidence 0.\n" . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => base64_encode((string) file_get_contents($filePath)),
                        ],
                    ],
                ],
            ],
        ];

        $url = rtrim(config('services.gemini.base_url'), '/')
            . '/models/' . rawurlencode($this->model())
            . ':generateContent?key=' . urlencode((string) config('services.gemini.api_key'));

        $response = Http::timeout((int) config('services.gemini.timeout', 120))
            ->acceptJson()
            ->asJson()
            ->post($url, [
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => 0,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

        if ($response->failed()) {
            Log::warning('Gemini vision request failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw new RuntimeException(data_get(
                $response->json(),
                'error.message',
                'Gemini OCR request failed (HTTP ' . $response->status() . ').',
            ));
        }

        $text = collect(data_get($response->json(), 'candidates.0.content.parts', []))
            ->pluck('text')
            ->filter()
            ->implode("\n");

        $json = $this->extractJson($text);
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini tidak mengembalikan JSON OCR yang valid.');
        }

        return $decoded;
    }

    public function name(): string
    {
        return 'gemini';
    }

    public function model(): string
    {
        return (string) config('services.gemini.vision_model', '');
    }

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key')) && filled($this->model());
    }

    private function extractJson(string $text): string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return '{}';
        }

        return substr($text, $start, $end - $start + 1);
    }
}
