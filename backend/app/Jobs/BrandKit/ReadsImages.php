<?php

namespace App\Jobs\BrandKit;

use App\Models\BrandKit;
use App\Support\Img;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** Load a stored public-disk path or an absolute URL as a Gemini inline image. */
trait ReadsImages
{
    /**
     * Cap an inline image to a max WIDTH (default 800px) before sending it to Gemini —
     * smaller uploads transfer and get processed noticeably faster. Logos/QRs stay well
     * above the ~500px the model needs to reproduce them faithfully. Returns webp.
     *
     * @param  array{mime:string,data:string}|null  $input
     * @return array{mime:string,data:string}|null
     */
    protected function capForGemini(?array $input, int $maxWidth = 800): ?array
    {
        if (! $input) {
            return null;
        }
        $bytes = base64_decode($input['data'], true);
        if ($bytes === false || $bytes === '') {
            return $input;
        }

        return ['mime' => 'image/webp', 'data' => base64_encode(Img::webp($bytes, $maxWidth))];
    }

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

    /**
     * The logo to send to Gemini for mockups — the square-padded copy, so a wide
     * wordmark isn't aspect-warped onto a product. Falls back to the display logo
     * for kits generated before the split.
     */
    protected function logoGeminiInput(BrandKit $kit): ?array
    {
        return $this->imageInput($kit->logo_gemini_path)
            ?? $this->imageInput($kit->logo_path)
            ?? $this->imageInput($kit->logo_url);
    }

    /** The captured QR-code image as an inline image (QR builder flow), if any. */
    protected function qrInput(BrandKit $kit): ?array
    {
        return $this->imageInput($kit->qr_path);
    }
}
