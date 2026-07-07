<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Affiliate partner portal: sign in with the widget key (the key IS the
 * credential — partners have no user accounts) and see impressions, clicks,
 * earnings, payouts and the embed snippet.
 */
class PartnerController extends Controller
{
    public function show()
    {
        if (session('partner.affiliate_id')) {
            return redirect()->route('partner.dashboard');
        }

        return Inertia::render('Partner/Login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['key' => ['required', 'string', 'max:60']]);

        $affiliate = Affiliate::where('key', trim($data['key']))->first();
        if (! $affiliate) {
            return back()->withErrors(['key' => 'That key does not match a partner account.']);
        }

        $request->session()->regenerate();
        session(['partner.affiliate_id' => $affiliate->id]);

        return redirect()->route('partner.dashboard');
    }

    public function dashboard()
    {
        $affiliate = Affiliate::with(['payouts' => fn ($q) => $q->latest()])->find(session('partner.affiliate_id'));
        if (! $affiliate) {
            return redirect()->route('partner');
        }

        $daily = $affiliate->stats()->orderByDesc('date')->limit(30)->get()
            ->map(fn ($s) => ['date' => $s->date, 'impressions' => $s->impressions, 'clicks' => $s->clicks]);

        return Inertia::render('Partner/Dashboard', [
            'partner' => [
                'name'        => $affiliate->name,
                'company'     => $affiliate->company,
                'status'      => $affiliate->status,
                'cpm'         => $affiliate->cpm_cents / 100,
                'key'         => $affiliate->key,
                'impressions' => $affiliate->impressions(),
                'clicks'      => $affiliate->clicks(),
                'earned'      => $affiliate->earnedCents() / 100,
                'paid'        => $affiliate->paidCents() / 100,
                'owed'        => $affiliate->owedCents() / 100,
            ],
            'daily'   => $daily,
            'payouts' => $affiliate->payouts->map(fn ($p) => [
                'amount' => $p->amount_cents / 100, 'note' => $p->note, 'date' => $p->created_at->toFormattedDateString(),
            ]),
        ]);
    }

    public function logout(Request $request)
    {
        session()->forget('partner.affiliate_id');
        $request->session()->regenerate();

        return redirect()->route('partner');
    }
}
