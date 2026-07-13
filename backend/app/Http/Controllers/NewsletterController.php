<?php

namespace App\Http\Controllers;

use App\Mail\WelcomeSubscriber;
use App\Models\Coupon;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NewsletterController extends Controller
{
    /** Capture a lead (footer / free tools) and send the welcome + first-order coupon once. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'email'  => ['required', 'email:rfc', 'max:190'],
            'source' => ['nullable', 'string', 'max:40'],
        ]);

        $subscriber = Subscriber::firstOrNew(['email' => strtolower(trim($data['email']))]);
        if (! $subscriber->exists) {
            $subscriber->source = $data['source'] ?? 'footer';
            $subscriber->save();
        }

        // Send the welcome (with WELCOME20) at most once per subscriber.
        if (! $subscriber->welcomed_at) {
            $coupon = Coupon::where('code', 'WELCOME20')->where('active', true)->first();
            if ($coupon) {
                try {
                    Mail::to($subscriber->email)->send(new WelcomeSubscriber($coupon->code, (int) $coupon->percent_off));
                    $subscriber->forceFill(['welcomed_at' => now()])->save();
                } catch (\Throwable $e) {
                    Log::error('welcome email failed', ['email' => $subscriber->email, 'error' => $e->getMessage()]);
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', "You're on the list — check your inbox for your discount code.");
    }
}
