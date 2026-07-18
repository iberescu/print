<?php

namespace App\Mail;

use App\Models\BrandStore;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Day-3 / day-10 nudge: the customer's private Brand Store is live — here's
 *  the (owner-authenticated) link, plus how the team logs in. */
class BrandStorePortal extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BrandStore $store, public bool $reminder = false)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->reminder
            ? "Reminder: the {$this->store->company} Brand Store is ready for your team"
            : "Your private Brand Store for {$this->store->company} is live");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.brand-store-portal', with: [
            'company'  => $this->store->company,
            'domain'   => $this->store->email_domain,
            'link'     => $this->store->url('/?bs_preview='.$this->store->token),
            'reminder' => $this->reminder,
            'products' => array_slice(\App\Support\LogoOnProducts::forKey($this->store->brandKit?->key), 0, 4),
        ]);
    }
}
