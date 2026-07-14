<?php

namespace App\Jobs;

use App\Mail\WelcomeSubscriber;
use App\Models\Coupon;
use App\Models\Subscriber;
use App\Support\LogoOnProducts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the welcome email — the WELCOME20 coupon plus up to 4 of the customer's
 * "your logo on" mockups (resolved at send time from their capture key, so the
 * ~5-minute delay from login gives the brand kit time to generate). Runs on the
 * same 'brandkit' queue the engine worker processes.
 */
class SendWelcome implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public string $email, public ?string $pqsgKey = null)
    {
        $this->onQueue('brandkit');
    }

    /**
     * Claim + schedule the welcome for an email, once. Marks welcomed_at up front so
     * repeat logins/subscribes don't queue duplicates; delivers after $delayMinutes.
     */
    public static function schedule(string $email, ?string $pqsgKey, int $delayMinutes, string $source = 'account'): void
    {
        $email = strtolower(trim($email));
        if ($email === '') {
            return;
        }
        $sub = Subscriber::firstOrCreate(['email' => $email], ['source' => $source]);
        if ($sub->welcomed_at) {
            return; // already welcomed / scheduled
        }
        $sub->forceFill(['welcomed_at' => now()])->save();

        self::dispatch($email, $pqsgKey)->delay(now()->addMinutes($delayMinutes));
    }

    public function handle(): void
    {
        $coupon = Coupon::where('code', 'WELCOME20')->where('active', true)->first();
        if (! $coupon) {
            return;
        }

        $products = array_slice(LogoOnProducts::forKey($this->pqsgKey), 0, 4);

        try {
            Mail::to($this->email)->send(new WelcomeSubscriber($coupon->code, (int) $coupon->percent_off, $products));
        } catch (\Throwable $e) {
            Log::error('welcome email failed', ['email' => $this->email, 'error' => $e->getMessage()]);
        }
    }
}
