<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for Google's Gemini (Generative Language) API.
 * Used for image generation (req 6/11), template JSON (req 17) and vision scoring (req 10).
 */
class GeminiClient
{
    private function http(): PendingRequest
    {
        return Http::timeout(240)
            ->acceptJson()
            ->withQueryParameters(['key' => (string) config('shop.gemini.api_key')]);
    }

    private function url(string $model): string
    {
        return rtrim((string) config('shop.gemini.base_url'), '/')."/models/{$model}:generateContent";
    }

    /**
     * Generate (or edit/composite) an image.
     *
     * @param  array<int,array{mime:string,data:string}>  $inputImages base64 inline images
     * @return array{data:string,mime:string} raw bytes + mime
     */
    public function generateImage(string $prompt, array $inputImages = [], ?string $model = null): array
    {
        $parts = [['text' => $prompt]];
        foreach ($inputImages as $img) {
            $parts[] = ['inlineData' => ['mimeType' => $img['mime'], 'data' => $img['data']]];
        }

        $resp = $this->http()->post($this->url($model ?? config('shop.gemini.image_model')), [
            'contents'         => [['parts' => $parts]],
            'generationConfig' => ['responseModalities' => ['IMAGE']],
        ])->throw();

        foreach ($resp->json('candidates.0.content.parts', []) as $part) {
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if ($inline && ! empty($inline['data'])) {
                return [
                    'data' => base64_decode($inline['data']),
                    'mime' => $inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png',
                ];
            }
        }

        throw new RuntimeException('Gemini returned no image: '.$resp->body());
    }

    /** Generate plain text. */
    public function generateText(string $prompt, ?string $model = null): string
    {
        $resp = $this->http()->post($this->url($model ?? config('shop.gemini.text_model')), [
            'contents' => [['parts' => [['text' => $prompt]]]],
        ])->throw();

        return (string) $resp->json('candidates.0.content.parts.0.text', '');
    }

    /**
     * Ask a vision model to evaluate an image and return parsed JSON.
     *
     * @param  array{mime:string,data:string}  $image
     * @return array<string,mixed>
     */
    public function inspectImage(string $prompt, array $image, ?string $model = null): array
    {
        $resp = $this->http()->post($this->url($model ?? config('shop.gemini.vision_model')), [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inlineData' => ['mimeType' => $image['mime'], 'data' => $image['data']]],
                ],
            ]],
            'generationConfig' => ['responseMimeType' => 'application/json'],
        ])->throw();

        $text = (string) $resp->json('candidates.0.content.parts.0.text', '{}');

        return json_decode($text, true) ?? [];
    }
}
