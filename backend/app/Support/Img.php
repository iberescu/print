<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class Img
{
    /**
     * Resolve a public image URL by base path, preferring webp and falling back
     * across extensions. This decouples the served file from whatever extension
     * the DB happens to store (seeding resets it to .png; generation writes .jpg/.webp).
     */
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');
        $base = preg_replace('/\.(webp|jpe?g|png)$/i', '', $path);

        foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
            if ($disk->exists("{$base}.{$ext}")) {
                return $disk->url("{$base}.{$ext}");
            }
        }

        return $disk->exists($path) ? $disk->url($path) : null;
    }
}
