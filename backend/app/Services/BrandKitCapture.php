<?php

namespace App\Services;

use App\Jobs\BrandKit\BuildBrandKit;
use App\Models\BrandKit;
use Illuminate\Support\Str;

/**
 * Creates a BrandKit for the current session and queues the in-house generation
 * pipeline. The internal-engine counterpart to dispatching a pqsg capture —
 * reuses the same session('pqsg.key') correlation key the storefront polls with.
 */
class BrandKitCapture
{
    /**
     * @param  array{source?:string,logoUrl?:?string,website?:?string,company?:?string,sourceFile?:?string}  $data
     * @return string the correlation key (also stored in the session)
     */
    public function capture(array $data): string
    {
        $key = (string) Str::uuid();
        session(['pqsg.key' => $key, 'pqsg.strong' => $key, 'pqsg.strong_at' => now()->toIso8601String()]);

        BrandKit::updateOrCreate(['key' => $key], [
            'source'      => $data['source'] ?? 'designer',
            'status'      => 'pending',
            'logo_url'    => $data['logoUrl'] ?? null,
            'logo_path'   => $this->diskPath($data['logoUrl'] ?? null),
            'qr_path'     => $this->diskPath($data['qrUrl'] ?? null),
            'website'     => $data['website'] ?? null,
            'company'     => $data['company'] ?? null,
            'source_file' => $this->diskPath($data['sourceFile'] ?? null) ?? ($data['sourceFile'] ?? null),
            'stages'      => [],
        ]);

        BuildBrandKit::dispatch($key);

        return $key;
    }

    /** Turn a /storage/... URL into its public-disk relative path (else null). */
    private function diskPath(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return preg_match('#/storage/(.+)$#', $url, $m) ? $m[1] : null;
    }
}
