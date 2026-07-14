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

    /** @param  array<int,array<string,mixed>>  $products  up to 4 "your logo on" mockups */
    public function __construct(public string $code, public int $percent, public array $products = [])
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Welcome — here's {$this->percent}% off your first order");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.welcome', with: [
            'code'     => $this->code,
            'percent'  => $this->percent,
            'products' => array_slice($this->products, 0, 4),
        ]);
    }
}
