<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\GeminiClient;
use App\Support\Img;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Material/finish preview images for the final-step page (req: change material
 * after design review). One macro shot per option value of every option that
 * doesn't affect the design surface — surface-bound options (size, corners, …)
 * are locked after review and never shown there.
 */
class GenerateOptionPreviews extends Command
{
    protected $signature = 'options:previews
        {--product= : only this product slug}
        {--limit=0 : max images to generate (0 = no limit)}
        {--all : also cover non-material groups (colors, counts, …) — default is material-like only}
        {--force : regenerate even if an image already exists}';

    protected $description = 'Generate material/finish preview images for the final step with Gemini (nano banana 2)';

    /** Groups that read as a material/finish — the ones a texture macro shot suits.
     *  Colors already render as swatch tiles and counts as plain cards. */
    private const MATERIAL_NAMES = '/paper|stock|material|finish|lamination|foil|quality|cover|substrate|coating|texture/i';

    /** Every value in a group shares this composition, so the material is the only thing that changes. */
    private const STYLE = 'Extreme macro close-up photograph, corner of the printed piece filling the frame at a slight '
        .'three-quarter angle, soft directional studio light raking across the surface to reveal the paper texture and '
        .'sheen, shallow depth of field, seamless light-grey studio background, premium print-shop e-commerce style. '
        .'The piece carries only a minimal abstract deep-navy and white geometric design — no readable text, no logo, '
        .'no watermark, no hands, no props.';

    /** Texture cues keyed by label keyword — the visible difference between stocks. */
    private const TEXTURES = [
        'soft'      => 'a velvety suede-like soft-touch lamination that absorbs light with zero glare, colours slightly muted and deep',
        'velvet'    => 'a velvety suede-like soft-touch lamination that absorbs light with zero glare, colours slightly muted and deep',
        'gloss'     => 'a high-gloss coated surface with crisp mirror-like highlights and vivid saturated colour',
        'matte'     => 'a smooth flat matte coating with a soft even non-reflective finish',
        'recycled'  => 'natural recycled stock with visible fibre flecks and a warm speckled off-white tone',
        'kraft'     => 'raw brown kraft board with coarse natural fibres and an earthy unbleached tone',
        'linen'     => 'a fine woven linen emboss with a visible criss-cross fabric texture',
        'pearl'     => 'a subtle pearlescent shimmer that catches the light with an iridescent sheen',
        'metallic'  => 'a subtle metallic shimmer that catches the light',
        'uncoated'  => 'natural uncoated paper with a visible tooth, ink sitting softly matte on the surface',
        'premium'   => 'an extra-thick rigid board with a clearly visible layered edge and substantial heft',
        'plus'      => 'an extra-thick rigid board with a clearly visible layered edge and substantial heft',
        'thick'     => 'an extra-thick rigid board with a clearly visible layered edge and substantial heft',
        'standard'  => 'a clean smooth professional print surface',
    ];

    /** Apparel decoration groups (e.g. "Decoration Technology") — previewed on FABRIC with the method's
     *  texture. Kept narrow ("decoration") so generic "printing" options on non-apparel products (card
     *  holders, flags, tablecloths) are NOT given a garment preview. */
    private const DECORATION_NAMES = '/decoration/i';

    private const DECORATION_STYLE = 'Extreme macro close-up photograph of a section of a plain cotton garment '
        .'(t-shirt / hoodie fleece) filling the frame at a slight three-quarter angle, soft directional studio '
        .'light raking across the weave to reveal the fabric texture and the decoration finish, shallow depth of '
        .'field, seamless light-grey studio background, premium apparel e-commerce style. A small minimal abstract '
        .'deep-navy and white geometric emblem is applied to the fabric via this method — no readable text, no '
        .'logo, no watermark, no hands, no props.';

