<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Affiliate management: approvals, CPM, impression/click stats, payouts owed. */
class AffiliateController extends Controller
{
    public function index()
    {
        $affiliates = Affiliate::with(['stats', 'payouts'])->orderByRaw("status = 'pending' DESC")->orderByDesc('created_at')->get()
            ->map(function (Affiliate $a) {
                $impressions = (int) $a->stats->sum('impressions');
                $clicks = (int) $a->stats->sum('clicks');
                $earned = (int) round($impressions / 1000 * $a->cpm_cents);
                $paid = (int) $a->payouts->sum('amount_cents');

                return [
                    'id'          => $a->id,
                    'name'        => $a->name,
                    'company'     => $a->company,
                    'email'       => $a->email,
                    'website'     => $a->website,
                    'key'         => $a->key,
                    'status'      => $a->status,
                    'cpm'         => $a->cpm_cents / 100,
                    'impressions' => $impressions,
                    'clicks'      => $clicks,
                    'earned'      => $earned / 100,
                    'paid'        => $paid / 100,
                    'owed'        => max(0, $earned - $paid) / 100,
                    'since'       => $a->created_at?->toFormattedDateString(),
                    'notes'       => $a->notes,
                ];
            });

        return Inertia::render('Admin/Affiliates/Index', ['affiliates' => $affiliates]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'email'   => ['required', 'email', 'max:160', 'unique:affiliates,email'],
            'website' => ['nullable', 'string', 'max:200'],
        ]);

        Affiliate::create($data + ['key' => Str::random(40), 'status' => 'active']);

        return back()->with('success', 'Affiliate created — copy their embed key from the row.');
    }

    public function update(Affiliate $affiliate, Request $request)
    {
        $data = $request->validate([
            'status'    => ['nullable', Rule::in(['pending', 'active', 'paused'])],
            'cpm'       => ['nullable', 'numeric', 'min:0', 'max:100'], // dollars; program range $15–20
            'notes'     => ['nullable', 'string', 'max:2000'],
        ]);

        $affiliate->update(array_filter([
            'status'    => $data['status'] ?? null,
            'cpm_cents' => isset($data['cpm']) ? (int) round($data['cpm'] * 100) : null,
            'notes'     => $data['notes'] ?? null,
        ], fn ($v) => $v !== null));

        return back()->with('success', 'Affiliate updated.');
    }

    public function payout(Affiliate $affiliate, Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'], // dollars
            'note'   => ['nullable', 'string', 'max:300'],
        ]);

        $affiliate->payouts()->create([
            'amount_cents' => (int) round($data['amount'] * 100),
            'note'         => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Payout recorded.');
    }
}
