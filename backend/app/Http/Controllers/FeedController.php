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
                .$this->tag('g:description', $this->description($p))
                .$this->tag('link', route('product.show', $p->slug))
                .$this->tag('g:image_link', $this->img($p))
                .$this->tag('g:price', number_format((float) $p->from_price, 2, '.', '').' USD')
                .$this->tag('g:availability', 'in_stock')
                .$this->tag('g:condition', 'new')
                .$this->tag('g:brand', 'RunMyPrint')
                .$this->tag('g:product_type', $p->category->name ?? '')
                .$this->googleCategory($p)
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

    /** A real product description — the PIM/SEO copy if present, else a clean, honest fallback. */
    private function description(Product $p): string
    {
        $seo = is_array($p->seo) ? ($p->seo['description'] ?? null) : null;
        $text = $p->description ?: ($seo ?: null);
        if (! $text) {
            $cat = strtolower($p->category->name ?? 'print product');
            $text = "{$p->name} — custom {$cat} printed by RunMyPrint. Premium quality, fast turnaround and a 100% satisfaction guarantee.";
        }
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));

        return mb_strlen($text) > 4900 ? mb_substr($text, 0, 4900) : $text; // Google caps description at 5000
    }

    /** Google product taxonomy for the categories we're confident about (helps Shopping categorise/bid). */
    private function googleCategory(Product $p): string
    {
        $map = ['Business Cards' => 'Office Supplies', 'Stationery' => 'Office Supplies'];
        $cat = $map[$p->category->name ?? ''] ?? null;

        return $cat ? $this->tag('g:google_product_category', $cat) : '';
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
