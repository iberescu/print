<?php

namespace App\Console\Commands;

use App\Mail\BrandStorePortal;
use App\Models\BrandStore;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Portal nudges: day 3 (store is live) and day 10 (reminder) after a brand
 * store's creation, mailed to the customer — resolved from the order that
 * carries the capture key (kits are anonymous until someone checks out).
 * Runs hourly from the scheduler; idempotent via portal_emails_sent.
 */
class NotifyBrandStores extends Command
{
    protected $signature = 'brandstores:notify {--dry : report without sending}';

    protected $description = 'Send the day-3 / day-10 brand-store portal emails';

    public function handle(): int
    {
        $due = BrandStore::with('brandKit')->where('portal_emails_sent', '<', 2)->get()
            ->filter(fn (BrandStore $s) => $s->portal_emails_sent === 0
                ? $s->created_at <= now()->subDays(3)
                : $s->created_at <= now()->subDays(10));

        foreach ($due as $store) {
            $email = $store->owner_email ?: $this->resolveEmail($store);
            if (! $email) {
                continue; // no order yet — try again next run
            }
            if ($this->option('dry')) {
                $this->line("would send #".($store->portal_emails_sent + 1)." to {$email} ({$store->subdomain})");

                continue;
            }
            try {
                Mail::to($email)->send(new BrandStorePortal($store, reminder: $store->portal_emails_sent > 0));
                $store->update([
                    'owner_email'          => $email,
                    'portal_emails_sent'   => $store->portal_emails_sent + 1,
                    'portal_email_last_at' => now(),
                ]);
                $this->info("sent #{$store->portal_emails_sent} to {$email} ({$store->subdomain})");
            } catch (\Throwable $e) {
                Log::error("brandstore notify failed for {$store->subdomain}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    /** The buyer behind this store's capture: their (preferably paid) order's email. */
    private function resolveEmail(BrandStore $store): ?string
    {
        $key = $store->brandKit?->key;
        if (! $key) {
            return null;
        }

        return Order::where('brand_kit_key', $key)
            ->orderByRaw("status = 'paid' DESC")->latest('id')
            ->value('email');
    }
}
