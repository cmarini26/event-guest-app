<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $serverKey = config('services.firebase.server_key');

        if (! $serverKey) {
            return;
        }

        if (! method_exists($notification, 'toPush')) {
            return;
        }

        $tokens = $notifiable->deviceTokens()->pluck('token')->all();

        if (empty($tokens)) {
            return;
        }

        $payload = $notification->toPush($notifiable);

        // FCM Legacy HTTP API — batch send to all registered device tokens
        $response = Http::withHeaders([
            'Authorization' => "key={$serverKey}",
            'Content-Type'  => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', [
            'registration_ids' => $tokens,
            'notification'     => [
                'title' => $payload['title'],
                'body'  => $payload['body'],
            ],
            'data' => $payload['data'] ?? [],
        ]);

        if (! $response->successful()) {
            Log::warning('FCM push failed', ['status' => $response->status(), 'body' => $response->body()]);
        }
    }
}
