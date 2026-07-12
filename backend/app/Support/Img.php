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
     * Composite $overlay over the solid-magenta (#FF00FF) placeholder region in
     * $base — used to drop the REAL, scannable QR image onto an AI mockup that
     * only drew a flat magenta square. Returns raw PNG bytes, or null if no
     * magenta region is found (caller keeps the original). The overlay is pasted
     * as a square, slightly over-filling the region so no magenta fringe shows.
     */
    public static function overlayColorKey(string $base, string $overlay): ?string
    {
        $b = @imagecreatefromstring($base);
        if ($b === false) {
            return null;
        }
        $o = @imagecreatefromstring($overlay);
        if ($o === false) {
            imagedestroy($b);

            return null;
        }
        $w = imagesx($b);
        $h = imagesy($b);
        $minX = $w; $minY = $h; $maxX = -1; $maxY = -1; $count = 0;
        for ($y = 0; $y < $h; $y += 2) {
            for ($x = 0; $x < $w; $x += 2) {
                $rgb = imagecolorat($b, $x, $y);
                $r = ($rgb >> 16) & 0xFF; $g = ($rgb >> 8) & 0xFF; $bl = $rgb & 0xFF;
                if ($r > 170 && $g < 110 && $bl > 170) { // magenta-ish
                    if ($x < $minX) { $minX = $x; }
                    if ($x > $maxX) { $maxX = $x; }
                    if ($y < $minY) { $minY = $y; }
                    if ($y > $maxY) { $maxY = $y; }
                    $count++;
                }
            }
        }
        if ($count < 25 || $maxX < 0) {
            imagedestroy($o);
            imagedestroy($b);

            return null; // no placeholder found
        }
        $rw = $maxX - $minX; $rh = $maxY - $minY;
        $side = max($rw, $rh);
        $pad = (int) round($side * 0.06); // over-fill to cover the anti-aliased magenta edge
        $dx = $minX - $pad; $dy = $minY - $pad; $dsz = $side + 2 * $pad;
        imagecopyresampled($b, $o, $dx, $dy, 0, 0, $dsz, $dsz, imagesx($o), imagesy($o));

        ob_start();
        imagepng($b);
        $out = ob_get_clean();
        imagedestroy($o);
        imagedestroy($b);

        return $out !== false && $out !== '' ? $out : null;
    }

    /** Cap the LARGEST side to $maxSide (downscale only, never upscales), webp. */
    public static function cap(string $data, int $maxSide): string
    {
        $im = @imagecreatefromstring($data);
        if ($im === false) {
            return $data;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $big = max($w, $h);
        if ($big > $maxSide) {
            $scaled = imagescale($im, (int) round($w * $maxSide / $big), (int) round($h * $maxSide / $big));
            if ($scaled !== false) {
                imagedestroy($im);
                $im = $scaled;
            }
        }
        ob_start();
        imagewebp($im, null, 90);
        $out = ob_get_clean();
        imagedestroy($im);

        return $out !== false && $out !== '' ? $out : $data;
    }

    /**
     * Pad a logo onto a square white canvas at its OWN resolution (no scaling) so
     * downstream generation gets a square input without ever upscaling the art.
     * Canvas side = the logo's larger dimension. Returns webp.
     */
    public static function squarePad(string $data): string
    {
        $im = @imagecreatefromstring($data);
        if ($im === false) {
            return $data;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        $size = max($w, $h);

        $canvas = imagecreatetruecolor($size, $size);
        imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $im, (int) (($size - $w) / 2), (int) (($size - $h) / 2), 0, 0, $w, $h);

        ob_start();
        imagewebp($canvas, null, 92);
        $out = ob_get_clean();
        imagedestroy($im);
        imagedestroy($canvas);

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
