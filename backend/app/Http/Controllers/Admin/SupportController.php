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
                'customer' => $t->user?->email ?? 'Guest',
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
                'customer' => $ticket->user?->email ?? 'Guest',
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

        // Signed-in customers get the reply by email too; guest tickets are
        // session-bound and only visible in the chat bubble.
        if ($ticket->user?->email) {
            try {
                \Illuminate\Support\Facades\Mail::to($ticket->user->email)->send(new \App\Mail\SupportReplied($data['body']));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('support reply mail failed', ['ticket' => $ticket->id, 'error' => $e->getMessage()]);
            }
        }

        return redirect()->route('admin.support.show', $ticket)->with('success', 'Reply sent.');
    }
}
