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

        return ($logoFile && is_file($logoFile)) ? $this->stampLogoPng($png, $logoFile) : $png;
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

    /** White pad + logo centred, aspect kept (GD composite). */
    private function stampLogoPng(string $png, string $logoFile): string
    {
        $base = imagecreatefromstring($png);
        $logo = @imagecreatefromstring((string) file_get_contents($logoFile));
        if ($base === false || $logo === false) {
            return $png;
        }
        $size = imagesx($base);
        $pad = (int) round($size * 0.26);
        $box = (int) round($size * 0.23); // logo fills more of the pad — half the white ring around it
        $white = imagecolorallocate($base, 255, 255, 255);
        imagefilledrectangle($base, (int) (($size - $pad) / 2), (int) (($size - $pad) / 2), (int) (($size + $pad) / 2), (int) (($size + $pad) / 2), $white);

        $lw = imagesx($logo);
        $lh = imagesy($logo);
        $scale = min($box / $lw, $box / $lh);
        $dw = (int) round($lw * $scale);
        $dh = (int) round($lh * $scale);
        imagealphablending($base, true);
        imagecopyresampled($base, $logo, (int) (($size - $dw) / 2), (int) (($size - $dh) / 2), 0, 0, $dw, $dh, $lw, $lh);
        imagedestroy($logo);

        ob_start();
        imagepng($base);
        $out = (string) ob_get_clean();
        imagedestroy($base);

        return $out !== '' ? $out : $png;
    }
}
