<?php

namespace App\Mail;

use App\Models\Summary;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailySummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Summary $summary,
    ) {}

    public function envelope(): Envelope
    {
        $type = ucfirst($this->summary->type);
        $date = $this->summary->period_start?->toDateString() ?? now()->toDateString();

        return new Envelope(
            subject: "StewardAI — {$type} Summary ({$date})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.daily-summary',
        );
    }
}
