<?php

namespace App\Jobs\BrandKit;

use App\Models\BrandKit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** Load a stored public-disk path or an absolute URL as a Gemini inline image. */
trait ReadsImages
{
    /** @return array{mime:string,data:string}|null */
    protected function imageInput(?string $pathOrUrl): ?array
    {
        if (! $pathOrUrl) {
            return null;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($pathOrUrl)) {
            return ['mime' => $disk->mimeType($pathOrUrl) ?: 'image/png', 'data' => base64_encode($disk->get($pathOrUrl))];
        }

        try {
            $r = Http::timeout(15)->get($pathOrUrl);
            if ($r->successful() && $r->body() !== '') {
                return ['mime' => $r->header('Content-Type') ?: 'image/png', 'data' => base64_encode($r->body())];
            }
        } catch (\Throwable) {
            // best effort
        }

        return null;
    }

    /** The brand logo as an inline image (prefers the stored path, falls back to url). */
    protected function logoInput(BrandKit $kit): ?array
    {
        return $this->imageInput($kit->logo_path) ?? $this->imageInput($kit->logo_url);
    }
}
