<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for Replicate — runs the recraft SVG model behind the AI logo
 * maker. Uses sync mode (Prefer: wait) and falls back to polling when a
 * prediction takes longer than the wait window.
 */
class ReplicateClient
{
    /** Generate a vector logo; returns the raw SVG markup. */
    public function generateSvg(string $prompt, string $size = '1024x1024'): string
    {
        $resp = Http::timeout(120)
            ->withToken((string) config('shop.replicate.api_token'))
            ->withHeaders(['Prefer' => 'wait=60'])
            ->post(rtrim((string) config('shop.replicate.base_url'), '/')
                .'/models/'.config('shop.replicate.svg_model').'/predictions', [
                    'input' => ['prompt' => $prompt, 'size' => $size],
                ])->throw();

        [$status, $url] = $this->outcome($resp->json());

        // Prefer:wait can return before completion — poll the prediction.
        $get = $resp->json('urls.get');
        for ($i = 0; $i < 30 && $status !== 'succeeded' && $get; $i++) {
            if (in_array($status, ['failed', 'canceled'], true)) {
                throw new RuntimeException('Replicate prediction '.$status);
            }
            sleep(2);
            [$status, $url] = $this->outcome(
                Http::timeout(30)->withToken((string) config('shop.replicate.api_token'))
                    ->get($get)->throw()->json()
            );
        }

        if ($status !== 'succeeded' || ! $url) {
            throw new RuntimeException('Replicate returned no output (status '.$status.')');
        }

        $svg = Http::timeout(60)->get($url)->throw()->body();
        if (! str_contains($svg, '<svg')) {
            throw new RuntimeException('Replicate output is not an SVG');
        }

        return $svg;
    }

    /** @return array{0:?string,1:?string} [status, first output url] */
    private function outcome(array $json): array
    {
        $out = $json['output'] ?? null;

        return [$json['status'] ?? null, is_array($out) ? ($out[0] ?? null) : $out];
    }
}
