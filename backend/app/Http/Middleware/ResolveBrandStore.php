<?php

namespace App\Http\Middleware;

use App\Models\BrandStore;
use Closure;
use Illuminate\Http\Request;

/**
 * Private Brand Stores ride wildcard subdomains over the SAME shop: when the
 * host is {sub}.<base>, resolve the store, bind it into the container (layout
 * banner + brand colors via the Inertia share; LogoOnProducts resolves the
 * store's kit) and gate every page behind the store session. Entry is either
 * an emailed login link (@their-domain only) or the owner's preview token
 * (?bs_preview=… — the cart iframe / "open your store" link).
 */
class ResolveBrandStore
{
    /** Paths that PLACE ORDERS — the only ones needing the employee session.
     *  Browsing the store is open; the banner explains who can order. */
    private const GATED_PATHS = ['checkout', 'checkout/*'];

    public function handle(Request $request, Closure $next)
    {
        $sub = $this->subdomain($request);
        if ($sub === null) {
            return $next($request); // the main shop — untouched
        }

        $store = BrandStore::with('brandKit')->where('subdomain', $sub)->first();
        abort_unless($store, 404);

        app()->instance('brandStore', $store);

        // owner/preview grant: the cart iframe & the buyer's link carry the token
        if ($request->query('bs_preview') && hash_equals((string) $store->token, (string) $request->query('bs_preview'))) {
            $request->session()->put("brandstore.auth.{$store->id}", 'owner');
        }

        if ($request->is(...self::GATED_PATHS) && ! $request->session()->has("brandstore.auth.{$store->id}")) {
            return redirect('/store-login');
        }

        return $next($request);
    }

    /** The store subdomain of this request's host, or null on the main shop. */
    private function subdomain(Request $request): ?string
    {
        $base = strtolower((string) (config('shop.brand_store_base')
            ?: preg_replace('/^www\./i', '', (string) parse_url((string) config('app.url'), PHP_URL_HOST))));
        $host = strtolower($request->getHost());

        if ($host === $base || $host === "www.{$base}" || ! str_ends_with($host, ".{$base}")) {
            return null;
        }
        $sub = substr($host, 0, -strlen(".{$base}"));
        if ($sub === '' || $sub === 'www' || str_contains($sub, '.')) {
            return null; // nested/reserved hosts are not stores
        }

        return $sub;
    }
}
