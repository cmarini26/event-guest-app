<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GuestInvitation extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject("You're invited to {$this->event->name}")
            ->greeting("Hi {$notifiable->first_name}!")
            ->line("You've been invited to **{$this->event->name}**.")
            ->when($this->event->starts_at, function ($mail) {
                // Format in UTC (wall-clock time) — starts_at stores the raw value the host
                // entered, so UTC display gives the intended time.
                $tzLabel = $this->event->timezone ? " ({$this->event->timezone})" : '';
                return $mail->line('**Date:** ' . $this->event->starts_at->format('l, F j, Y \a\t g:i A') . $tzLabel);
            })
            ->when($this->event->venue_name, fn ($mail) => $mail->line(
                '**Venue:** ' . $this->event->venue_name
            ))
            ->action('RSVP Now', $rsvpUrl)
            ->line('We look forward to hearing from you!');
    }
}
