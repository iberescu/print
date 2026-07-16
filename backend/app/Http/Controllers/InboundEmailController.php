<?php

namespace App\Http\Controllers;

use App\Jobs\AnswerSupportTicket;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Inbound support email → the support inbox. Any mail to contact@ is POSTed
 * here by the inbound provider (Resend inbound webhook / a mail-forwarding
 * worker — the payload parsing is tolerant of both shapes). Threading: our
 * outbound subjects carry "[RMP-T{id}-{tok}]"; replies with that ref land on
 * the same ticket, otherwise we reuse the sender's latest recent ticket, else
 * open a new one. Every customer email gets the same Gemini-first treatment
 * as the chat bubble (auto-answer when confident, needs_human otherwise).
 */
class InboundEmailController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(hash_equals((string) config('shop.support_inbound_token'), (string) $request->query('token')), 403);

        // Resend webhooks multiplex event types; only received mail matters here
        // (delivery/open events for our own outbound would otherwise echo back).
        $type = (string) $request->input('type', '');
        if ($type !== '' && ! in_array($type, ['email.received', 'inbound.email.received'], true)) {
            return response()->json(['ok' => false, 'reason' => 'ignored event type'], 200);
        }

        $p = (array) $request->input('data', $request->all()); // Resend nests under data
        $fromRaw = (string) ($p['from'] ?? '');
        if (is_array($p['from'] ?? null)) {
            $fromRaw = (string) ($p['from']['email'] ?? ($p['from'][0]['email'] ?? json_encode($p['from'])));
        }
        preg_match('/[\w.+-]+@[\w.-]+\.\w+/', $fromRaw, $m);
        $email = strtolower($m[0] ?? '');
        $subject = trim((string) ($p['subject'] ?? ''));
        $body = trim((string) ($p['text'] ?? $p['text_body'] ?? $p['plain'] ?? ''));
        if ($body === '' && ! empty($p['html'])) {
            $body = trim(preg_replace('/\s+/', ' ', strip_tags((string) $p['html'])));
        }
        if ($email === '' || $body === '') {
            return response()->json(['ok' => false, 'reason' => 'no sender or body'], 200);
        }
        // Never loop on our own outbound / bounces.
        if (Str::contains($email, ['@runmyprint.com', 'mailer-daemon', 'noreply', 'no-reply'])) {
            return response()->json(['ok' => false, 'reason' => 'self or bounce'], 200);
        }

        $body = $this->stripQuotedTail($body);

        // 1) thread ref in the subject → the exact ticket
        $ticket = null;
        if (preg_match('/\[RMP-T(\d+)-([0-9a-zA-Z]{6})\]/', $subject, $ref)) {
            $ticket = SupportTicket::where('id', (int) $ref[1])->where('token', 'like', $ref[2].'%')->first();
        }
        // 2) the sender's latest recent ticket  3) a fresh one
        $ticket ??= SupportTicket::where('email', $email)->where('updated_at', '>', now()->subDays(30))->latest('updated_at')->first();
        $ticket ??= SupportTicket::create([
            'token'   => (string) Str::uuid(),
            'channel' => 'email',
            'email'   => $email,
            'subject' => Str::limit($subject !== '' ? preg_replace('/^(re:\s*)+/i', '', $subject) : 'Email inquiry', 160, ''),
            'status'  => 'open',
        ]);
        if (! $ticket->email) {
            $ticket->update(['email' => $email]); // chat ticket picking up an email address
        }

        $ticket->messages()->create(['sender' => 'customer', 'body' => Str::limit($body, 8000, '…')]);
        $ticket->touch();

        AnswerSupportTicket::dispatchAfterResponse($ticket->id);

        return response()->json(['ok' => true, 'ticket' => $ticket->id]);
    }

    /** Drop quoted reply tails ("On …, X wrote:" and "> " blocks) so threads stay readable. */
    private function stripQuotedTail(string $body): string
    {
        $lines = preg_split('/\r?\n/', $body);
        $keep = [];
        foreach ($lines as $line) {
            if (preg_match('/^On .{5,80} wrote:\s*$/', trim($line)) || str_starts_with(trim($line), '-----Original Message-----')) {
                break;
            }
            if (str_starts_with(ltrim($line), '>')) {
                continue;
            }
            $keep[] = $line;
        }

        return trim(implode("\n", $keep)) ?: trim($body);
    }
}
