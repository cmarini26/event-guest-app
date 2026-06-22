<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\Guest;
use App\Notifications\Channels\PushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RsvpReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Guest $guest,
        public readonly Event $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', PushChannel::class];
    }

    public function toPush(object $notifiable): array
    {
        $guestName = "{$this->guest->first_name} {$this->guest->last_name}";
        $verb = match ($this->guest->rsvp_status) {
            'attending'  => 'is attending',
            'declined'   => 'declined',
            'waitlisted' => 'joined the waitlist for',
            default      => 'responded to',
        };

        return [
            'title' => 'New RSVP',
            'body'  => "{$guestName} {$verb} {$this->event->name}",
            'data'  => [
                'event_id' => (string) $this->event->id,
                'type'     => 'rsvp_received',
            ],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $guestName = "{$this->guest->first_name} {$this->guest->last_name}";
        $status = $this->guest->rsvp_status;

        $statusLine = match ($status) {
            'attending' => "{$guestName} is attending {$this->event->name}.",
            'waitlisted' => "{$guestName} has been added to the waitlist for {$this->event->name}.",
            'declined' => "{$guestName} has declined their invitation to {$this->event->name}.",
            default => "{$guestName} responded to their invitation for {$this->event->name}.",
        };

        $eventUrl = config('app.url') . '/events/' . $this->event->id;

        return (new MailMessage)
            ->subject("{$guestName} " . ($status === 'attending' ? 'is attending' : ($status === 'declined' ? 'declined' : 'responded to')) . " {$this->event->name}")
            ->greeting("Hi {$notifiable->name}!")
            ->line($statusLine)
            ->action('View guest list', $eventUrl);
    }
}
