<?php

namespace App\Contracts\Ai;

interface VisionProvider
{
    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    public function extractStructured(
        string $filePath,
        string $mimeType,
        array $schema,
        string $instruction,
    ): array;

    public function name(): string;

    public function model(): string;

    public function isConfigured(): bool;
}