    /** Decoration-method texture cues keyed by label keyword. */
    private const DECORATION_TEXTURES = [
        'direct-to-garment' => 'a Direct-to-Garment (DTG) print: a soft, finely-detailed full-colour design absorbed INTO the fabric weave, smooth and flat with almost no hand-feel and photographic detail',
        'dtg'               => 'a Direct-to-Garment (DTG) print: a soft, finely-detailed full-colour design absorbed INTO the fabric weave, smooth and flat with almost no hand-feel and photographic detail',
        'screen'            => 'a screen print: a bold opaque layer of plastisol ink sitting slightly RAISED on top of the fabric with a smooth semi-matte finish and vivid, flat, solid colours',
        'embroider'         => 'machine embroidery: raised satin and fill stitches in glossy thread with clearly visible stitch direction and a tactile 3D texture',
        'vinyl'             => 'heat-transfer vinyl (HTV): a smooth solid vinyl layer with crisp clean-cut edges sitting on top of the fabric, with a slight sheen',
        'transfer'          => 'a heat transfer: a thin smooth printed layer bonded onto the fabric surface with clean edges',
        'sublimation'       => 'dye-sublimation: vivid full-colour graphics dyed permanently into the fibres, completely flat with zero hand-feel',
    ];

    public function handle(GeminiClient $gemini): int
    {
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');
        $disk = Storage::disk('public');
        $count = 0;

        $products = Product::with(['options.values', 'category'])
            ->where('is_active', true)
            ->when($this->option('product'), fn ($q, $slug) => $q->where('slug', $slug))
            ->orderBy('sort_order')->get();

        foreach ($products as $product) {
            foreach ($product->options as $option) {
                if ($option->affectsSurface() || $option->values->count() < 2) {
                    continue;
                }
                if (! $this->option('all')
                    && ! preg_match(self::MATERIAL_NAMES, $option->name)
                    && ! preg_match(self::DECORATION_NAMES, $option->name)) {
                    continue;
                }

                foreach ($option->values as $value) {
                    if ($limit > 0 && $count >= $limit) {
                        $this->info("Limit reached. Generated {$count} image(s).");

                        return self::SUCCESS;
                    }

                    $path = sprintf('option-previews/%s/%s-%s.webp',
                        $product->slug, Str::slug($option->name), Str::slug($value->label));

                    if (! $force && $disk->exists($path)) {
                        if ($value->image_path !== $path) {
                            $value->update(['image_path' => $path]);
                        }
                        $this->line("skip  {$path} (exists)");
                        continue;
                    }

                    try {
                        $img = $gemini->generateImage($this->prompt($product, $option, $value));
                        $disk->put($path, Img::webp($img['data'], 640));
                        $value->update(['image_path' => $path]);
                        $count++;
                        $this->info("ok    {$path}");
                    } catch (Throwable $e) {
                        $this->error("fail  {$path}: ".$e->getMessage());
                    }

                    usleep(800_000); // be gentle on rate limits
                }
            }
        }

        $this->newLine();
        $this->info("Done. Generated {$count} image(s).");

        return self::SUCCESS;
    }

    private function prompt(Product $product, $option, $value): string
    {
        $item = Str::singular($product->name);

        // Decoration / print-method groups render on FABRIC with the method's own texture.
        if (preg_match(self::DECORATION_NAMES, $option->name)) {
            $tex = collect(self::DECORATION_TEXTURES)
                ->first(fn ($t, $key) => Str::contains(Str::lower($value->label), $key))
                ?? 'the characteristic finish of this decoration method';

            return "Apparel decoration-method preview for an online print shop: a cotton {$item} decorated with the "
                ."\"{$value->label}\" method. Show {$tex}. ".self::DECORATION_STYLE;
        }

        $texture = collect(self::TEXTURES)
            ->first(fn ($t, $key) => Str::contains(Str::lower($value->label), $key))
            ?? 'the distinctive surface character of this stock';

        $specs = collect($value->attributes ?? [])
            ->map(fn ($a) => trim(($a['name'] ?? '').' '.($a['value'] ?? '')))
            ->filter()->implode(', ');

        return "Product material preview for an online print shop: a {$item} in the "
            ."\"{$value->label}\" {$option->name} variant. "
            .($value->description ? "{$value->description}. " : '')
            .($specs ? "Physical specs: {$specs}. " : '')
            ."Show {$texture}. ".self::STYLE;
    }
}
