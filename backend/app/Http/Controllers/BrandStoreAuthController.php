<?php

namespace App\Http\Controllers;

use App\Models\BrandStoreToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Private Brand Store access: employees sign in with their work email — a
 * magic link is mailed ONLY to addresses @the customer's domain. The session
 * grant is host-scoped (per store); the buyer's own entry is the preview
 * token handled in ResolveBrandStore.
 */
class BrandStoreAuthController extends Controller
{
    public function show()
    {
        if (! app()->bound('brandStore')) {
            return redirect('/'); // login page only exists on store hosts
        }
        $store = app('brandStore');

        return Inertia::render('BrandStore/Login', [
            'company' => $store->company,
            'domain'  => $store->email_domain,
            'logo'    => $store->logoUrl(),
            'sent'    => (bool) session('bs_link_sent'),
        ]);
    }

    public function send(Request $request)
    {
        if (! app()->bound('brandStore')) {
            return redirect('/');
        }
        $store = app('brandStore');
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $email = strtolower(trim($data['email']));

        // Only @their-domain addresses may enter. Same neutral reply either way —
        // the form never confirms which addresses exist or qualify.
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        if ($domain === strtolower($store->email_domain)) {
            $token = BrandStoreToken::create([
                'brand_store_id' => $store->id,
                'email'          => $email,
                'token'          => Str::random(48),
                'expires_at'     => now()->addMinutes(30),
            ]);
            try {
                Mail::to($email)->send(new \App\Mail\BrandStoreLoginLink(
                    $store->company,
                    $store->url('/store-auth/'.$token->token),
                ));
            } catch (\Throwable $e) {
                Log::error("brandstore: login mail failed for {$email}: {$e->getMessage()}");
            }
        } else {
            Log::info("brandstore: login refused for {$email} on {$store->subdomain} (needs @{$store->email_domain})");
        }

        return redirect('/store-login')->with('bs_link_sent', true);
    }

    public function auth(string $token, Request $request)
    {
        if (! app()->bound('brandStore')) {
            return redirect('/');
        }
        $store = app('brandStore');

        $row = BrandStoreToken::where('token', $token)
            ->where('brand_store_id', $store->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
        if (! $row) {
            return redirect('/store-login')->with('error', 'That link has expired — request a fresh one.');
        }

        $row->update(['used_at' => now()]);
        $request->session()->put("brandstore.auth.{$store->id}", $row->email);

        return redirect('/');
    }
}
