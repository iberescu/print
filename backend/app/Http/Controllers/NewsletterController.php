<?php

namespace App\Http\Controllers;

use App\Jobs\SendWelcome;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    /** Capture a lead (footer / free tools) and send the welcome + first-order coupon once. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'email'  => ['required', 'email:rfc', 'max:190'],
            'source' => ['nullable', 'string', 'max:40'],
        ]);

        // Newsletter signups get the coupon straight away; any "your logo on" products
        // in the session are included if already generated.
        SendWelcome::schedule($data['email'], session('pqsg.key'), 0, $data['source'] ?? 'footer');

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', "You're on the list — check your inbox for your discount code.");
    }
}
