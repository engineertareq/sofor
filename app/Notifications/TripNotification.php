<?php

namespace App\Notifications;

use App\Support\Phrase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One small, generic trip notification so every channel renders from the same
 * payload: a title, a short body and a deep link back into the trip.
 *
 * Title and body may be Phrases: they are then written in each recipient's
 * own language when the notification is stored or mailed. `title` and `body`
 * hold the text in the sender's language.
 */
class TripNotification extends Notification
{
    use Queueable;

    public string $title;

    public string $body;

    private Phrase|string $titlePhrase;

    private Phrase|string $bodyPhrase;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        Phrase|string $title,
        Phrase|string $body,
        public string $url,
        public string $tone = 'info',
        public string $icon = 'bell',
        public ?int $tripId = null,
        public array $meta = [],
    ) {
        $this->titlePhrase = $title;
        $this->bodyPhrase = $body;
        $this->title = (string) $title;
        $this->body = (string) $body;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Runs in the recipient's language (User::preferredLocale).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => (string) $this->titlePhrase,
            'body' => (string) $this->bodyPhrase,
            'url' => $this->url,
            'tone' => $this->tone,
            'icon' => $this->icon,
            'trip_id' => $this->tripId,
            'meta' => $this->meta,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject((string) $this->titlePhrase)
            ->line((string) $this->bodyPhrase)
            ->action(__('Open Sofor'), $this->url);
    }
}
