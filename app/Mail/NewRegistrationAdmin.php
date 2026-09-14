<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewRegistrationAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Registration: ' . $this->user->name . ' (' . ucfirst($this->user->role) . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.new-registration-admin',
            with: [
                'user' => $this->user,
                'reviewUrl' => route('admin.registrations.index'),
            ]
        );
    }
}
