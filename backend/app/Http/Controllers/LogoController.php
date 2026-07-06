<?php

namespace App\Http\Controllers;

use App\Services\ReplicateClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * AI logo maker (req: standalone page + designer integration). Recraft SVG
 * via Replicate generates true vector logos; finishing a logo registers a
 * pqSmartGenerator capture so the buyer immediately sees it on products.
 */
class LogoController extends Controller
{
    private const STYLES = [
        'minimal' => 'minimal and geometric with generous negative space, flat design',
        'modern'  => 'modern and sleek with bold geometric forms, tech-forward, flat design',
        'classic' => 'classic and timeless with a refined serif wordmark, heritage feel',
        'playful' => 'playful and friendly with rounded approachable shapes',
        'elegant' => 'elegant and luxurious with thin refined line work',
        'bold'    => 'bold and strong with thick confident strokes, high impact',
    ];

    private const COLORS = [
        'brand-blue' => 'deep navy blue (#2b3b55) and vivid blue (#398aff)',
        'monochrome' => 'black and white monochrome',
        'warm'       => 'warm terracotta, amber and cream tones',
        'nature'     => 'forest green and natural earth tones',
        'colorful'   => 'a vibrant multi-colour palette',
    ];

    /** Different lockups per variant click, so regenerations feel fresh. */
    private const LOCKUPS = [
        'a simple distinctive emblem centred above the wordmark',
        'a compact emblem left of the wordmark in a horizontal lockup',
        'an abstract lettermark monogram built from the company initials, wordmark beneath',
        'a badge-style emblem that encloses the company name',
    ];

    public function show()
    {
        // Industry example gallery — real output of this generator, committed as assets.
        $samples = collect(Storage::disk('public')->files('logo-samples'))
            ->filter(fn ($f) => str_ends_with($f, '.svg'))
            ->map(fn ($f) => [
                'label' => Str::of(basename($f, '.svg'))->replace('-', ' ')->title()->toString(),
                'url'   => Storage::disk('public')->url($f),
            ])->values()->all();

        return Inertia::render('LogoMaker', [
            'heroImage'     => \App\Support\Img::url('heroes/logo-maker'),
            'showcaseImage' => \App\Support\Img::url('promos/logo-maker-showcase'),
            'styles'        => array_keys(self::STYLES),
            'colors'        => array_keys(self::COLORS),
            'samples'       => $samples,
            'pqsg'          => [
                'apiBase'   => config('shop.pqsg.api_base'),
                'widgetSrc' => config('shop.pqsg.widget_src'),
            ],
        ]);
    }

    public function generate(Request $request, ReplicateClient $replicate)
    {
        $data = $request->validate([
            'company'  => ['required', 'string', 'max:80'],
            'tagline'  => ['nullable', 'string', 'max:120'],
            'industry' => ['required', 'string', 'max:80'],
            'style'    => ['required', 'string', 'in:'.implode(',', array_keys(self::STYLES))],
            'color'    => ['required', 'string', 'in:'.implode(',', array_keys(self::COLORS))],
            'variant'  => ['nullable', 'integer', 'min:0'],
        ]);

        $svg = $replicate->generateSvg($this->prompt($data));

        // Recraft paints a full-canvas white rect first; strip it so the logo is
        // transparent — cleaner downloads, and no white tile when it lands on a
        // coloured design in the editor.
        $svg = preg_replace(
            '/<path(?=[^>]*\bd="M 0 0 L )[^>]*\bfill="rgb\(255,\s*255,\s*255\)"[^>]*>\s*<\/path>/',
            '',
            $svg,
            1
        );

        $path = 'logos/'.Str::uuid().'.svg';
        Storage::disk('public')->put($path, $svg);

        return response()->json([
            'path' => $path,
            'url'  => Storage::disk('public')->url($path),
        ]);
    }

    /** The buyer is happy: hand the logo to the upsell engine (logo on products). */
    public function finish(Request $request)
    {
        $data = $request->validate(['path' => ['required', 'string', 'regex:/^logos\/[0-9a-f-]+\.svg$/']]);
        abort_unless(Storage::disk('public')->exists($data['path']), 404);

        $key = session('pqsg.key') ?? (string) Str::uuid();
        session(['pqsg.key' => $key]);

        if (config('shop.pqsg.enabled')) {
            \App\Jobs\SendPqsgCapture::dispatchAfterResponse(
                key: $key,
                source: 'runmyprint-logo-maker',
                logoUrl: url(Storage::disk('public')->url($data['path'])),
                website: null,
            );
        }

        return response()->json(['key' => $key]);
    }

    private function prompt(array $d): string
    {
        $lockup = self::LOCKUPS[((int) ($d['variant'] ?? 0)) % count(self::LOCKUPS)];

        return 'Professional vector logo for "'.$d['company'].'"'
            .(filled($d['tagline'] ?? null) ? ' with the tagline "'.$d['tagline'].'"' : '')
            .', a '.$d['industry'].' business. '
            .ucfirst($lockup).', the wordmark reading exactly "'.$d['company'].'". '
            .'The emblem is a simplified flat pictogram of the single most iconic, instantly '
            .'recognisable object of the '.$d['industry'].' trade — pick it yourself (the way a pipe '
            .'wrench says plumbing, scales say law, a whisk says bakery, a lens says photography) — '
            .'never a generic abstract mark. '
            .'Style: '.self::STYLES[$d['style']].'. Colours: '.self::COLORS[$d['color']].' on a plain white background. '
            .'Professional brand identity design, crisp clean vector shapes, balanced composition, '
            .'no photorealism, no watermark.';
    }
}
