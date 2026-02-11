<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\Mails;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResidentInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public string $queue = 'notifications';

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly array $data,
    ) {}

    public function envelope(): Envelope
    {
        $condominiumName = $this->data['condominium_name'] ?? 'Condomínio';

        return new Envelope(
            subject: "Convite para {$condominiumName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.resident-invitation',
            with: [
                'name' => $this->data['name'] ?? '',
                'condominiumName' => $this->data['condominium_name'] ?? '',
                'token' => $this->data['token'] ?? '',
                'expiresAt' => $this->data['expires_at'] ?? '',
            ],
        );
    }
}
