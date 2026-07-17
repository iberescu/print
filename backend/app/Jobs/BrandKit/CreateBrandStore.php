<?php

namespace App\Jobs\BrandKit;

use App\Models\BrandKit;
use App\Models\BrandStore;
use App\Support\BrandKitSpec;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Private Brand Store: once a capture has a LOGO and a URL, and the product
 * mockups + crawl summary are both done, spin up {company}.runmyprint.com —
 * the main shop re-skinned with the customer's brand (colors from the crawl/
 * logo, their mockups on the home page), gated to @their-domain login links.
 * Called from every completion point via consider(); creates at most once.
 */
class CreateBrandStore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** Junk/placeholder domains never get a store. */
    private const BLOCKED_DOMAINS = [
        'example.com', 'example.org', 'yourcompany.com', 'your-company.com',
        'company.com', 'acme.com', 'test.com', 'localhost',
    ];

    private const RESERVED_SUBDOMAINS = [
        'www', 'api', 'mail', 'admin', 'app', 'shop', 'store', 'in', 'send',
        'staging', 'dev', 'cdn', 'static', 'assets', 'blog', 'status',
    ];

    public function __construct(public string $key)
    {
        $this->onQueue('brandkit');
    }

    /** Cheap completion check — dispatch exactly once, when everything's ready. */
    public static function consider(string $key): void
    {
        $kit = BrandKit::where('key', $key)->first();
        if (! $kit || ! self::ready($kit)) {
            return;
        }
        if (BrandStore::where('brand_kit_id', $kit->id)->exists()) {
            return;
        }
        // many product jobs finish near-simultaneously — only one dispatches
        if (! Cache::add("brandstore:dispatch:{$kit->id}", 1, 3600)) {
            return;
        }
        self::dispatch($key);
    }

    public function handle(): void
    {
        $kit = BrandKit::where('key', $this->key)->first();
        if (! $kit || ! self::ready($kit) || BrandStore::where('brand_kit_id', $kit->id)->exists()) {
            return;
        }

        $domain = self::emailDomain($kit);
        if (! $domain || in_array($domain, self::BLOCKED_DOMAINS, true)) {
            return;
        }

        $store = BrandStore::create([
            'brand_kit_id' => $kit->id,
            'subdomain'    => $this->pickSubdomain($kit, $domain),
            'company'      => $this->companyName($kit, $domain),
            'email_domain' => $domain,
            'token'        => (string) Str::uuid(),
            'colors'       => $this->colors($kit),
            'status'       => 'ready',
        ]);

        Log::info("brandstore: created {$store->subdomain} for kit {$kit->id} ({$domain})");
    }

    /** Logo + URL + products complete + crawl summary done. */
    public static function ready(BrandKit $kit): bool
    {
        if (! $kit->website || ! ($kit->logo_path || $kit->logo_url)) {
            return false;
        }
        if (($kit->stages['summary'] ?? null) !== 'done') {
            return false;
        }

        return count((array) $kit->products) >= BrandKitSpec::expectedProductCount(true);
    }

    public static function emailDomain(BrandKit $kit): ?string
    {
        $host = parse_url((string) $kit->website, PHP_URL_HOST)
            ?: parse_url('https://'.$kit->website, PHP_URL_HOST);
        $host = strtolower(trim((string) $host));
        $host = preg_replace('/^www\./', '', $host);

        return $host && str_contains($host, '.') ? $host : null;
    }

    private function companyName(BrandKit $kit, string $domain): string
    {
        $name = trim((string) ($kit->company ?: ($kit->summary['company'] ?? '')));

        return $name !== '' ? Str::limit($name, 60, '') : Str::title(str_replace(['-', '_'], ' ', explode('.', $domain)[0]));
    }

    private function pickSubdomain(BrandKit $kit, string $domain): string
    {
        $base = Str::slug($this->companyName($kit, $domain));
        if ($base === '' || in_array($base, self::RESERVED_SUBDOMAINS, true)) {
            $base = Str::slug(explode('.', $domain)[0]) ?: 'store-'.$kit->id;
        }
        $base = substr($base, 0, 50);

        $sub = $base;
        for ($i = 2; BrandStore::where('subdomain', $sub)->exists() || in_array($sub, self::RESERVED_SUBDOMAINS, true); $i++) {
            $sub = "{$base}-{$i}";
        }

        return $sub;
    }

    /** Brand palette: crawled site colors first (truest), logo-extract second. */
    private function colors(BrandKit $kit): array
    {
        $palette = collect((array) ($kit->summary['colors'] ?? []))
            ->merge((array) ($kit->extract['colors'] ?? []))
            ->map(fn ($c) => strtolower(trim((string) $c)))
            ->filter(fn ($c) => preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/', $c))
            ->unique()->values();

        // avoid a white/near-white primary (buttons would vanish on the white shop)
        $usable = $palette->reject(fn ($c) => self::isNearWhite($c))->values();

        return [
            'primary' => $usable[0] ?? '#1f4fd8',
            'accent'  => $usable[1] ?? ($usable[0] ?? '#1f4fd8'),
            'palette' => $palette->take(6)->all(),
        ];
    }

    private static function isNearWhite(string $hex): bool
    {
        $h = ltrim($hex, '#');
        if (strlen($h) === 3) {
            $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        }
        [$r, $g, $b] = [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];

        return ($r + $g + $b) / 3 > 225;
    }
}
