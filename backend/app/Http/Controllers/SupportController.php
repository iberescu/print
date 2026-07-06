<?php

namespace App\Http\Controllers;

use App\Jobs\AnswerSupportTicket;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Visitor side of the support chat (the bubble widget). Tickets are bound to
 * a session token so guests keep their thread; the AI answer job runs after
 * the response and the widget polls it in.
 */
class SupportController extends Controller
{
    public function messages(): JsonResponse
    {
        $ticket = $this->currentTicket();

        return response()->json($this->payload($ticket));
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $ticket = $this->currentTicket() ?? SupportTicket::create([
            'token'   => $this->token(),
            'user_id' => $request->user()?->id,
            'status'  => 'open',
        ]);
        if ($request->user() && ! $ticket->user_id) {
            $ticket->update(['user_id' => $request->user()->id]);
        }

        $ticket->messages()->create(['sender' => 'customer', 'body' => $data['body']]);
        $ticket->touch();

        AnswerSupportTicket::dispatchAfterResponse($ticket->id);

        return response()->json($this->payload($ticket->fresh('messages')));
    }

    private function token(): string
    {
        if (! session('support.token')) {
            session(['support.token' => Str::random(40)]);
        }

        return session('support.token');
    }

    private function currentTicket(): ?SupportTicket
    {
        return SupportTicket::with('messages')
            ->where('token', $this->token())
            ->where('status', '!=', 'closed')
            ->latest('id')->first();
    }

    private function payload(?SupportTicket $ticket): array
    {
        return [
            'status'   => $ticket?->status,
            'messages' => $ticket?->messages->map(fn ($m) => [
                'id'     => $m->id,
                'sender' => $m->sender,
                'body'   => $m->body,
                'at'     => $m->created_at->format('H:i'),
            ])->values() ?? [],
        ];
    }
}
