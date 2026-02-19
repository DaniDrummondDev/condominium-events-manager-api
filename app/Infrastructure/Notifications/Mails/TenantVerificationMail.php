<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\Mails;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly array $data,
    ) {
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirme seu cadastro - Condominium Events Manager',
        );
    }

    public function content(): Content
    {
        $token = $this->data['verification_token'] ?? '';
        $baseUrl = config('app.url', 'http://localhost:8000');
        $verificationUrl = "{$baseUrl}/api/v1/platform/public/register/verify?token={$token}";

        return new Content(
            view: 'emails.tenant-verification',
            with: [
                'adminName' => $this->data['admin_name'] ?? '',
                'condominiumName' => $this->data['condominium_name'] ?? '',
                'verificationUrl' => $verificationUrl,
                'expiresAt' => $this->data['expires_at'] ?? '',
            ],
        );
    }
}
