<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public string $purpose = 'verification'
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->purpose) {
            'password_reset' => 'LifeLoop - Password Reset Code',
            'password_change' => 'LifeLoop - Password Change Security Code',
            'email_change' => 'LifeLoop - Email Change Verification Code',
            default => 'LifeLoop - Email Verification Code',
        };

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'code' => $this->code,
                'purpose' => $this->purpose,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
