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
        if (! preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#s', $image, $m)) {
            return null;
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) === 0 || strlen($bytes) > self::MAX_BYTES) {
            return null;
        }

        $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $path = 'previews/'.now()->format('Ym').'/'.Str::uuid().'.'.$ext;
        Storage::disk('public')->put($path, $bytes);

        return Storage::disk('public')->url($path);
    }
}
