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

    /** Resize (cap width) and re-encode raw image bytes to web-ready webp. */
    public static function webp(string $data, int $maxW): string
    {
        $im = @imagecreatefromstring($data);
        if ($im === false) {
            return $data;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w > $maxW) {
            $scaled = imagescale($im, $maxW, (int) round($h * $maxW / $w));
            if ($scaled !== false) {
                imagedestroy($im);
                $im = $scaled;
            }
        }
        ob_start();
        imagewebp($im, null, 82);
        $out = ob_get_clean();
        imagedestroy($im);

        return $out !== false && $out !== '' ? $out : $data;
    }

    /**
     * Center a logo on a square white canvas. Normalises odd aspect ratios (a
     * wide wordmark, a tall stack) so downstream generation produces square
     * product shots instead of inheriting the logo's aspect. Returns webp.
     */
    public static function square(string $data, int $size = 1024): string
    {
        $im = @imagecreatefromstring($data);
        if ($im === false) {
            return $data;
        }
        $w = imagesx($im);
        $h = imagesy($im);

        $canvas = imagecreatetruecolor($size, $size);
        imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocate($canvas, 255, 255, 255));

        $box = (int) ($size * 0.82);
        $scale = min($box / $w, $box / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        imagecopyresampled($canvas, $im, (int) (($size - $nw) / 2), (int) (($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagewebp($canvas, null, 92);
        $out = ob_get_clean();
        imagedestroy($im);
        imagedestroy($canvas);

        return $out !== false && $out !== '' ? $out : $data;
    }
}
