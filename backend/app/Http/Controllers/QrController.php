<?php

namespace App\Http\Controllers;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\Module\DotsModule;
use BaconQrCode\Renderer\Module\RoundnessModule;
use BaconQrCode\Renderer\Module\SquareModule;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Free QR code generator (req: IKEA-style low-CPC entry tool). URL / vCard /
 * email / phone payloads render server-side — crisp SVG for screens, PNG for
 * print, downloads via Content-Disposition (anchor downloads die on iOS).
 * Styling: square/rounded/dot modules, any dark foreground colour, and an
 * uploaded logo composited into the centre (error correction jumps to H so the
 * ~25% the logo covers stays recoverable). Generating a QR for a website hands
 * that URL — and the logo, when given — to the upsell engine: the page then
 * shows the visitor's brand printed on real products.
 */
class QrController extends Controller
{
    public function show()
    {
        return Inertia::render('QrGenerator', [
            // after a login-gated download, resume the "your logo on" gallery generated during login
            'captureKey' => session('pqsg.key'),
            'pqsg' => [
                'apiBase'   => config('shop.pqsg.api_base'),
                'widgetSrc' => config('shop.pqsg.widget_src'),
            ],
            'useCases' => [ // Gemini-generated print scenes (storage/app/public/promos)
                'card'    => \App\Support\Img::url('promos/qr-business-card'),
                'menu'    => \App\Support\Img::url('promos/qr-menu'),
                'sticker' => \App\Support\Img::url('promos/qr-sticker'),
                'signage' => \App\Support\Img::url('promos/qr-signage'),
            ],
        ]);
    }

    /** Render the QR image. Also the <img> source for live preview and the
     *  fabric source when inserting a QR in the online designer. */
    public function image(Request $request)
    {
        $data = $request->validate([
            'data'     => ['required', 'string', 'max:1800'],  // a full vCard fits
            'format'   => ['nullable', 'in:svg,png'],
            'size'     => ['nullable', 'integer', 'min:120', 'max:2000'],
            'download' => ['nullable', 'boolean'],
            'style'    => ['nullable', 'in:square,rounded,dots'],
            'color'    => ['nullable', 'regex:/^[0-9a-fA-F]{6}$/'],
            'logo'     => ['nullable', 'boolean'],
        ]);

        $payload = $data['data'];
        $format = $data['format'] ?? 'svg';
        $size = (int) ($data['size'] ?? 600);
        $style = $data['style'] ?? 'square';
        $color = strtolower($data['color'] ?? '000000');

        $logoPath = null;
        if ($request->boolean('logo') && ($p = session('qr.logo')) && Storage::disk('public')->exists($p)) {
            $logoPath = Storage::disk('public')->path($p);
        }
        // the centre logo covers ~25% of the pattern — level H recovers 30%
        $ec = $logoPath ? ErrorCorrectionLevel::H() : ErrorCorrectionLevel::M();
        $styled = $style !== 'square' || $color !== '000000';

        if ($format === 'png') {
            $out = $styled
                ? $this->svgToPng($this->renderSvg($payload, $size, $style, $color, $ec), $size)
                : (new Writer(new GDLibRenderer($size)))->writeString($payload, Encoder::DEFAULT_BYTE_MODE_ENCODING, $ec);
            if ($logoPath) {
                $out = $this->stampLogoPng($out, $logoPath);
            }
            $mime = 'image/png';
            $name = 'qr-code.png';
        } else {
            $out = $this->renderSvg($payload, $size, $style, $color, $ec);
            if ($logoPath) {
                $out = $this->stampLogoSvg($out, $logoPath, $size);
            }
            $mime = 'image/svg+xml';
            $name = 'qr-code.svg';
        }

        $headers = [
            'Content-Type' => $mime,
            // logo variants depend on the session — never let a shared cache serve them across users
            'Cache-Control' => $logoPath ? 'private, max-age=3600' : 'public, max-age=86400',
        ];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename='.$name;
        }

