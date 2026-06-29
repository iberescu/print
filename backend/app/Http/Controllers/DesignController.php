<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DesignController extends Controller
{
    public function show(Product $product, Request $request): Response
    {
        abort_unless($product->is_active, 404);
        $product->load('category');

        $opts = array_values(array_filter(array_map('intval', (array) $request->query('opts', []))));

        return Inertia::render('Editor', [
            'product'   => $product->only('id', 'name', 'slug'),
            'category'  => ['name' => $product->category->name, 'slug' => $product->category->slug],
            'mode'      => $request->query('mode') === 'upload' ? 'upload' : 'design',
            'templates' => $this->templatesFor($product),
            'selection' => [
                'quantityId'     => ((int) $request->query('qty')) ?: null,
                'optionValueIds' => $opts,
            ],
        ]);
    }

    public function templateData(Template $template): JsonResponse
    {
        return response()->json(['data' => $template->data]);
    }

    /** Templates are business-card designs for now. */
    private function templatesFor(Product $product): array
    {
        if ($product->category->slug !== 'business-cards') {
            return [];
        }

        return Template::where('is_active', true)
            ->orderByDesc('score')->orderBy('sort_order')
            ->take(60)->get()
            ->map(fn (Template $t) => ['ref' => $t->ref, 'name' => $t->name, 'preview' => $t->previewUrl()])
            ->all();
    }
}
