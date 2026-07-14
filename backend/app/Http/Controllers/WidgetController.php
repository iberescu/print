<?php

namespace App\Http\Controllers;

use App\Jobs\BrandKit\BuildBrandKit;
use App\Models\BrandKit;
use App\Services\IpCompany;
use App\Support\LogoOnProducts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Embeddable "see your logo on products" widget — a small unit any site can drop in.
 *
 * Split, as designed:
 *   • Backend call  POST /api/widget  {url|logo|ip}  → { id }
 *   • Frontend call GET  /widget/{id}  (iframe)  or  GET /api/widget/{id} (JSON)
 *
 * Brand resolution: an explicit logo, else a website (logo fetched from the domain),
 * else the visitor IP (reverse DNS + IPinfo → domain → logo). It reuses the in-house
 * brand-kit engine (BuildBrandKit → product mockups). A click lands on runmyprint with
 * the brand adopted into the session — that's the lead.
 */
class WidgetController extends Controller
{
    /** POST /api/widget — resolve a brand and start (or reuse) its generation. */
    public function create(Request $request, IpCompany $ipco)
    {
        $data = $request->validate([
            'url'     => ['nullable', 'string', 'max:255'],
            'logo'    => ['nullable', 'string', 'max:4000000'], // data-uri or image URL
            'ip'      => ['nullable', 'ip'],
            'company' => ['nullable', 'string', 'max:120'],
        ]);

        $logoPath = ! empty($data['logo']) ? $this->storeLogo($data['logo']) : null;
        $website = ! empty($data['url']) ? $this->normalizeUrl($data['url']) : null;
        $domain = $website ? $this->host($website) : null;

        // No explicit brand → identify the company behind the visitor's IP.
        if (! $logoPath && ! $domain) {
            if ($domain = $ipco->domainFor($data['ip'] ?? $request->ip())) {
                $website = "https://{$domain}";
            }
        }

        // Reuse a recent kit for the same domain — instant render + no repeat generation.
        if ($domain && ($hit = BrandKit::where('source', 'widget')
            ->where('website', 'like', '%'.$domain.'%')
            ->where('created_at', '>', now()->subDays(30))->latest()->first())) {
            return response()->json(['id' => $hit->key]);
        }

        if (! $logoPath && $domain) {
            $logoPath = $this->logoForDomain($domain);
        }
        if (! $logoPath) {
            return response()->json(['id' => null, 'error' => 'no_brand'], 200);
        }

        $key = (string) Str::uuid();
        BrandKit::create([
            'key'       => $key,
            'source'    => 'widget',
            'status'    => 'pending',
            'logo_path' => $logoPath,
            'website'   => $website,
            'company'   => $data['company'] ?? ($domain ? Str::of(explode('.', $domain)[0])->title()->value() : null),
            'stages'    => [],
        ]);
        BuildBrandKit::dispatch($key);

        return response()->json(['id' => $key]);
    }

    /** GET /api/widget/{id} — the up-to-6 generated mockups (polled until ready). */
    public function products(string $id)
    {
        $kit = BrandKit::where('key', $id)->first();
        $products = $kit ? array_slice(LogoOnProducts::forKey($id), 0, 6) : [];
        $stage = $kit->stages['products'] ?? null;

        return response()->json([
            'ready'    => count($products) > 0,
            'done'     => in_array($stage, ['done', 'skipped'], true) || count($products) >= 6
                || ($kit && $kit->created_at?->lt(now()->subMinutes(4))),
            'products' => array_map(fn ($p) => [
                'img'  => $p['img'],
                'name' => $p['name'] ?? $p['label'] ?? 'Your logo',
            ], $products),
        ]);
    }

    /** GET /widget/{id} — the small embeddable UI (frameable anywhere). */
    public function frame(string $id)
    {
        return response()
            ->view('widget.frame', ['id' => $id])
            ->header('Content-Security-Policy', 'frame-ancestors *')
            ->header('X-Frame-Options', ''); // allow embedding on partner sites
    }

