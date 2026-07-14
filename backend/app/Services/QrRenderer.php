<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Module\DotsModule;
use BaconQrCode\Renderer\Module\RoundnessModule;
use BaconQrCode\Renderer\Module\SquareModule;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders a styled QR PNG (optionally with a logo in the centre), server-side and
 * session-free — the same rendering the QR tool uses, reusable from queued jobs
 * (e.g. the brand-kit QR business card). Mirrors QrController's private renderers.
 */
class QrRenderer
{
    /** @param  ?string  $logoFile  absolute path to a raster logo to stamp in the centre */
    public function png(string $payload, string $style = 'rounded', string $color = '000000', ?string $logoFile = null, int $size = 1000): string
    {
        $ec = $logoFile ? ErrorCorrectionLevel::H() : ErrorCorrectionLevel::M(); // logo covers ~25% → H recovers 30%
        $png = $this->svgToPng($this->renderSvg($payload, $size, $style, strtolower($color), $ec), $size);

        return ($logoFile && is_file($logoFile)) ? $this->stampLogo($png, $logoFile) : $png;
    }

    private function renderSvg(string $payload, int $size, string $style, string $color, ErrorCorrectionLevel $ec): string
    {
        $module = match ($style) {
            'rounded' => new RoundnessModule(0.8),
            'dots'    => new DotsModule(DotsModule::LARGE),
            default   => SquareModule::instance(),
        };
        $eye = $style === 'dots' ? \BaconQrCode\Renderer\Eye\SimpleCircleEye::instance() : null;
        [$r, $g, $b] = sscanf($color, '%02x%02x%02x');
        $fill = Fill::uniformColor(new Rgb(255, 255, 255), new Rgb($r, $g, $b));
        $renderer = new ImageRenderer(new RendererStyle($size, 4, $module, $eye, $fill), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($payload, Encoder::DEFAULT_BYTE_MODE_ENCODING, $ec);
    }

    private function svgToPng(string $svg, int $size): string
    {
        $in = tempnam(sys_get_temp_dir(), 'qr').'.svg';
        $out = tempnam(sys_get_temp_dir(), 'qr').'.png';
        file_put_contents($in, $svg);
        $proc = new \Symfony\Component\Process\Process(['mutool', 'draw', '-o', $out, '-w', (string) $size, '-h', (string) $size, $in]);
        $proc->run();
        $png = $proc->isSuccessful() && is_file($out) ? (string) file_get_contents($out) : '';
        @unlink($in);
        @unlink($out);
        if (! str_starts_with($png, "\x89PNG")) {
            throw new \RuntimeException('QR rendering failed');
        }

        return $png;
    }

    /** Padding around the trimmed logo inside the white backing, as a fraction of the logo's larger side. */
    private const RING = 0.06;

    /**
     * Composite a centred logo onto a QR PNG behind a white CIRCLE that hugs the logo.
     * The logo is trimmed of transparent/white padding first so the circle wraps the
     * actual mark, not the padded canvas. Shared by the QR tool + the brand-kit renderer.
     */
    public function stampLogo(string $png, string $logoFile): string
    {
        $base = imagecreatefromstring($png);
        $logo = @imagecreatefromstring((string) file_get_contents($logoFile));
        if ($base === false || $logo === false) {
            return $png;
        }
        $logo = $this->trim($logo);
        $size = imagesx($base);
        $box = (int) round($size * 0.23);
        $lw = imagesx($logo);
        $lh = imagesy($logo);
        $scale = min($box / $lw, $box / $lh);
        $dw = (int) round($lw * $scale);
        $dh = (int) round($lh * $scale);
        // white circle hugging the trimmed logo's diagonal + a thin margin proportional to the logo
        $dia = (int) round(sqrt($dw * $dw + $dh * $dh) + 2 * (max($dw, $dh) * self::RING));
        $c = intdiv($size, 2);
        $white = imagecolorallocate($base, 255, 255, 255);
        imagealphablending($base, true);
        imagefilledellipse($base, $c, $c, $dia, $dia, $white);
        imagecopyresampled($base, $logo, (int) ($c - $dw / 2), (int) ($c - $dh / 2), 0, 0, $dw, $dh, $lw, $lh);
        imagedestroy($logo);

        ob_start();
        imagepng($base);
        $out = (string) ob_get_clean();
        imagedestroy($base);

        return $out !== '' ? $out : $png;
    }

    /**
     * Load a logo, strip transparent/near-white padding, and return [pngBytes, width, height]
     * of the tight crop — so callers (e.g. the SVG stamper) size the backing to the real mark.
     */
    public function trimmedLogo(string $logoFile): ?array
    {
        $logo = @imagecreatefromstring((string) @file_get_contents($logoFile));
        if ($logo === false) {
            return null;
        }
        $logo = $this->trim($logo);
        imagesavealpha($logo, true);
        ob_start();
        imagepng($logo);
        $png = (string) ob_get_clean();
        $dims = [$png, imagesx($logo), imagesy($logo)];
        imagedestroy($logo);

        return $png !== '' ? $dims : null;
    }

    /** Fraction of the logo's larger side used as the ring margin (for the SVG stamper). */
    public function ring(): float
    {
        return self::RING;
    }

    /**
     * Crop empty borders so the mark fills its bounding box. Background is detected from
     * the corners: transparent corners → trim transparent padding; else → trim solid-white
     * padding (so a white mark on a transparent canvas isn't eaten). Matches Img::trim.
     */
    private function trim(\GdImage $logo): \GdImage
    {
        imagepalettetotruecolor($logo);
        $w = imagesx($logo);
        $h = imagesy($logo);

        $transBg = 0;
        foreach ([[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]] as [$cx, $cy]) {
            if (((imagecolorat($logo, $cx, $cy) >> 24) & 0x7F) > 100) {
                $transBg++;
            }
        }
        $useAlpha = $transBg >= 2;

        $minx = $w;
        $miny = $h;
        $maxx = -1;
        $maxy = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($logo, $x, $y);
                if ($useAlpha) {
                    if ((($rgba >> 24) & 0x7F) > 100) {
                        continue; // (near-)transparent padding
                    }
                } elseif ((($rgba >> 24) & 0x7F) <= 100
                    && (($rgba >> 16) & 0xFF) > 245 && (($rgba >> 8) & 0xFF) > 245 && ($rgba & 0xFF) > 245) {
                    continue; // opaque near-white padding
                }
                $minx = min($minx, $x);
                $maxx = max($maxx, $x);
                $miny = min($miny, $y);
                $maxy = max($maxy, $y);
            }
        }
        if ($maxx < $minx || ($maxx - $minx + 1 >= $w && $maxy - $miny + 1 >= $h)) {
            return $logo; // blank, or nothing to trim
        }
        $tw = $maxx - $minx + 1;
        $th = $maxy - $miny + 1;
        $out = imagecreatetruecolor($tw, $th);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopy($out, $logo, 0, 0, $minx, $miny, $tw, $th);
        imagedestroy($logo);

        return $out;
    }
}
