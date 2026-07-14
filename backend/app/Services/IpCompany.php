<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Best-effort "which company is behind this IP" for the widget's IP mode.
 * Reverse DNS first (free), then IPinfo's company data when a token is set.
 * Returns a registrable company domain, or null (residential/ISP/cloud/unknown).
 */
class IpCompany
{
    /** Infra/ISP/cloud domains that are never the visitor's company. */
    private const GENERIC = '/(^|\.)(amazonaws|cloudfront|1e100|googleusercontent|azure|windows|akamai|akamaitechnologies|comcast|xfinity|verizon|att|charter|spectrum|rr|cox|t-mobile|tmodns|digitalocean|linode|ovh|hetzner|cloudflare|as\d+)\.(com|net|org)$/i';

    public function domainFor(?string $ip): ?string
    {
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        // 1. Reverse DNS — cheap, occasionally identifies a corporate host.
        $host = @gethostbyaddr($ip);
        if ($host && $host !== $ip) {
            $d = $this->registrable($host);
            if ($d && ! preg_match(self::GENERIC, $d)) {
                return $d;
            }
        }

        // 2. IPinfo company lookup (paid add-on) when configured.
        if ($token = config('services.ipinfo.token')) {
            try {
                $j = Http::timeout(4)->acceptJson()->get("https://ipinfo.io/{$ip}", ['token' => $token])->json();
                $d = $j['company']['domain'] ?? ($j['domain'] ?? null);
                if (is_string($d) && $d !== '' && ! preg_match(self::GENERIC, $d)) {
                    return strtolower($d);
                }
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        return null;
    }

    /** Last two labels of a hostname → the registrable domain (rough; fine for filtering). */
    private function registrable(string $host): ?string
    {
        $parts = explode('.', strtolower(trim($host, '.')));

        return count($parts) >= 2 ? implode('.', array_slice($parts, -2)) : null;
    }
}
