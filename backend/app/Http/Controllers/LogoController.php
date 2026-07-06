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

    /** Four concept lanes — every generation round covers all of them:
     *  0 industry pictogram · 1 name-literal motif · 2 abstract monogram ·
     *  3 tagline-inspired (falling back to a name+industry fusion). */
    private const CONCEPTS = 4;

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

        // Async: hand back a prediction id immediately — a 15–60 s long-poll dies
        // on mobile Safari (fetch cap + backgrounding), status polling doesn't.
        return response()->json(['id' => $replicate->createSvgPrediction($this->prompt($data))]);
    }

    /** Poll a prediction; when done, store the (transparent) SVG and return it. */
    public function status(string $id, ReplicateClient $replicate)
    {
        abort_unless(preg_match('/^[0-9a-z]{10,40}$/i', $id), 404);

        try {
            $p = $replicate->getPrediction($id);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            abort_if($e->response?->status() === 404, 404); // unknown id — not our 500
            throw $e;
        }
        if (in_array($p['status'], ['failed', 'canceled'], true)) {
            return response()->json(['message' => 'generation failed'.($p['error'] ? ': '.$p['error'] : '')], 422);
        }
        if ($p['status'] !== 'succeeded' || ! $p['url']) {
            return response()->json(['done' => false]);
        }

        $svg = $replicate->fetchSvg($p['url']);

        // Recraft paints a full-canvas background rect first (usually white,
        // sometimes cream); strip any near-white one so the logo is transparent —
        // cleaner downloads, and no pale tile when it lands on a coloured design.
        $svg = preg_replace_callback(
            '/<path(?=[^>]*\bd="M 0 0 L )[^>]*\bfill="rgb\((\d+),\s*(\d+),\s*(\d+)\)"[^>]*>\s*<\/path>/',
            fn ($m) => (min((int) $m[1], (int) $m[2], (int) $m[3]) >= 235) ? '' : $m[0],
            $svg,
            1
        );

        $path = 'logos/'.Str::uuid().'.svg';
        Storage::disk('public')->put($path, $svg);

        return response()->json([
            'done' => true,
            'path' => $path,
            'url'  => Storage::disk('public')->url($path),
        ]);
    }

    /** Serve the SVG as an attachment: the save prompt appears and the page
     *  stays put — anchor/blob downloads navigate away on iOS Safari. */
    public function download(Request $request)
    {
        $data = $request->validate(['path' => ['required', 'string', 'regex:/^logos\/[0-9a-f-]+\.svg$/']]);
        abort_unless(Storage::disk('public')->exists($data['path']), 404);

        return Storage::disk('public')->download($data['path'], 'logo.svg', ['Content-Type' => 'image/svg+xml']);
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
        $company = $d['company'];
        $industry = $d['industry'];
        $tagline = trim((string) ($d['tagline'] ?? ''));

        $concept = match (((int) ($d['variant'] ?? 0)) % self::CONCEPTS) {
            // the trade's most iconic object
            0 => 'The emblem is a simplified flat pictogram of the single most iconic, instantly '
                .'recognisable object of the '.$industry.' trade — pick it yourself (the way a pipe '
                .'wrench says plumbing, scales say law, a whisk says bakery, a lens says photography) — '
                .'never a generic abstract mark.',
            // the imagery hiding inside the name itself: CloudLab → cloud, MyPrint → print
            1 => 'The emblem visualises the literal imagery inside the company name itself: read the '
                .'words and roots in "'.$company.'" and turn the most visual one into a simplified flat '
                .'pictogram (the way "CloudLab" suggests a cloud, "MyPrint" suggests a printed sheet, '
                .'"Driftwood" suggests a wave-worn branch). Ignore the industry for the emblem.',
            // deliberately abstract
            2 => 'The emblem is a distinctive ABSTRACT geometric mark — interlocking shapes, negative '
                .'space or a stylised monogram built from the initials of "'.$company.'". Deliberately '
                .'non-literal: no recognisable objects.',
            // the tagline's promise (or fuse name + industry when there is none)
            default => $tagline !== ''
                ? 'The emblem visualises the promise of the tagline "'.$tagline.'": pick its most '
                    .'visual idea and reduce it to a simplified flat pictogram.'
                : 'The emblem fuses the imagery of the name "'.$company.'" with the most iconic object '
                    .'of the '.$industry.' trade into ONE clean combined pictogram.',
        };

        return 'Professional vector logo for "'.$company.'"'
            .($tagline !== '' ? ' with the tagline "'.$tagline.'"' : '')
            .', a '.$industry.' business. '
            .'A simple distinctive emblem centred above the wordmark, the wordmark reading exactly "'.$company.'". '
            .$concept.' '
            .'Style: '.self::STYLES[$d['style']].'. Colours: '.self::COLORS[$d['color']].' on a plain white background. '
            .'Professional brand identity design, crisp clean vector shapes, balanced composition, '
            .'no photorealism, no watermark.';
    }
}
