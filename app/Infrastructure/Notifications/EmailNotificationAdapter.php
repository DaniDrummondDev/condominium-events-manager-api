<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Infrastructure\Notifications\Mails\ResidentInvitationMail;
use Application\Shared\Contracts\NotificationServiceInterface;
use Illuminate\Support\Facades\Mail;

class EmailNotificationAdapter implements NotificationServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(string $channel, string $to, string $template, array $data): void
    {
        if ($channel !== 'email') {
            return;
        }

        $mailable = match ($template) {
            'resident-invitation' => new ResidentInvitationMail($data),
            default => null,
        };

        if ($mailable === null) {
            return;
        }

        Mail::to($to)->queue($mailable);
    }
}
