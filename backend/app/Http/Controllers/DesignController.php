<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Template;
use App\Services\Pricing;
use App\Support\PrintSpec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DesignController extends Controller
{
    public function show(Product $product, Request $request): Response
    {
        abort_unless($product->is_active, 404);
        $product->load(['category', 'surface', 'options.values.surface']);

        $opts = $this->optionIds($request);

        return Inertia::render('Editor', [
            'product'   => $product->only('id', 'name', 'slug', 'decoration'),
            'category'  => ['name' => $product->category->name, 'slug' => $product->category->slug],
            'mode'      => $request->query('mode') === 'upload' ? 'upload' : 'design',
            'templates' => $this->templatesFor($product),
            'template'  => $request->query('template'),   // pre-apply this template ref (from the gallery)
            'canvas'    => $this->geometry($product, $opts),
            'selection' => [
                'quantityId'     => ((int) $request->query('qty')) ?: null,
                'optionValueIds' => $opts,
            ],
        ]);
    }

    /** Template gallery shown before the editor (req: pick a template first). */
    public function templates(Product $product, Request $request): Response
    {
        abort_unless($product->is_active && $product->supports_design, 404);
        $product->load(['category', 'surface', 'options.values.surface']);

        $opts = $this->optionIds($request);

        return Inertia::render('Templates', [
            'product'   => $product->only('id', 'name', 'slug'),
            'category'  => ['name' => $product->category->name, 'slug' => $product->category->slug],
            'templates' => $this->templatesFor($product),
            'canvas'    => $this->geometry($product, $opts),
            'selection' => [
                'quantityId'     => ((int) $request->query('qty')) ?: null,
                'optionValueIds' => $opts,
            ],
        ]);
    }

    /** A surface assigned to the chosen option value (e.g. Format → A4) wins, then the
     *  product's default surface, then the size-option derived geometry. */
    private function geometry(Product $product, array $opts): array
    {
        $productCut = (bool) $product->surface?->cut_path;

        $surface = null;
        foreach ($product->options as $opt) {
            $match = $opt->values->first(fn ($v) => in_array($v->id, $opts, true) && $v->surface);
            // a PLAIN value surface must not flatten a die-cut product (door hangers,
            // die-cut postcards…) — only a value surface with its own cut (e.g. a
            // "Rounded" corners mapping) may replace the product's shape
            if ($match && ($match->surface->cut_path || ! $productCut)) {
                $surface = $match->surface;
                break;
            }
        }
        // no explicit selection → fall back to the default value's surface, then the product's
        if (! $surface && empty($opts) && ! $productCut) {
            foreach ($product->options as $opt) {
                $def = $opt->values->first(fn ($v) => $v->is_default && $v->surface);
                if ($def) {
                    $surface = $def->surface;
                    break;
                }
            }
        }

        // A selected size WITHOUT its own surface (crawled sizes often have none) must
        // still change the canvas — parse its label instead of silently keeping the
        // product's default surface. Never flatten a die-cut product this way.
        if (! $surface && $opts && ! $productCut) {
            foreach ($product->options as $opt) {
                if (! preg_match('/size|format|dimension/i', $opt->name)) {
                    continue;
                }
                $val = $opt->values->first(fn ($v) => in_array($v->id, $opts, true));
                if ($val && PrintSpec::parsesAsSize($val->label, $product)) {
                    return PrintSpec::canvas($product, $opts);
                }
            }
        }
        $surface ??= $product->surface;

        return $surface ? PrintSpec::fromSurface($surface) : PrintSpec::canvas($product, $opts);
    }

    public function templateData(Template $template): JsonResponse
    {
        return response()->json(['data' => $template->data]);
    }

    /** Stash the finished design, then show the review step (PRG). */
    public function review(Product $product, Request $request): RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $data = $request->validate([
            'preview'           => ['nullable', 'string', 'max:4000000'],
            'brand'             => ['nullable', 'array'],
            // NOTE: once one nested rule exists, validated() drops un-ruled siblings —
            // every brand field the funnel uses must be listed here.
            'brand.logo'        => ['nullable', 'string', 'max:4000000'],
            'brand.companyName' => ['nullable', 'string', 'max:300'],
            'brand.name'        => ['nullable', 'string', 'max:300'],
            'brand.title'       => ['nullable', 'string', 'max:300'],
            'brand.email'       => ['nullable', 'string', 'max:300'],
            'brand.phone'       => ['nullable', 'string', 'max:300'],
            'brand.url'         => ['nullable', 'string', 'max:300'],
            'mode'              => ['nullable', 'string', 'max:20'],
            'quantityId'        => ['nullable', 'integer'],
            'optionValueIds'    => ['nullable', 'array'],
            'optionValueIds.*'  => ['integer'],
        ]);

        // Persist the big base64 blobs to disk and keep only URLs in the session —
        // otherwise every request drags megabytes through the DB session store.
        $data['preview'] = \App\Support\PreviewStore::persist($data['preview'] ?? null);
        if (isset($data['brand']['logo'])) {
            $data['brand']['logo'] = \App\Support\PreviewStore::persist($data['brand']['logo']);
        }

        // Register the design with the pqSmartGenerator upsell engine — async,
        // after this response is sent, so the shopper never waits on it.
        $data['pqsgKey'] = $this->dispatchPqsgCapture($data['brand'] ?? null, $data['preview'] ?? null);

        session(['design.review' => $data + ['product' => $product->slug]]);

        return redirect()->route('design.review', $product);
    }

    public function showReview(Product $product, Pricing $pricing): Response|RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $d = session('design.review');
        if (! $d || ($d['product'] ?? null) !== $product->slug) {
            return redirect()->route('design.start', $product);
        }

        $product->load('category');
        $quote = $pricing->quote($product, $d['quantityId'] ?? null, $d['optionValueIds'] ?? []);

        return Inertia::render('Review', [
            'product'  => $product->only('id', 'name', 'slug'),
            'category' => ['name' => $product->category->name, 'slug' => $product->category->slug],
            'preview'  => $d['preview'] ?? null,
            'mode'     => $d['mode'] ?? 'design',
            'design'   => [
                'brand'          => $d['brand'] ?? null,
                'quantityId'     => $d['quantityId'] ?? null,
                'optionValueIds' => $d['optionValueIds'] ?? [],
            ],
            'quote'    => $quote,
        ]);
    }

    /**
     * Queue a pqSmartGenerator capture from the designer's brand fields (the logo
     * placeholder image and the company-website text). Seed placeholders are
     * skipped — the engine only gets real customer data. Returns our correlation
     * key (reused across upload + designer flows within the session), or null
     * when there is nothing worth sending.
     */
    private function dispatchPqsgCapture(?array $brand, ?string $preview = null): ?string
    {
        if (! config('shop.pqsg.enabled')) {
            return null;
        }

        $logo = $brand['logo'] ?? null;
        // the seeded "YOUR LOGO HERE" placeholder is not a customer logo
        if ($logo && str_contains($logo, 'logo-placeholder')) {
            $logo = null;
        }
        if ($logo && ! str_starts_with($logo, 'http')) {
            $logo = url($logo);
        }

        $website = trim((string) ($brand['url'] ?? ''));
        // the seeded placeholder URL is not a customer website
        if (preg_match('/^(www\.)?yourcompany\.com$/i', $website)) {
            $website = '';
        }
        if ($website !== '' && ! preg_match('#^https?://#i', $website)) {
            $website = 'https://'.$website;
        }

        // No real logo/website? The approved design itself carries the brand —
        // the engine extracts it from image_url (updated API), so placeholder
        // designs get the gallery too instead of silently skipping the steps.
        $image = $preview && str_starts_with($preview, '/') ? url($preview) : $preview;

        $key = session('pqsg.key');   // set earlier if the upload flow already captured
        if (! $logo && $website === '' && ! $image && ! $key) {
            return null;              // nothing to send, nothing already in flight
        }

        $key ??= (string) \Illuminate\Support\Str::uuid();
        session(['pqsg.key' => $key]);

        if ($logo || $website !== '' || $image) {
            \App\Jobs\SendPqsgCapture::dispatchAfterResponse(
                key: $key,
                source: 'runmyprint-designer',
                logoUrl: $logo,
                website: $website !== '' ? $website : null,
                imageUrl: $logo ? null : $image, // the design preview is the fallback brand source
            );
        }

        return $key;
    }

    /** @return int[] */
    private function optionIds(Request $request): array
    {
        return array_values(array_filter(array_map('intval', (array) $request->query('opts', []))));
    }

    /** Templates for the product's category (business-card designs exist today). */
    private function templatesFor(Product $product): array
    {
        // Only the columns the picker needs — never the heavy `data` (embedded base64 images).
        return Template::where('is_active', true)
            ->where('category', $product->category->slug)
            ->orderByDesc('score')->orderBy('sort_order')
            ->take(60)->get(['ref', 'name', 'preview_path'])
            ->map(fn (Template $t) => ['ref' => $t->ref, 'name' => $t->name, 'preview' => $t->previewUrl()])
            ->all();
    }
}