        return response($out, 200, $headers);
    }

    /** Upload a centre logo: SVG rasterises via mutool, rasters re-encode via GD
     *  (also sanitises the upload). Stored per session, referenced by logo=1. */
    public function logo(Request $request)
    {
        $request->validate(['logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048']]);

        $file = $request->file('logo');
        $raw = (string) file_get_contents($file->getRealPath());
        $png = str_contains(strtolower($file->getMimeType() ?? ''), 'svg') || str_starts_with(ltrim($raw), '<')
            ? $this->rasterizeSvg($raw)
            : $this->reencodePng($raw);
        abort_unless($png !== null, 422, 'Could not read that image — try a PNG or JPG.');

        $path = 'qr-logos/'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $png);
        session(['qr.logo' => $path]);

        return response()->json(['url' => Storage::disk('public')->url($path)]);
    }

    /** A finished QR is a brand signal: hand the destination — and the centre
     *  logo, when one was used — to the upsell engine so the gallery can show
     *  their brand on printed products. */
    public function capture(Request $request)
    {
        $data = $request->validate([
            'qr'    => ['nullable', 'file', 'image', 'max:5120'], // the rendered QR image, placed on products
            'url'   => ['nullable', 'string', 'max:300'],
            'email' => ['nullable', 'email', 'max:200'],
            'logo'  => ['nullable', 'boolean'],
        ]);

        $website = trim((string) ($data['url'] ?? ''));
        if ($website === '' && ! empty($data['email'])) {
            // no site given — the email domain is usually the business site
            $domain = strtolower(Str::after($data['email'], '@'));
            if (! in_array($domain, ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com', 'aol.com', 'proton.me', 'protonmail.com'], true)) {
                $website = $domain;
            }
        }
        if ($website !== '' && ! preg_match('#^https?://#i', $website)) {
            $website = 'https://'.$website;
        }

        // The centre logo, if the buyer uploaded one.
        $logoUrl = null;
        if ($request->boolean('logo') && ($p = session('qr.logo')) && Storage::disk('public')->exists($p)) {
            $logoUrl = Storage::disk('public')->url($p);
        }

        // The rendered QR image itself — placed on the print products (with the logo
        // when there is one, else on its own).
        $qrUrl = null;
        if ($request->hasFile('qr')) {
            $qrPath = 'qr-captures/'.Str::uuid().'.png';
            Storage::disk('public')->put($qrPath, file_get_contents($request->file('qr')->getRealPath()));
            $qrUrl = Storage::disk('public')->url($qrPath);
        }

        if (! $qrUrl && ! $logoUrl && $website === '') {
            return response()->json(['key' => null]); // nothing brandable
        }

        // In-house engine: build "your [logo +] QR on products" — the SAME pipeline
        // as the designer/logo-maker flows (BrandKitCapture → BuildBrandKit).
        if (config('shop.upsell_engine') === 'internal') {
            $key = app(\App\Services\BrandKitCapture::class)->capture([
                'source'  => 'qr-maker',
                'logoUrl' => $logoUrl,
                'qrUrl'   => $qrUrl,
                'website' => $website ?: null,
            ]);

            return response()->json(['key' => $key]);
        }

        // Legacy third-party engine.
        $key = (string) Str::uuid();
        session(['pqsg.key' => $key, 'pqsg.strong' => $key, 'pqsg.strong_at' => now()->toIso8601String()]);
        \App\Jobs\SendPqsgCapture::dispatchAfterResponse(
            key: $key,
            source: 'runmyprint-qr-maker',
            logoUrl: $logoUrl,
            website: $website ?: null,
        );

        return response()->json(['key' => $key]);
    }

    // ---- rendering internals -------------------------------------------------

    private function renderSvg(string $payload, int $size, string $style, string $color, ErrorCorrectionLevel $ec): string
    {
        $module = match ($style) {
            'rounded' => new RoundnessModule(0.8), // MEDIUM (0.5) barely differs from square at screen sizes
            'dots'    => new DotsModule(DotsModule::LARGE), // smaller dots leave too little ink per module — decoders give up
            default   => SquareModule::instance(),
        };
        // dot-shaped finder eyes (the ModuleEye default) break decoders — keep
        // the eyes solid circles so dot-style codes stay scannable
        $eye = $style === 'dots' ? \BaconQrCode\Renderer\Eye\SimpleCircleEye::instance() : null;
        [$r, $g, $b] = sscanf($color, '%02x%02x%02x');
        $fill = Fill::uniformColor(new Rgb(255, 255, 255), new Rgb($r, $g, $b));
        $renderer = new ImageRenderer(new RendererStyle($size, 4, $module, $eye, $fill), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($payload, Encoder::DEFAULT_BYTE_MODE_ENCODING, $ec);
    }

    /** Styled PNGs go through the SVG renderer + mutool so rounded/dot modules
     *  and colours look identical in both formats (GD can only do squares). */
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
        abort_unless(str_starts_with($png, "\x89PNG"), 500, 'QR rendering failed.');

        return $png;
    }

    /** White pad + logo <image> injected into the SVG's own coordinate space. */
    private function stampLogoSvg(string $svg, string $logoPath, int $size): string
    {
        $pad = (int) round($size * 0.26);
        $box = (int) round($size * 0.20);
        $b64 = base64_encode((string) file_get_contents($logoPath));
        $overlay = sprintf(
            '<rect x="%d" y="%d" width="%d" height="%d" rx="%d" fill="#ffffff"/>'
            .'<image x="%d" y="%d" width="%d" height="%d" preserveAspectRatio="xMidYMid meet" href="data:image/png;base64,%s"/>',
            (int) (($size - $pad) / 2), (int) (($size - $pad) / 2), $pad, $pad, (int) round($size * 0.02),
            (int) (($size - $box) / 2), (int) (($size - $box) / 2), $box, $box, $b64,
        );

        return str_replace('</svg>', $overlay.'</svg>', $svg);
    }

    /** GD composite for PNGs: white pad, then the logo centred, aspect kept. */
    private function stampLogoPng(string $png, string $logoPath): string
    {
        $base = imagecreatefromstring($png);
        $logo = imagecreatefromstring((string) file_get_contents($logoPath));
        if ($base === false || $logo === false) {
            return $png;
        }
        $size = imagesx($base);
        $pad = (int) round($size * 0.26);
        $box = (int) round($size * 0.20);
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

    /** Uploaded SVG logo → transparent PNG (mutool, PreviewStore pattern). */
    private function rasterizeSvg(string $svg): ?string
    {
        if (! str_contains($svg, '<svg')) {
            return null;
        }
        $in = tempnam(sys_get_temp_dir(), 'qrlogo').'.svg';
        $out = tempnam(sys_get_temp_dir(), 'qrlogo').'.png';
        file_put_contents($in, $svg);
        $proc = new \Symfony\Component\Process\Process(['mutool', 'draw', '-o', $out, '-w', '512', '-h', '512', '-c', 'rgba', $in]);
        $proc->run();
        $png = $proc->isSuccessful() && is_file($out) ? (string) file_get_contents($out) : null;
        @unlink($in);
        @unlink($out);

        return $png !== null && str_starts_with($png, "\x89PNG") ? $png : null;
    }

    /** Raster upload → alpha-preserving PNG capped at 512 px (GD re-encode). */
    private function reencodePng(string $raw): ?string
    {
        $im = @imagecreatefromstring($raw);
        if ($im === false) {
            return null;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        if (max($w, $h) > 512) {
            $s = 512 / max($w, $h);
            $scaled = imagescale($im, (int) round($w * $s), (int) round($h * $s));
            if ($scaled !== false) {
                imagedestroy($im);
                $im = $scaled;
            }
        }
        imagesavealpha($im, true);
        ob_start();
        imagepng($im);
        $out = (string) ob_get_clean();
        imagedestroy($im);

        return $out !== '' ? $out : null;
    }
}
