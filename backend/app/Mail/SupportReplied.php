<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupportReplied extends Mailable
{
    use Queueable, SerializesModels;

    /** $threadRef (e.g. "[RMP-T12-a1b2c3]") rides in the subject so a customer's
     *  "Re:" lands back on the same ticket via the inbound webhook. $channel
     *  picks the footer: email tickets are told to just reply, chat tickets are
     *  pointed back at the bubble. */
    public function __construct(
        public string $reply,
        public ?string $emailSubject = null,
        public ?string $threadRef = null,
        public string $channel = 'chat',
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = $this->emailSubject ? 'Re: '.preg_replace('/^(re:\s*)+/i', '', $this->emailSubject) : 'We replied to your support message';
        if ($this->threadRef && ! str_contains($subject, $this->threadRef)) {
            $subject .= ' '.$this->threadRef;
        }
        $support = (string) config('shop.support_email', 'contact@runmyprint.com');

        return new Envelope(
            from: new Address($support, config('app.name', 'RunMyPrint').' Support'),
            replyTo: [new Address($support)],
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.support-replied');
    }
}
