<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\SupportTicket;
use App\Services\GeminiClient;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Auto-answer a support ticket with Gemini flash (req: AI-first support).
 * Runs after the response is sent — the widget polls and picks the reply up.
 * When the model isn't confident (or errors), the ticket is flagged
 * needs_human (red in the admin inbox) and the customer is told a person
 * will follow up right here in the chat.
 */
class AnswerSupportTicket
{
    use Dispatchable;

    private const ESCALATION = "Thanks for reaching out! I've passed your question to our support team — "
        .'a real person will reply right here in this chat shortly.';

    public function __construct(public readonly int $ticketId)
    {
    }

    public function handle(GeminiClient $gemini): void
    {
        $ticket = SupportTicket::with('messages')->find($this->ticketId);
        if (! $ticket || $ticket->messages->last()?->sender !== 'customer') {
            return; // already handled
        }
        // Once a ticket is escalated, the AI stays out of the conversation.
        if ($ticket->status === 'needs_human') {
            $ticket->touch();

            return;
        }

        try {
            $out = $gemini->generateJson($this->prompt($ticket));
            $canAnswer = (bool) ($out['can_answer'] ?? false);
            $reply = trim((string) ($out['reply'] ?? ''));
        } catch (Throwable $e) {
            Log::warning("support: gemini failed for ticket {$ticket->id}: {$e->getMessage()}");
            $canAnswer = false;
            $reply = '';
        }

        if ($canAnswer && $reply !== '') {
            $ticket->messages()->create(['sender' => 'ai', 'body' => $reply]);
            $ticket->update(['status' => 'ai']);
        } else {
            $ticket->messages()->create(['sender' => 'ai', 'body' => self::ESCALATION]);
            $ticket->update(['status' => 'needs_human']);
        }
    }

    private function prompt(SupportTicket $ticket): string
    {
        $threshold = (float) config('shop.free_shipping_threshold', 50);

        // Compact live catalogue summary so answers reflect what we actually sell.
        $catalogue = Cache::remember('support.catalogue', 600, function () {
            return Category::with(['products' => fn ($q) => $q->where('is_active', true)])
                ->where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn ($c) => $c->name.': '.$c->products
                    ->map(fn ($p) => "{$p->name} (from \${$p->from_price})")->implode(', '))
                ->implode("\n");
        });

        $transcript = $ticket->messages
            ->map(fn ($m) => strtoupper($m->sender).': '.$m->body)->implode("\n");

        return <<<PROMPT
You are the support assistant for RunMyPrint (runmyprint.com), an online custom-printing
shop for small businesses.

FACTS YOU MAY USE (the only source of truth — never invent anything beyond this):
- Products by category, with starting prices:
{$catalogue}
- Exact prices depend on quantity and options (paper stock, finish, …) and are shown live
  on each product page. Larger quantities cost less per unit.
- Shipping: free Economy shipping on orders of \${$threshold} or more; below that a flat \$4.99.
- How ordering works: pick a product → design it online in our free browser editor
  (200+ professional templates, no design skills needed) or upload your own artwork
  (PDF/images supported) → review and approve the design → fine-tune quantity and paper
  on the final step → checkout.
- After approving a design, size/format/corners are locked (they define the print area),
  but quantity, paper stock and finishes can still be changed before checkout.
- Accounts: sign up with email/password or Google. You must be signed in to check out.
  Payment is by card via Stripe. Orders can be tracked under "My orders" in the account.
- Guarantee: 100% satisfaction — if you don't love the print, we reprint it. See the
  returns page for details.
- Turnaround: fast production with 2-day options on many products.
- Current promo: pay \$50 and get \$250 of Google Display ads via our Layout.ai partnership
  (offered during checkout).
- Support: this chat. Human agents reply here when needed.

RULES:
- Answer ONLY from the facts above. Be friendly, concise (2–4 short sentences), and answer
  in the same language the customer writes in.
- Never invent prices, delivery dates, discount codes, or policies.
- Set "can_answer" to false when the question needs anything you don't have: the status or
  contents of a specific order, refunds/cancellations for an existing order, account or
  payment problems, custom/bulk quotes, complaints, legal/press/partnership requests, or
  anything else you are not fully certain about.

CONVERSATION SO FAR:
{$transcript}

Reply with STRICT JSON, nothing else: {"can_answer": true|false, "reply": "your answer"}.
When "can_answer" is false leave "reply" empty.
PROMPT;
    }
}
