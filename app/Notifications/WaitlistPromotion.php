<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitlistPromotion extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Event $event) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $rsvpUrl = config('app.url') . '/rsvp/' . $notifiable->rsvp_token;

        $mail = (new MailMessage)
            ->subject("Great news — you're in! {$this->event->name}")
            ->greeting("Hi {$notifiable->first_name}!")
            ->line("A spot has opened up and you've been moved off the waitlist — you're now confirmed for **{$this->event->name}**!");

        if ($this->event->starts_at) {
            $tzLabel = $this->event->timezone ? " ({$this->event->timezone})" : '';
            $mail->line('**Date:** ' . $this->event->starts_at->format('l, F j, Y \a\t g:i A') . $tzLabel);
        }
        if ($this->event->venue_name) {
            $mail->line('**Venue:** ' . $this->event->venue_name);
        }

        return $mail
            ->action('View your RSVP', $rsvpUrl)
            ->line('See you there!');
    }
}
