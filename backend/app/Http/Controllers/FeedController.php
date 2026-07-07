<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class FeedController extends Controller
{
    /** Google Shopping feed (RSS 2.0 + g: namespace). */
    public function google(): Response
    {
        $items = '';
        foreach ($this->products() as $p) {
            $items .= '<item>'
                .$this->tag('g:id', $p->id)
                .$this->tag('g:title', $p->name)
                .$this->tag('g:description', $p->description ?: $p->tagline)
                .$this->tag('link', route('product.show', $p->slug))
                .$this->tag('g:image_link', $this->img($p))
                .$this->tag('g:price', number_format((float) $p->from_price, 2, '.', '').' USD')
                .$this->tag('g:availability', 'in_stock')
                .$this->tag('g:condition', 'new')
                .$this->tag('g:brand', 'RunMyPrint')
                .$this->tag('g:product_type', $p->category->name ?? '')
                .$this->tag('g:identifier_exists', 'no')
                .'</item>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>'
            .$this->tag('title', 'RunMyPrint')
            .$this->tag('link', url('/'))
            .$this->tag('description', 'Custom printing for business')
            .$items
            .'</channel></rss>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
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
