<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeSubscriber extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public int $percent)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Welcome — here's {$this->percent}% off your first order");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.welcome', with: [
            'code'    => $this->code,
            'percent' => $this->percent,
        ]);
    }
}
