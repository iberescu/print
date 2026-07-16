<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Admin inbox for the support chat: every inquiry lands here; needs_human
 * tickets (AI punted) are flagged red and answered by a person.
 */
class SupportController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $tickets = SupportTicket::with(['user', 'messages' => fn ($q) => $q->oldest('id')->limit(1)])
            ->withCount('messages')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("status = 'needs_human' DESC")->latest('updated_at')
            ->paginate(25)->withQueryString()
            ->through(fn (SupportTicket $t) => [
                'id'       => $t->id,
                'customer' => $t->email ?? $t->user?->email ?? 'Guest',
                'channel'  => $t->channel ?? 'chat',
                'subject'  => $t->subject,
                'excerpt'  => Str::limit(trim((string) $t->messages->first()?->body), 90),
                'count'    => $t->messages_count,
                'status'   => $t->status,
                'updated'  => $t->updated_at->diffForHumans(),
            ]);

        return Inertia::render('Admin/Support/Index', [
            'tickets' => $tickets,
            'status'  => $status,
            'counts'  => [
                'all'         => SupportTicket::count(),
                'needs_human' => SupportTicket::where('status', 'needs_human')->count(),
                'ai'          => SupportTicket::where('status', 'ai')->count(),
                'answered'    => SupportTicket::where('status', 'answered')->count(),
            ],
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load(['user', 'messages']);

        return Inertia::render('Admin/Support/Show', [
            'ticket' => [
                'id'       => $ticket->id,
                'customer' => $ticket->email ?? $ticket->user?->email ?? 'Guest',
                'channel'  => $ticket->channel ?? 'chat',
                'subject'  => $ticket->subject,
                'status'   => $ticket->status,
                'created'  => $ticket->created_at->format('M j, Y H:i'),
                'messages' => $ticket->messages->map(fn ($m) => [
                    'id'     => $m->id,
                    'sender' => $m->sender,
                    'body'   => $m->body,
                    'at'     => $m->created_at->format('M j, H:i'),
                ]),
            ],
        ]);
    }

    public function reply(SupportTicket $ticket, Request $request)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $ticket->messages()->create(['sender' => 'admin', 'body' => $data['body']]);
        $ticket->update(['status' => 'answered']);

        // Email tickets always reply by mail (threaded via the subject ref);
        // chat tickets email signed-in customers too. Guest chat tickets are
        // session-bound and only visible in the bubble.
        $to = $ticket->email ?: $ticket->user?->email;
        if ($to) {
            try {
                $ref = sprintf('[RMP-T%d-%s]', $ticket->id, substr((string) $ticket->token, 0, 6));
                \Illuminate\Support\Facades\Mail::to($to)->send(new \App\Mail\SupportReplied($data['body'], $ticket->subject, $ref, $ticket->channel ?? 'chat'));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('support reply mail failed', ['ticket' => $ticket->id, 'error' => $e->getMessage()]);
            }
        }

        return redirect()->route('admin.support.show', $ticket)->with('success', 'Reply sent.');
    }

    /** "Try AI again" — re-run Gemini on the thread (even after an escalation).
     *  Synchronous so the admin sees the outcome on the reload. */
    public function retryAi(SupportTicket $ticket)
    {
        $answered = \App\Jobs\AnswerSupportTicket::dispatchSync($ticket->id, force: true);

        return redirect()->route('admin.support.show', $ticket)->with(
            'success',
            $answered
                ? ($ticket->fresh()->channel === 'email' ? 'AI answered — reply emailed to the customer.' : 'AI answered in the chat.')
                : 'AI still can\'t answer this one confidently — write a reply below.',
        );
    }
}
