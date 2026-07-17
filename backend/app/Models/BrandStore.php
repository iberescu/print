<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandStore extends Model
{
    protected $guarded = [];

    protected $casts = ['colors' => 'array'];

    public function brandKit()
    {
        return $this->belongsTo(BrandKit::class);
    }

    /** The store's own origin, e.g. https://acme-plumbing.runmyprint.com */
    public function url(string $path = '/'): string
    {
        $base = config('shop.brand_store_base') ?: parse_url((string) config('app.url'), PHP_URL_HOST);
        $base = preg_replace('/^www\./i', '', (string) $base);
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);

        return $scheme.'://'.$this->subdomain.'.'.$base.($port ? ':'.$port : '').$path;
    }

    /** The generated 16:9 store hero (products-on-a-table in their brand), if ready. */
    public function heroUrl(): ?string
    {
        $key = $this->brandKit?->key;
        if (! $key) {
            return null;
        }
        $path = \App\Jobs\BrandKit\GenerateStoreHero::path($key);

        return \Illuminate\Support\Facades\Cache::remember(
            "brandstore.hero.{$this->id}",
            300,
            fn () => \Illuminate\Support\Facades\Storage::disk('public')->exists($path)
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($path)
                : null,
        );
    }

    /** The tight display logo (logo-hd) of the owning kit, absolute URL. */
    public function logoUrl(): ?string
    {
        $kit = $this->brandKit;
        if (! $kit) {
            return null;
        }
        if ($kit->logo_path) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($kit->logo_path);
        }

        return $kit->logo_url ?: null;
    }
}
