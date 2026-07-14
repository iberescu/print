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
     * Trim empty borders so the mark fills the frame — for AI-generated logos that
     * rasterise to a big square with the art floating in the middle, and for uploaded
     * logos framed in whitespace. The background is detected from the CORNERS: mostly
     * transparent corners → trim transparent padding; otherwise → trim solid-white
     * padding. (Corner detection avoids eating a white mark on a transparent canvas.)
     * Preserves transparency. Returns webp, or the original bytes if there's nothing
     * to trim. Cap the input first — this scans every pixel.
     */
    public static function trim(string $data): string
    {
        $im = @imagecreatefromstring($data);
        if ($im === false) {
            return $data;
        }
        imagepalettetotruecolor($im);
        $w = imagesx($im);
        $h = imagesy($im);

        $transBg = 0;
        foreach ([[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]] as [$cx, $cy]) {
            if (((imagecolorat($im, $cx, $cy) >> 24) & 0x7F) > 100) {
                $transBg++;
            }
        }
        $useAlpha = $transBg >= 2; // transparent-cornered → transparent padding; else white

        $minx = $w;
        $miny = $h;
        $maxx = -1;
        $maxy = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                if ($useAlpha) {
                    if ((($c >> 24) & 0x7F) > 100) {
                        continue; // (near-)transparent padding
                    }
                } elseif ((($c >> 24) & 0x7F) <= 100
                    && (($c >> 16) & 0xFF) > 245 && (($c >> 8) & 0xFF) > 245 && ($c & 0xFF) > 245) {
                    continue; // opaque near-white padding
                }
                $minx = min($minx, $x);
                $maxx = max($maxx, $x);
                $miny = min($miny, $y);
                $maxy = max($maxy, $y);
            }
        }
        if ($maxx < $minx || ($maxx - $minx + 1 >= $w && $maxy - $miny + 1 >= $h)) {
            imagedestroy($im);

            return $data; // blank, or nothing to trim
        }
        $tw = $maxx - $minx + 1;
        $th = $maxy - $miny + 1;
        $out = imagecreatetruecolor($tw, $th);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopy($out, $im, 0, 0, $minx, $miny, $tw, $th);
        ob_start();
        imagewebp($out, null, 92);
        $bytes = ob_get_clean();
        imagedestroy($im);
        imagedestroy($out);

        return $bytes !== false && $bytes !== '' ? $bytes : $data;
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
