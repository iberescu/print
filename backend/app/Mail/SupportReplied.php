<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportReplied extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $reply)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'We replied to your support message');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.support-replied');
    }
}
