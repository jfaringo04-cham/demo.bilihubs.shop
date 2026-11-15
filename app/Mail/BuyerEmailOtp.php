<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BuyerEmailOtp extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $otp,
        public ?string $approvalMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'BiliHub - Verify Your Email Address'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.buyer-email-otp',
            with: [
                'otp' => $this->otp,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
