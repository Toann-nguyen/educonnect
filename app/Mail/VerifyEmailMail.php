<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Khởi tạo message instance.
     */
    public function __construct(
        public User $user,
        public string $token
    ) {}

    /**
     * Lấy envelope cho message.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Xác thực tài khoản EduConnect',
        );
    }

    /**
     * Lấy content definition cho message.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.verify',
            with: [
                'url' => config('app.frontend_url', 'http://localhost:3000') . '/verify-email?token=' . $this->token,
                'name' => $this->user->name ?? $this->user->email,
            ]
        );
    }

    /**
     * Lấy các attachments cho message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
