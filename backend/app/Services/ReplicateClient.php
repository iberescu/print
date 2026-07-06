<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for Replicate — runs the recraft SVG model behind the AI logo
 * maker. Fully async: create a prediction, poll its status, download the SVG.
 */
class ReplicateClient
{
    /** Start a prediction WITHOUT waiting — returns its id for status polling.
     *  Long-poll requests die on mobile Safari (~60 s fetch cap), so the logo
     *  maker generates asynchronously. */
    public function createSvgPrediction(string $prompt, string $size = '1024x1024'): string
    {
        $resp = Http::timeout(30)
            ->withToken((string) config('shop.replicate.api_token'))
            ->post(rtrim((string) config('shop.replicate.base_url'), '/')
                .'/models/'.config('shop.replicate.svg_model').'/predictions', [
                    'input' => ['prompt' => $prompt, 'size' => $size],
                ])->throw();

        $id = $resp->json('id');
        if (! $id) {
            throw new RuntimeException('Replicate returned no prediction id');
        }

        return $id;
    }

    /** @return array{status:?string,url:?string,error:?string} */
    public function getPrediction(string $id): array
    {
        $json = Http::timeout(20)
            ->withToken((string) config('shop.replicate.api_token'))
            ->get(rtrim((string) config('shop.replicate.base_url'), '/').'/predictions/'.$id)
            ->throw()->json();

        [$status, $url] = $this->outcome($json);

        return ['status' => $status, 'url' => $url, 'error' => $json['error'] ?? null];
    }

    /** Download a finished prediction's SVG output. */
    public function fetchSvg(string $url): string
    {
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
