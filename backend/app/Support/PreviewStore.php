<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Design previews and brand logos arrive from the editor as base64 data-URLs
 * (hundreds of KB each). Persisting them into the session/orders as-is bloats
 * the DB session store and makes every request deserialize megabytes. This
 * stores the image once on the public disk and returns its URL instead.
 */
class PreviewStore
{
    /** Decoded size cap — a canvas preview should never legitimately exceed this. */
    private const MAX_BYTES = 3_000_000;

    /**
     * Convert a data-URL to a stored file URL. Already-stored URLs/paths pass
     * through untouched; anything invalid or oversized returns null.
     *
     * SVG logos (popup upload accepts image/*) rasterise to PNG via mutool —
     * storing raw customer SVGs on our origin would be stored XSS, and
     * silently dropping them cost a WirMachenDruck capture on 2026-07-07.
     */
    public static function persist(?string $image): ?string
    {
        if (! $image) {
            return null;
        }
        // already a URL/path (idempotent — Review posts the stored URL back on add-to-cart)
        if (Str::startsWith($image, ['/storage/', 'http://', 'https://'])) {
            return $image;
        }
        if (! preg_match('#^data:image/(jpeg|png|webp|gif|svg\+xml);base64,(.+)$#s', $image, $m)) {
            return null;
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) === 0 || strlen($bytes) > self::MAX_BYTES) {
            return null;
        }

        if ($m[1] === 'svg+xml') {
            return self::rasterizeSvg($bytes);
        }

        $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $path = 'previews/'.now()->format('Ym').'/'.Str::uuid().'.'.$ext;
        Storage::disk('public')->put($path, $bytes);

        return Storage::disk('public')->url($path);
    }

    /** SVG bytes → stored transparent PNG url (mutool ships in the app image). */
    private static function rasterizeSvg(string $svg): ?string
    {
        if (! str_contains($svg, '<svg')) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'logo').'.svg';
        file_put_contents($tmp, $svg);
        $path = 'previews/'.now()->format('Ym').'/'.Str::uuid().'.png';
        $out = Storage::disk('public')->path($path);
        @mkdir(dirname($out), 0775, true);

        $proc = new \Symfony\Component\Process\Process(['mutool', 'draw', '-o', $out, '-w', '1024', '-h', '1024', '-c', 'rgba', $tmp]);
        $proc->run();
        @unlink($tmp);

        if (! $proc->isSuccessful() || ! is_file($out) || ! str_starts_with((string) file_get_contents($out, false, null, 0, 8), "\x89PNG")) {
            @unlink($out);

            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
