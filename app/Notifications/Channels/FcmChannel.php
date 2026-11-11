<?php

namespace App\Notifications\Channels;

use App\Jobs\SendPush;
use App\Models\User;
use App\Services\Fcm;
use Illuminate\Notifications\Notification;

/**
 * The "push" channel: an alert that is stored for the website also lands on
 * the traveller's phone, in their own language. Does nothing for somebody
 * without the app, or while Firebase is not set up.
 */
class FcmChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toPush') || ! app(Fcm::class)->configured() || ! $notifiable->devices()->exists()) {
            return;
        }

        /** @var array{title: string, body: string, data?: array<string, scalar|null>, channel?: string} $push */
        $push = $notification->toPush($notifiable);

        SendPush::dispatch($notifiable->id, $push['title'], $push['body'], $push['data'] ?? [], $push['channel'] ?? 'trips');
    }
}