    /** GET /widget.js — one-line embed loader (reads data-id / data-url / data-logo). */
    public function loader()
    {
        $origin = rtrim(config('app.url'), '/');
        $js = <<<JS
(function(){
  var s = document.currentScript;
  if (!s) return;
  var origin = "{$origin}";
  function mount(id){
    if(!id) return;
    var f = document.createElement('iframe');
    f.src = origin + '/widget/' + id;
    f.width = '340'; f.height = '300'; f.loading = 'lazy';
    f.style.cssText = 'border:0;width:340px;max-width:100%;height:300px;overflow:hidden;';
    f.setAttribute('title','See your logo on products');
    s.parentNode.insertBefore(f, s.nextSibling);
  }
  var id = s.getAttribute('data-id');
  if (id) return mount(id);
  var body = {};
  if (s.getAttribute('data-url')) body.url = s.getAttribute('data-url');
  if (s.getAttribute('data-logo')) body.logo = s.getAttribute('data-logo');
  fetch(origin + '/api/widget', {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'}, body: JSON.stringify(body)})
    .then(function(r){return r.json();}).then(function(d){ mount(d && d.id); }).catch(function(){});
})();
JS;

        return response($js, 200, ['Content-Type' => 'application/javascript; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** GET /w/{id} — a widget click: adopt the brand into the session and land the lead. */
    public function land(string $id, Request $request)
    {
        if (BrandKit::where('key', $id)->exists()) {
            $request->session()->put('pqsg.key', $id);
        }

        return redirect()->to('/?from=widget');
    }

    // ---- helpers ------------------------------------------------------------

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        return preg_match('#^https?://#i', $url) ? $url : 'https://'.ltrim($url, '/');
    }

    private function host(string $url): ?string
    {
        $h = parse_url($this->normalizeUrl($url), PHP_URL_HOST);

        return $h ? preg_replace('/^www\./', '', strtolower($h)) : null;
    }

    /** Store a data-uri or remote image as the working logo; returns the public-disk path. */
    private function storeLogo(string $input): ?string
    {
        if (str_starts_with($input, 'data:')) {
            $b64 = substr($input, strpos($input, ',') + 1);

            return $this->put(base64_decode($b64) ?: '');
        }
        if (str_starts_with($input, 'http')) {
            return $this->fetchImage($input);
        }

        return null;
    }

    /**
     * A company logo from its domain, best quality first:
     *   1. parse the homepage HTML for a real logo (JSON-LD logo, apple-touch-icon,
     *      a "logo" <img>, og:image),
     *   2. the well-known /apple-touch-icon.png,
     *   3. Google's favicon service (small marks get super-resolved by enhanceLogo).
     */
    private function logoForDomain(string $domain): ?string
    {
        if ($p = $this->logoFromHtml($domain)) {
            return $p;
        }
        foreach (["https://{$domain}/apple-touch-icon.png", "https://{$domain}/apple-touch-icon-precomposed.png"] as $u) {
            if ($p = $this->fetchImage($u)) {
                return $p;
            }
        }

        return $this->fetchImage("https://www.google.com/s2/favicons?domain={$domain}&sz=256");
    }

    /** Fetch the homepage and pull the best logo candidate out of the markup. */
    private function logoFromHtml(string $domain): ?string
    {
        try {
            $r = Http::timeout(8)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36',
                'Accept'     => 'text/html',
            ])->get("https://{$domain}");
            if (! $r->successful()) {
                return null;
            }
            $html = $r->body();
            $base = (string) ($r->effectiveUri() ?? "https://{$domain}");
        } catch (\Throwable $e) {
            return null;
        }

        $cands = [];
        // Organization logo in JSON-LD (string or {url})
        if (preg_match('/"logo"\s*:\s*"([^"]+)"/i', $html, $m)) {
            $cands[] = $m[1];
        }
        if (preg_match('/"logo"\s*:\s*\{[^}]*?"url"\s*:\s*"([^"]+)"/i', $html, $m)) {
            $cands[] = $m[1];
        }
        // apple-touch-icon <link> (both attribute orders)
        array_push($cands, ...$this->linkHrefs($html, 'apple-touch-icon'));
        // an <img> that looks like the logo
        if (preg_match_all('/<img\b[^>]*>/i', $html, $imgs)) {
            foreach ($imgs[0] as $tag) {
                if (preg_match('/logo/i', $tag) && preg_match('/\bsrc=["\']([^"\']+)["\']/i', $tag, $s)) {
                    $cands[] = $s[1];
                }
            }
        }
        // og:image (often a brand image), then plain icons
        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)
            || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
            $cands[] = $m[1];
        }
        array_push($cands, ...$this->linkHrefs($html, 'icon'));

        foreach ($cands as $c) {
            if (($abs = $this->absUrl($c, $base, $domain)) && ($p = $this->fetchImage($abs))) {
                return $p;
            }
        }

        return null;
    }

