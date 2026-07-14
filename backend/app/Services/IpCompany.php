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

        // 1. Local reverse DNS — cheap; a corporate PTR gives the domain directly.
        $host = @gethostbyaddr($ip);
        if ($host && $host !== $ip && ($d = $this->fromHost($host))) {
            return $d;
        }

        // 2. IPinfo — a better reverse DNS (hostname) + company.domain on paid plans.
        if ($token = config('services.ipinfo.token')) {
            try {
                $j = Http::timeout(4)->acceptJson()->get("https://ipinfo.io/{$ip}", ['token' => $token])->json();

                $company = $j['company']['domain'] ?? null; // paid "company" data, if enabled
                if (is_string($company) && $company !== '' && ! preg_match(self::GENERIC, $company)) {
                    return strtolower($company);
                }
                if (! empty($j['hostname']) && ($d = $this->fromHost($j['hostname']))) {
                    return $d;
                }
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        return null;
    }

    /** A hostname → its registrable company domain, unless it's ISP/cloud infra. */
    private function fromHost(string $host): ?string
    {
        $parts = explode('.', strtolower(trim($host, '.')));
        if (count($parts) < 2) {
            return null;
        }
        $domain = implode('.', array_slice($parts, -2));

        return preg_match(self::GENERIC, $domain) ? null : $domain;
    }
}
