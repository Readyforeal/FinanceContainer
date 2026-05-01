<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly array $recommendations,
        public readonly string $analysis,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'StewardAI — Payment Reminders for Today',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.payment-reminder',
        );
    }
}
