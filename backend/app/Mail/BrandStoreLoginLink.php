<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BrandStoreLoginLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $company, public string $link)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your sign-in link — {$this->company} Brand Store");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.brand-store-login');
    }
}
