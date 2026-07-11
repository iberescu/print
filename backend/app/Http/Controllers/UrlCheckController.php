<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class UrlCheckController extends Controller
{
    /**
     * Live "is this a real, reachable web address?" check for designer URL
     * fields. Read-only GET; returns {valid, message} for a green/red tick.
     */
    public function __invoke(Request $request)
    {
        $raw = trim((string) $request->query('url', ''));
        if ($raw === '') {
            return response()->json(['valid' => false, 'message' => 'Enter a web address']);
        }

        $url = preg_match('#^https?://#i', $raw) ? $raw : 'https://'.$raw;
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host || ! str_contains($host, '.')) {
            return response()->json(['valid' => false, 'message' => 'That doesn’t look like a valid web address']);
        }

        // SSRF guard: never let a user point our server at a private/loopback host.
        $ip = gethostbyname($host);
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return response()->json(['valid' => false, 'message' => 'This domain doesn’t resolve']);
        }
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return response()->json(['valid' => false, 'message' => 'That address can’t be reached']);
        }

        try {
            $resp = Http::timeout(5)->connectTimeout(4)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; RunMyPrint URL check)'])
                ->get($url);

            if ($resp->successful() || in_array($resp->status(), [301, 302, 401, 403, 405], true)) {
                return response()->json(['valid' => true, 'message' => 'Looks good — this site is reachable']);
            }

            return response()->json(['valid' => false, 'message' => "Site responded with an error (HTTP {$resp->status()})"]);
        } catch (\Throwable) {
            return response()->json(['valid' => false, 'message' => 'Couldn’t reach this site']);
        }
    }
}
