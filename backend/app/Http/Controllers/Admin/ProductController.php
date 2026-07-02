<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Surface;
use App\Support\Img;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->withCount(['options', 'quantities'])
            ->orderBy('category_id')->orderBy('sort_order')->get()
            ->map(fn (Product $p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'slug'      => $p->slug,
                'category'  => $p->category?->name,
                'fromPrice' => (float) $p->from_price,
                'options'   => $p->options_count,
                'tiers'     => $p->quantities_count,
                'active'    => (bool) $p->is_active,
                'image'     => Img::url($p->image_path),
            ]);

        return Inertia::render('Admin/Products/Index', [
            'products'   => $products,
            'categories' => Category::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:160'],
            'categoryId' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $product = Product::create([
            'category_id' => $data['categoryId'],
            'name'        => $data['name'],
            'slug'        => $this->uniqueSlug($data['name']),
            'from_price'  => 0,
            'is_active'   => false,
            'sort_order'  => (int) Product::max('sort_order') + 1,
        ]);

        return redirect()->route('admin.products.edit', $product)
            ->with('success', 'Product created — add options and pricing.');
    }

    public function edit(Product $product)
    {
        $product->load(['options.values', 'quantities']);

        return Inertia::render('Admin/Products/Edit', [
            'product' => [
                'id'             => $product->id,
                'name'           => $product->name,
                'slug'           => $product->slug,
                'categoryId'     => $product->category_id,
                'tagline'        => $product->tagline,
                'description'    => $product->description,
                'fromPrice'      => (float) $product->from_price,
                'badge'          => $product->badge,
                'supportsDesign' => (bool) $product->supports_design,
                'supportsUpload' => (bool) $product->supports_upload,
                'isActive'       => (bool) $product->is_active,
                'featured'       => (bool) $product->featured,
                'surfaceId'      => $product->surface_id,
                'image'          => Img::url($product->image_path),
                'seo'            => [
                    'description' => $product->seo['description'] ?? '',
                    'details'     => array_values($product->seo['details'] ?? []),
                    'faq'         => array_values($product->seo['faq'] ?? []),
                ],
            ],
            'categories' => Category::orderBy('sort_order')->get(['id', 'name']),
            'surfaces'   => Surface::orderBy('name')->get(['id', 'name']),
            'options'    => $product->options->map(fn ($o) => [
                'name'     => $o->name,
                'type'     => $o->type,
                'required' => (bool) $o->required,
                'values'   => $o->values->map(fn ($v) => [
                    'label'       => $v->label,
                    'priceDelta'  => (float) $v->price_delta,
                    'description' => $v->description,
                    'badge'       => $v->badge,
                    'swatch'      => $v->swatch,
                    'isDefault'   => (bool) $v->is_default,
                    'attributes'  => $v->attributes ?? [],
                    'surfaceId'   => $v->surface_id,
                ]),
            ]),
            'quantities' => $product->quantities->map(fn ($q) => [
                'quantity'   => $q->quantity,
                'unitPrice'  => (float) $q->unit_price,
                'totalPrice' => $q->total_price !== null ? (float) $q->total_price : null,
                'isDefault'  => (bool) $q->is_default,
            ]),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);

        DB::transaction(function () use ($product, $data) {
            $product->update([
                'category_id'     => $data['categoryId'],
                'name'            => $data['name'],
                'slug'            => $data['slug'],
                'tagline'         => $data['tagline'] ?? null,
                'description'     => $data['description'] ?? null,
                'from_price'      => $data['fromPrice'] ?? 0,
                'badge'           => $data['badge'] ?? null,
                'supports_design' => $data['supportsDesign'] ?? false,
                'supports_upload' => $data['supportsUpload'] ?? false,
                'is_active'       => $data['isActive'] ?? false,
                'featured'        => $data['featured'] ?? false,
                'surface_id'      => $data['surfaceId'] ?? null,
                'seo'             => $this->cleanSeo($data['seo'] ?? null),
            ]);

            // Rebuild options/values/quantities (FK cascade clears child values).
            $product->options()->delete();
            foreach (array_values($data['options'] ?? []) as $oi => $opt) {
                $option = $product->options()->create([
                    'name'       => $opt['name'],
                    'type'       => $opt['type'] ?? 'select',
                    'required'   => $opt['required'] ?? true,
                    'sort_order' => $oi,
                ]);
                foreach (array_values($opt['values'] ?? []) as $vi => $val) {
                    $option->values()->create([
                        'label'       => $val['label'],
                        'price_delta' => $val['priceDelta'] ?? 0,
                        'description' => $val['description'] ?? null,
                        'badge'       => $val['badge'] ?? null,
                        'swatch'      => $val['swatch'] ?? null,
                        'is_default'  => $val['isDefault'] ?? false,
                        'attributes'  => $this->cleanAttributes($val['attributes'] ?? []),
                        'surface_id'  => $val['surfaceId'] ?? null,
                        'sort_order'  => $vi,
                    ]);
                }
            }

            $product->quantities()->delete();
            foreach (array_values($data['quantities'] ?? []) as $qi => $q) {
                $product->quantities()->create([
                    'quantity'    => $q['quantity'],
                    'unit_price'  => $q['unitPrice'] ?? 0,
                    'total_price' => $q['totalPrice'] ?? null,
                    'is_default'  => $q['isDefault'] ?? false,
                    'sort_order'  => $qi,
                ]);
            }
        });

        Cache::forget('nav.categories');

        return redirect()->route('admin.products.edit', $product)->with('success', 'Product saved.');
    }

    public function destroy(Product $product)
    {
        $product->delete(); // cascades to options/values/quantities
        Cache::forget('nav.categories');

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    private function validated(Request $request, Product $product): array
    {
        return $request->validate([
            'name'                          => ['required', 'string', 'max:160'],
            'slug'                          => ['required', 'string', 'max:160', Rule::unique('products', 'slug')->ignore($product->id)],
            'categoryId'                    => ['required', 'integer', 'exists:categories,id'],
            'tagline'                       => ['nullable', 'string', 'max:255'],
            'description'                   => ['nullable', 'string'],
            'fromPrice'                     => ['nullable', 'numeric', 'min:0'],
            'badge'                         => ['nullable', 'string', 'max:60'],
            'supportsDesign'                => ['boolean'],
            'supportsUpload'                => ['boolean'],
            'isActive'                      => ['boolean'],
            'featured'                      => ['boolean'],
            'surfaceId'                     => ['nullable', 'integer', 'exists:surfaces,id'],
            'seo'                           => ['nullable', 'array'],
            'seo.description'               => ['nullable', 'string', 'max:4000'],
            'seo.details'                   => ['array'],
            'seo.details.*'                 => ['nullable', 'string', 'max:200'],
            'seo.faq'                       => ['array'],
            'seo.faq.*.q'                   => ['nullable', 'string', 'max:300'],
            'seo.faq.*.a'                   => ['nullable', 'string', 'max:2000'],
            'options'                       => ['array'],
            'options.*.name'                => ['required', 'string', 'max:120'],
            'options.*.type'                => ['required', 'in:select,radio,swatch'],
            'options.*.required'            => ['boolean'],
            'options.*.values'              => ['array'],
            'options.*.values.*.label'      => ['required', 'string', 'max:120'],
            'options.*.values.*.priceDelta' => ['nullable', 'numeric'],
            'options.*.values.*.description' => ['nullable', 'string', 'max:255'],
            'options.*.values.*.badge'      => ['nullable', 'string', 'max:60'],
            'options.*.values.*.swatch'     => ['nullable', 'string', 'max:20'],
            'options.*.values.*.isDefault'  => ['boolean'],
            'options.*.values.*.surfaceId'  => ['nullable', 'integer', 'exists:surfaces,id'],
            'options.*.values.*.attributes'         => ['array'],
            'options.*.values.*.attributes.*.name'  => ['nullable', 'string', 'max:80'],
            'options.*.values.*.attributes.*.value' => ['nullable', 'string', 'max:160'],
            'quantities'                    => ['array'],
            'quantities.*.quantity'         => ['required', 'integer', 'min:1'],
            'quantities.*.unitPrice'        => ['nullable', 'numeric', 'min:0'],
            'quantities.*.totalPrice'       => ['nullable', 'numeric', 'min:0'],
            'quantities.*.isDefault'        => ['boolean'],
        ]);
    }

    /** Normalise SEO content: trim, drop blank details/FAQ, null when empty. */
    private function cleanSeo($seo): ?array
    {
        if (! is_array($seo)) {
            return null;
        }

        $description = trim((string) ($seo['description'] ?? ''));
        $details = array_values(array_filter(
            array_map(fn ($d) => trim((string) $d), (array) ($seo['details'] ?? [])),
            fn ($d) => $d !== '',
        ));
        $faq = array_values(array_filter(
            array_map(fn ($f) => [
                'q' => trim((string) ($f['q'] ?? '')),
                'a' => trim((string) ($f['a'] ?? '')),
            ], (array) ($seo['faq'] ?? [])),
            fn ($f) => $f['q'] !== '' && $f['a'] !== '',
        ));

        if ($description === '' && ! $details && ! $faq) {
            return null;
        }

        return ['description' => $description, 'details' => $details, 'faq' => $faq];
    }

    /** Drop blank spec rows; keep ordered [{name,value}]. */
    private function cleanAttributes($attrs): array
    {
        return array_values(array_filter(
            array_map(fn ($a) => [
                'name'  => trim((string) ($a['name'] ?? '')),
                'value' => trim((string) ($a['value'] ?? '')),
            ], (array) $attrs),
            fn ($a) => $a['name'] !== '' || $a['value'] !== '',
        ));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 2;
        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
