<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CartReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $items, public float $subtotal)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your designs are waiting — finish your order');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.cart-reminder');
    }
}
