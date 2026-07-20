<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class FeedController extends Controller
{
    /** Google Shopping feed (RSS 2.0 + g: namespace) — variant-aware, see GoogleShoppingFeed. */
    public function google(): Response
    {
        return response((new \App\Support\GoogleShoppingFeed)->xml(),
            200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** RTB House product feed (generic XML). */
    public function rtbhouse(): Response
    {
        $items = '';
        foreach ($this->products() as $p) {
            $items .= '<product>'
                .$this->tag('id', $p->id)
                .$this->tag('url', route('product.show', $p->slug))
                .$this->tag('title', $p->name)
                .$this->tag('category', $p->category->name ?? '')
                .$this->tag('price', number_format((float) $p->from_price, 2, '.', ''))
                .$this->tag('currency', 'USD')
                .$this->tag('image', $this->img($p))
                .$this->tag('description', $p->tagline ?: $p->name)
                .'</product>';
        }

        return response('<?xml version="1.0" encoding="UTF-8"?><products>'.$items.'</products>',
            200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** RTB House BRAND-STORE feed: the pre-provisioned alias pool (see RtbStoreFeed).
     *  TSV (viteprint-proven structure); served under both .tsv and .csv paths. */
    public function rtbhouseStores(): Response
    {
        return response((new \App\Support\RtbStoreFeed)->csv(),
            200, ['Content-Type' => 'text/tab-separated-values; charset=UTF-8']);
    }

    private function products()
    {
        // services (ad credit etc.) are not shippable goods — keep them out of shopping feeds
        return Product::with('category')->where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('slug', '!=', 'services'))
            ->orderBy('sort_order')->get();
    }

    private function tag(string $name, $value): string
    {
        return "<{$name}>".htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8')."</{$name}>";
    }

    private function img(Product $p): string
    {
        if ($p->image_path && Storage::disk('public')->exists($p->image_path)) {
            return url(Storage::disk('public')->url($p->image_path));
        }

        return url('/');
    }
}
