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
        $subject = $this->purpose === 'password_reset'
            ? 'LifeLoop - Password Reset Code'
            : 'LifeLoop - Email Verification Code';

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
