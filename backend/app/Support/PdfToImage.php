<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Render uploaded PDF artwork to images with MuPDF (`mutool draw`) so the
 * editor can show a real preview and let the customer position each page.
 * Best-effort: returns [] when mutool is unavailable or the PDF is unreadable.
 */
class PdfToImage
{
    private const MAX_PAGES = 8;

    private const DPI = 144;

    private const MAX_WIDTH = 1600;

    /**
     * Render up to MAX_PAGES pages to public webp files.
     *
     * @return array<int,string> public URLs, in page order
     */
    public static function pages(string $absolutePdfPath): array
    {
        if (! is_file($absolutePdfPath) || ! self::mutoolAvailable()) {
            return [];
        }

        $tmp = rtrim(sys_get_temp_dir(), '/').'/pdf-'.Str::uuid();
        if (! @mkdir($tmp, 0777, true)) {
            return [];
        }

        try {
            // %d expands to the page number; a range past the last page still
            // renders the existing pages, so ignore mutool's exit status.
            $cmd = sprintf(
                'mutool draw -q -o %s -r %d %s 1-%d 2>/dev/null',
                escapeshellarg("{$tmp}/page-%d.png"),
                self::DPI,
                escapeshellarg($absolutePdfPath),
                self::MAX_PAGES,
            );
            @exec($cmd);

            $urls = [];
            $dir = 'previews/pdf/'.now()->format('Ym').'/'.Str::uuid();
            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                $png = "{$tmp}/page-{$page}.png";
                if (! is_file($png)) {
                    break;
                }
                $webp = self::toWebp((string) file_get_contents($png));
                if ($webp === null) {
                    continue;
                }
                $path = "{$dir}/page-{$page}.webp";
                Storage::disk('public')->put($path, $webp);
                $urls[] = Storage::disk('public')->url($path);
            }

            return $urls;
        } finally {
            foreach (glob("{$tmp}/*") ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmp);
        }
    }

    private static function mutoolAvailable(): bool
    {
        return trim((string) @shell_exec('command -v mutool')) !== '';
    }

    /** Cap width + re-encode to web-friendly webp (white background for CMYK/alpha). */
    private static function toWebp(string $pngBytes): ?string
    {
        $im = @imagecreatefromstring($pngBytes);
        if ($im === false) {
            return null;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w > self::MAX_WIDTH) {
            $scaled = imagescale($im, self::MAX_WIDTH, (int) round($h * self::MAX_WIDTH / $w));
            if ($scaled !== false) {
                imagedestroy($im);
                $im = $scaled;
                $w = imagesx($im);
                $h = imagesy($im);
            }
        }
        // flatten transparency onto white — print previews should look like paper
        $flat = imagecreatetruecolor($w, $h);
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $im, 0, 0, 0, 0, $w, $h);
        imagedestroy($im);

        ob_start();
        imagewebp($flat, null, 85);
        $out = ob_get_clean();
        imagedestroy($flat);

        return $out !== false && $out !== '' ? $out : null;
    }
}
