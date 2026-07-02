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
        $surface = null;
        foreach ($product->options as $opt) {
            $match = $opt->values->first(fn ($v) => in_array($v->id, $opts, true) && $v->surface);
            if ($match) {
                $surface = $match->surface;
                break;
            }
        }
        // no explicit selection → fall back to the default value's surface, then the product's
        if (! $surface && empty($opts)) {
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
        // product's default surface.
        if (! $surface && $opts) {
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
            'preview'          => ['nullable', 'string'],
            'brand'            => ['nullable', 'array'],
            'mode'             => ['nullable', 'string'],
            'quantityId'       => ['nullable', 'integer'],
            'optionValueIds'   => ['nullable', 'array'],
            'optionValueIds.*' => ['integer'],
        ]);

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
