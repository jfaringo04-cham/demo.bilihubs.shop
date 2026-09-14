<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationDecision extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $decision,
        public ?string $reason = null
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->decision === 'approved'
            ? 'Your ' . config('app.name') . ' Account Has Been Approved'
            : 'Update on Your ' . config('app.name') . ' Account Application';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.registration-decision',
            with: [
                'user' => $this->user,
                'decision' => $this->decision,
                'reason' => $this->reason,
                'loginUrl' => route('login'),
            ]
        );
    }
}