    /** All href values of <link rel="…{keyword}…"> (handles rel/href in either order). */
    private function linkHrefs(string $html, string $keyword): array
    {
        $out = [];
        if (preg_match_all('/<link\b[^>]*>/i', $html, $links)) {
            foreach ($links[0] as $tag) {
                if (preg_match('/\brel=["\'][^"\']*'.preg_quote($keyword, '/').'[^"\']*["\']/i', $tag)
                    && preg_match('/\bhref=["\']([^"\']+)["\']/i', $tag, $h)) {
                    $out[] = $h[1];
                }
            }
        }

        return $out;
    }

    /** Resolve a possibly-relative asset URL against the page. */
    private function absUrl(string $u, string $base, string $domain): ?string
    {
        $u = trim(html_entity_decode($u));
        if ($u === '' || str_starts_with($u, 'data:')) {
            return null;
        }
        if (str_starts_with($u, '//')) {
            return 'https:'.$u;
        }
        if (preg_match('#^https?://#i', $u)) {
            return $u;
        }
        if (str_starts_with($u, '/')) {
            return 'https://'.$domain.$u;
        }

        return rtrim(preg_replace('#/[^/]*$#', '/', $base) ?: "https://{$domain}/", '/').'/'.ltrim($u, '/');
    }

    private function fetchImage(string $url): ?string
    {
        try {
            $r = Http::timeout(6)->get($url);
            if ($r->successful()
                && str_starts_with((string) $r->header('Content-Type'), 'image')
                && strlen($r->body()) > 600) { // skip Google's tiny default globe
                return $this->put($r->body());
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    private function put(string $bytes): ?string
    {
        if ($bytes === '') {
            return null;
        }
        // Favicons/logos are frequently SVG — the brand-kit engine + Gemini can't use
        // SVG, so rasterise to PNG here (mutool, same as the logo maker).
        if ($this->isSvg($bytes)) {
            $bytes = $this->rasterizeSvg($bytes);
            if (! $bytes) {
                return null;
            }
        }
        $path = 'widget-logos/'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /**
     * Inline <style> class rules as presentation attributes. mutool ignores CSS, so
     * Illustrator-exported SVGs (fills defined as `.stN{fill:#…}`) otherwise rasterise
     * to a black silhouette; this applies the real colours.
     */
    private function inlineSvgStyles(string $svg): string
    {
        if (! preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $svg, $blocks)) {
            return $svg;
        }
        $rules = [];
        foreach ($blocks[1] as $css) {
            if (preg_match_all('/\.([A-Za-z0-9_-]+)\s*\{([^}]*)\}/', $css, $m, PREG_SET_ORDER)) {
                foreach ($m as $r) {
                    $rules[$r[1]] = trim($r[2]);
                }
            }
        }
        if (! $rules) {
            return $svg;
        }

        return preg_replace_callback('/\bclass="([^"]+)"/', function ($mm) use ($rules) {
            $attrs = '';
            foreach (preg_split('/\s+/', trim($mm[1])) as $cls) {
                foreach (explode(';', $rules[$cls] ?? '') as $decl) {
                    if (! str_contains($decl, ':')) {
                        continue;
                    }
                    [$prop, $val] = array_map('trim', explode(':', $decl, 2));
                    if ($prop !== '' && $val !== '') {
                        $attrs .= ' '.$prop.'="'.$val.'"';
                    }
                }
            }

            return $mm[0].$attrs;
        }, $svg);
    }

    private function isSvg(string $bytes): bool
    {
        $head = ltrim(substr($bytes, 0, 400));

        return str_contains($head, '<svg') || (str_starts_with($head, '<?xml') && str_contains(substr($bytes, 0, 800), '<svg'));
    }

    private function rasterizeSvg(string $svg): ?string
    {
        $svg = $this->inlineSvgStyles($svg);
        $disk = Storage::disk('public');
        $tmpSvg = 'widget-logos/tmp-'.Str::uuid().'.svg';
        $tmpPng = str_replace('.svg', '.png', $tmpSvg);
        $disk->put($tmpSvg, $svg);

        $proc = new \Symfony\Component\Process\Process([
            'mutool', 'draw', '-o', $disk->path($tmpPng), '-w', '1024', '-h', '1024', '-c', 'rgba', $disk->path($tmpSvg),
        ]);
        $proc->run();

        $png = ($proc->isSuccessful() && $disk->exists($tmpPng) && str_starts_with((string) $disk->get($tmpPng), "\x89PNG"))
            ? $disk->get($tmpPng) : null;
        $disk->delete([$tmpSvg, $tmpPng]);

        return $png;
    }
}
