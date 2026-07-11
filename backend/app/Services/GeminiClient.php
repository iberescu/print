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
            // Exponential backoff on transient rate-limit / server errors — the
            // internal engine fires many concurrent generations and Gemini 429s;
            // waits long enough (up to ~30s) to clear a per-minute rate window.
            ->retry(6, function (int $attempt) {
                return (int) min(30000, 500 * (2 ** $attempt)); // 1s,2s,4s,8s,16s,30s
            }, function ($exception) {
                $status = $exception instanceof \Illuminate\Http\Client\RequestException
                    ? $exception->response->status() : null;

                return $exception instanceof \Illuminate\Http\Client\ConnectionException
                    || in_array($status, [408, 429, 500, 502, 503, 529], true);
            }, throw: false)
            ->withQueryParameters(['key' => (string) config('shop.gemini.api_key')]);
    }

    /**
     * POST to a model, but funnel through a Redis semaphore so no more than
     * config('shop.gemini.max_concurrency') Gemini calls run at once across all
     * workers — this is what stops parallel captures from 429-ing each other.
     */
    private function call(string $model, array $body): \Illuminate\Http\Client\Response
    {
        $run = fn () => $this->http()->post($this->url($model), $body)->throw();
        $limit = (int) config('shop.gemini.max_concurrency', 0);
        if ($limit <= 0) {
            return $run();
        }

        return \Illuminate\Support\Facades\Redis::funnel('gemini-api')
            ->limit($limit)->block(120)->then($run, $run);
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

        $resp = $this->call($model ?? config('shop.gemini.image_model'), [
            'contents'         => [['parts' => $parts]],
            'generationConfig' => ['responseModalities' => ['IMAGE']],
        ]);

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
        $resp = $this->call($model ?? config('shop.gemini.text_model'), [
            'contents' => [['parts' => [['text' => $prompt]]]],
        ]);

        return (string) $resp->json('candidates.0.content.parts.0.text', '');
    }

    /**
     * Generate structured JSON (responseMimeType=application/json).
     *
     * @return array<string,mixed>
     */
    public function generateJson(string $prompt, ?string $model = null): array
    {
        $resp = $this->call($model ?? config('shop.gemini.text_model'), [
            'contents'         => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['responseMimeType' => 'application/json'],
        ]);

        $text = (string) $resp->json('candidates.0.content.parts.0.text', '{}');

        return json_decode($text, true) ?? [];
    }

    /**
     * Ask a vision model to evaluate an image and return parsed JSON.
     *
     * @param  array{mime:string,data:string}  $image
     * @return array<string,mixed>
     */
    public function inspectImage(string $prompt, array $image, ?string $model = null): array
    {
        $resp = $this->call($model ?? config('shop.gemini.vision_model'), [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inlineData' => ['mimeType' => $image['mime'], 'data' => $image['data']]],
                ],
            ]],
            'generationConfig' => ['responseMimeType' => 'application/json'],
        ]);

        $text = (string) $resp->json('candidates.0.content.parts.0.text', '{}');

        return json_decode($text, true) ?? [];
    }
}
