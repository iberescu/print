<?php

namespace App\Console\Commands;

use App\Mail\CartReminder;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Abandoned-cart nudge: signed-in carts untouched for 3–48 hours get one
 * reminder email — unless the customer ordered in the meantime. Runs hourly
 * from the scheduler.
 */
class SendCartReminders extends Command
{
    protected $signature = 'carts:remind';

    protected $description = 'Email one reminder for signed-in carts abandoned 3-48h ago';

    public function handle(): int
    {
        $rows = DB::table('cart_reminders')
            ->whereNull('reminded_at')
            ->whereBetween('updated_at', [now()->subHours(48), now()->subHours(3)])
            ->limit(200)
            ->get();

        $sent = 0;
        foreach ($rows as $row) {
            // bought since abandoning? then no nagging
            if (Order::where('email', $row->email)->where('created_at', '>=', $row->updated_at)->exists()) {
                DB::table('cart_reminders')->where('id', $row->id)->delete();

                continue;
            }

            $items = json_decode($row->items, true) ?: [];
            if (! $items) {
                DB::table('cart_reminders')->where('id', $row->id)->delete();

                continue;
            }

            try {
                Mail::to($row->email)->send(new CartReminder($items, (float) $row->subtotal));
                DB::table('cart_reminders')->where('id', $row->id)->update(['reminded_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                Log::error('cart reminder failed', ['email' => $row->email, 'error' => $e->getMessage()]);
            }
        }

        $this->info("cart reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
